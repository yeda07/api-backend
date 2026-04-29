<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ProgressService $progressService
    ) {
    }

    public function getProjects(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'status' => 'nullable|string|in:pending,active,completed',
            'account_uid' => 'nullable|uuid',
            'opportunity_uid' => 'nullable|uuid',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ])->validate();

        if (!empty($validated['account_uid'])) {
            $validated['account_id'] = $this->resolveAccountId($validated['account_uid']);
            unset($validated['account_uid']);
        }

        if (!empty($validated['opportunity_uid'])) {
            $validated['opportunity_id'] = $this->resolveOpportunity($validated['opportunity_uid'])->getKey();
            unset($validated['opportunity_uid']);
        }

        return $this->projectRepository->all($validated);
    }

    public function showProject(string $uid): Project
    {
        return $this->projectRepository->findByUid($uid);
    }

    public function createProject(array $data): Project
    {
        $validated = $this->validateProject($data);

        return DB::transaction(function () use ($validated) {
            $accountId = $this->resolveAccountId($validated['account_uid']);
            $opportunity = !empty($validated['opportunity_uid'])
                ? $this->resolveOpportunity($validated['opportunity_uid'])
                : null;

            if ($opportunity) {
                $existing = $this->projectRepository->findByOpportunityId($opportunity->getKey());

                if ($existing) {
                    throw ValidationException::withMessages([
                        'opportunity_uid' => ['La oportunidad ya tiene un proyecto asociado'],
                    ]);
                }
            }

            return $this->projectRepository->create([
                'tenant_id' => auth()->user()->tenant_id,
                'account_id' => $accountId,
                'opportunity_id' => $opportunity?->getKey(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 'pending',
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
            ]);
        });
    }

    public function updateProject(string $uid, array $data): Project
    {
        $project = $this->projectRepository->findByUid($uid);
        $validated = $this->validateProject($data, true);

        return DB::transaction(function () use ($project, $validated) {
            $payload = [];

            foreach (['name', 'description', 'status', 'start_date', 'end_date'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $payload[$field] = $validated[$field];
                }
            }

            if (array_key_exists('account_uid', $validated)) {
                $payload['account_id'] = $this->resolveAccountId($validated['account_uid']);
            }

            if (array_key_exists('opportunity_uid', $validated)) {
                if ($validated['opportunity_uid']) {
                    $opportunity = $this->resolveOpportunity($validated['opportunity_uid']);
                    $existing = $this->projectRepository->findByOpportunityId($opportunity->getKey());

                    if ($existing && $existing->uid !== $project->uid) {
                        throw ValidationException::withMessages([
                            'opportunity_uid' => ['La oportunidad ya tiene un proyecto asociado'],
                        ]);
                    }

                    $payload['opportunity_id'] = $opportunity->getKey();
                } else {
                    $payload['opportunity_id'] = null;
                }
            }

            return $this->projectRepository->update($project, $payload);
        });
    }

    public function createFromOpportunity(string $opportunityUid, array $overrides = []): ?Project
    {
        $opportunity = $this->resolveOpportunity($opportunityUid);

        return $this->createFromOpportunityModel($opportunity, $overrides, false);
    }

    public function createFromOpportunityModel(Opportunity $opportunity, array $overrides = [], bool $quietIfNoAccount = true): ?Project
    {
        if ($existing = $this->projectRepository->findByOpportunityId($opportunity->getKey())) {
            return $existing;
        }

        $accountId = $this->resolveAccountIdFromOpportunity($opportunity);

        if (!$accountId) {
            if ($quietIfNoAccount) {
                return null;
            }

            throw ValidationException::withMessages([
                'opportunity_uid' => ['La oportunidad no tiene una cuenta resoluble para crear el proyecto'],
            ]);
        }

        return $this->projectRepository->create([
            'tenant_id' => auth()->user()?->tenant_id ?? $opportunity->tenant_id,
            'account_id' => $accountId,
            'opportunity_id' => $opportunity->getKey(),
            'name' => $overrides['name'] ?? ('Implementacion - ' . $opportunity->title),
            'description' => $overrides['description'] ?? $opportunity->description,
            'status' => $overrides['status'] ?? 'pending',
            'start_date' => $overrides['start_date'] ?? now()->toDateString(),
            'end_date' => $overrides['end_date'] ?? $opportunity->expected_close_date?->toDateString(),
        ]);
    }

    public function progress(string $projectUid): array
    {
        return $this->progressService->calculateProgress($projectUid);
    }

    private function validateProject(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'account_uid' => [$partial ? 'sometimes' : 'required', 'nullable', 'uuid'],
            'opportunity_uid' => 'sometimes|nullable|uuid',
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'status' => 'sometimes|string|in:pending,active,completed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if (!$partial && empty($validated['account_uid'])) {
            throw ValidationException::withMessages([
                'account_uid' => ['Debes enviar account_uid para crear el proyecto'],
            ]);
        }

        return $validated;
    }

    private function resolveAccountId(string $accountUid): int
    {
        $account = Account::query()->where('uid', $accountUid)->first();

        if (!$account) {
            throw ValidationException::withMessages([
                'account_uid' => ['La cuenta no existe o no pertenece a este tenant'],
            ]);
        }

        return $account->getKey();
    }

    private function resolveOpportunity(string $opportunityUid): Opportunity
    {
        return Opportunity::query()->where('uid', $opportunityUid)->firstOrFail();
    }

    private function resolveAccountIdFromOpportunity(Opportunity $opportunity): ?int
    {
        $opportunity->loadMissing('opportunityable');
        $entity = $opportunity->opportunityable;

        if ($entity instanceof Account) {
            return $entity->getKey();
        }

        if ($entity instanceof Contact) {
            return $entity->account_id;
        }

        return null;
    }
}
