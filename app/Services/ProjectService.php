<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\User;
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
        $filters = $this->normalizeProjectPayload($filters);
        $validated = Validator::make($filters, [
            'status' => 'nullable|string|in:pending,active,completed,planning,in_progress,on_hold,cancelled',
            'account_uid' => 'nullable|uuid',
            'client_uid' => 'nullable|uuid',
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
        $data = $this->normalizeProjectPayload($data);
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

            $project = $this->projectRepository->create([
                'tenant_id' => auth()->user()->tenant_id,
                'account_id' => $accountId,
                'opportunity_id' => $opportunity?->getKey(),
                'assigned_user_id' => !empty($validated['assigned_to_uid']) ? $this->resolveUserId($validated['assigned_to_uid']) : null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 'pending',
                'priority' => $validated['priority'] ?? 'medium',
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'estimated_hours' => $validated['estimated_hours'] ?? 0,
                'actual_hours' => $validated['actual_hours'] ?? 0,
            ]);

            $this->syncPrimaryAssignment($project, $validated);

            return $project->fresh(['account', 'opportunity.stage', 'assignedUser', 'milestones', 'assignments.user']);
        });
    }

    public function updateProject(string $uid, array $data): Project
    {
        $project = $this->projectRepository->findByUid($uid);
        $data = $this->normalizeProjectPayload($data);
        $validated = $this->validateProject($data, true);

        return DB::transaction(function () use ($project, $validated) {
            $payload = [];

            foreach (['name', 'description', 'status', 'priority', 'start_date', 'end_date', 'estimated_hours', 'actual_hours'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $payload[$field] = $validated[$field];
                }
            }

            if (array_key_exists('assigned_to_uid', $validated)) {
                $payload['assigned_user_id'] = $validated['assigned_to_uid'] ? $this->resolveUserId($validated['assigned_to_uid']) : null;
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

            $project = $this->projectRepository->update($project, $payload);
            $this->syncPrimaryAssignment($project, $validated);

            return $project->fresh(['account', 'opportunity.stage', 'assignedUser', 'milestones', 'assignments.user']);
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
            'priority' => $overrides['priority'] ?? 'medium',
            'estimated_hours' => $overrides['estimated_hours'] ?? 0,
            'actual_hours' => $overrides['actual_hours'] ?? 0,
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
            'client_uid' => 'nullable|uuid',
            'opportunity_uid' => 'sometimes|nullable|uuid',
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'status' => 'sometimes|string|in:pending,active,completed,planning,in_progress,on_hold,cancelled',
            'priority' => 'sometimes|string|in:low,medium,high',
            'assigned_to_uid' => 'nullable|uuid',
            'estimated_hours' => 'nullable|numeric|min:0',
            'actual_hours' => 'nullable|numeric|min:0',
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

    private function normalizeProjectPayload(array $data): array
    {
        if (array_key_exists('client_uid', $data) && !array_key_exists('account_uid', $data)) {
            $data['account_uid'] = $data['client_uid'];
        }

        if (array_key_exists('status', $data)) {
            $data['status'] = match ($data['status']) {
                'planning' => 'pending',
                'in_progress' => 'active',
                default => $data['status'],
            };
        }

        unset($data['client_uid'], $data['client_name'], $data['assigned_to_name']);

        return $data;
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

    private function syncPrimaryAssignment(Project $project, array $validated): void
    {
        if (empty($validated['assigned_to_uid'])) {
            return;
        }

        $userId = $this->resolveUserId($validated['assigned_to_uid']);

        ProjectAssignment::query()->firstOrCreate(
            [
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->getKey(),
                'user_id' => $userId,
            ],
            [
                'role' => 'manager',
                'hours_allocated' => $validated['estimated_hours'] ?? 0,
            ]
        );
    }

    private function resolveUserId(string $userUid): int
    {
        $userId = User::query()->where('uid', $userUid)->value('id');

        if (!$userId) {
            throw ValidationException::withMessages([
                'assigned_to_uid' => ['El usuario asignado no existe o no pertenece al tenant'],
            ]);
        }

        return $userId;
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
