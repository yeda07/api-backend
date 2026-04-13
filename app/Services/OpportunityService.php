<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\OpportunityStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OpportunityService
{
    public function stages()
    {
        return OpportunityStage::query()->orderBy('position')->get();
    }

    public function createStage(array $data): OpportunityStage
    {
        $validated = $this->validateStage($data);

        return OpportunityStage::query()->create($validated);
    }

    public function updateStage(string $uid, array $data): OpportunityStage
    {
        $stage = OpportunityStage::query()->where('uid', $uid)->firstOrFail();
        $validated = $this->validateStage($data, true);
        $stage->update($validated);

        return $stage->fresh();
    }

    public function deleteStage(string $uid): void
    {
        $stage = OpportunityStage::query()->where('uid', $uid)->firstOrFail();

        if ($stage->opportunities()->exists()) {
            throw ValidationException::withMessages([
                'stage' => ['No puedes eliminar una etapa con oportunidades asociadas'],
            ]);
        }

        $stage->delete();
    }

    public function opportunities(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'stage_uid' => 'nullable|uuid',
            'owner_user_uid' => 'nullable|uuid',
        ])->validate();

        return Opportunity::query()
            ->with(['stage', 'owner', 'opportunityable'])
            ->when(!empty($validated['stage_uid']), function ($query) use ($validated) {
                $stageId = OpportunityStage::query()->where('uid', $validated['stage_uid'])->value('id');
                $query->where('stage_id', $stageId ?: 0);
            })
            ->when(!empty($validated['owner_user_uid']), function ($query) use ($validated) {
                $ownerId = User::query()->where('uid', $validated['owner_user_uid'])->value('id');
                $query->where('owner_user_id', $ownerId ?: 0);
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function createOpportunity(array $data): Opportunity
    {
        $validated = $this->validateOpportunity($data);

        return DB::transaction(function () use ($validated) {
            $stage = $this->resolveStage($validated['stage_uid']);
            $entity = $this->resolveEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);

            return Opportunity::query()->create([
                'owner_user_id' => $this->resolveOwnerUserId($validated['owner_user_uid'] ?? null, $entity?->owner_user_id),
                'stage_id' => $stage->getKey(),
                'opportunityable_type' => $entity ? get_class($entity) : null,
                'opportunityable_id' => $entity?->getKey(),
                'title' => $validated['title'],
                'amount' => $validated['amount'] ?? 0,
                'currency' => $validated['currency'] ?? null,
                'expected_close_date' => $validated['expected_close_date'] ?? null,
                'description' => $validated['description'] ?? null,
                'won_at' => $stage->is_won ? now() : null,
                'lost_at' => $stage->is_lost ? now() : null,
            ])->fresh(['stage', 'owner', 'opportunityable']);
        });
    }

    public function updateOpportunity(string $uid, array $data): Opportunity
    {
        $opportunity = Opportunity::query()->with(['stage', 'owner', 'opportunityable'])->where('uid', $uid)->firstOrFail();
        $validated = $this->validateOpportunity($data, true);

        return DB::transaction(function () use ($opportunity, $validated) {
            $payload = [];

            foreach (['title', 'amount', 'currency', 'expected_close_date', 'description'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $payload[$field] = $validated[$field];
                }
            }

            if (array_key_exists('stage_uid', $validated)) {
                $stage = $this->resolveStage($validated['stage_uid']);
                $payload['stage_id'] = $stage->getKey();
                $payload['won_at'] = $stage->is_won ? now() : null;
                $payload['lost_at'] = $stage->is_lost ? now() : null;
            }

            if (array_key_exists('owner_user_uid', $validated)) {
                $payload['owner_user_id'] = $this->resolveOwnerUserId($validated['owner_user_uid']);
            }

            if (array_key_exists('entity_type', $validated) || array_key_exists('entity_uid', $validated)) {
                $entity = $this->resolveEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);
                $payload['opportunityable_type'] = $entity ? get_class($entity) : null;
                $payload['opportunityable_id'] = $entity?->getKey();
            }

            $opportunity->update($payload);

            return $opportunity->fresh(['stage', 'owner', 'opportunityable']);
        });
    }

    public function deleteOpportunity(string $uid): void
    {
        Opportunity::query()->where('uid', $uid)->firstOrFail()->delete();
    }

    public function board(): array
    {
        $stages = OpportunityStage::query()->orderBy('position')->get();
        $opportunities = Opportunity::query()->with(['stage', 'owner', 'opportunityable'])->get()->groupBy('stage_id');

        return [
            'stages' => $stages->map(function (OpportunityStage $stage) use ($opportunities) {
                $items = $opportunities->get($stage->getKey(), collect())->values();

                return [
                    'stage' => $stage,
                    'summary' => [
                        'count' => $items->count(),
                        'amount' => round((float) $items->sum(fn ($opportunity) => (float) $opportunity->amount), 2),
                    ],
                    'items' => $items,
                ];
            })->values(),
        ];
    }

    public function summary(): array
    {
        $opportunities = Opportunity::query()->with('stage')->get();

        return [
            'totals' => [
                'count' => $opportunities->count(),
                'amount' => round((float) $opportunities->sum(fn ($opportunity) => (float) $opportunity->amount), 2),
                'won_count' => $opportunities->filter(fn ($opportunity) => $opportunity->stage?->is_won)->count(),
                'lost_count' => $opportunities->filter(fn ($opportunity) => $opportunity->stage?->is_lost)->count(),
            ],
            'by_stage' => OpportunityStage::query()
                ->orderBy('position')
                ->get()
                ->map(function (OpportunityStage $stage) {
                    $items = Opportunity::query()->where('stage_id', $stage->getKey())->get();

                    return [
                        'stage_uid' => $stage->uid,
                        'stage_name' => $stage->name,
                        'count' => $items->count(),
                        'amount' => round((float) $items->sum(fn ($opportunity) => (float) $opportunity->amount), 2),
                    ];
                })
                ->values(),
        ];
    }

    private function validateStage(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'key' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'position' => 'sometimes|integer|min:1',
            'probability_percent' => 'sometimes|integer|min:0|max:100',
            'is_won' => 'sometimes|boolean',
            'is_lost' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function validateOpportunity(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'stage_uid' => [$partial ? 'sometimes' : 'required', 'uuid'],
            'owner_user_uid' => 'nullable|uuid',
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'amount' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'expected_close_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if (!empty($validated['entity_type']) xor !empty($validated['entity_uid'])) {
            throw ValidationException::withMessages([
                'entity_uid' => ['Debes enviar entity_type y entity_uid juntos'],
            ]);
        }

        return $validated;
    }

    private function resolveStage(string $uid): OpportunityStage
    {
        return OpportunityStage::query()->where('uid', $uid)->firstOrFail();
    }

    private function resolveEntity(?string $type, ?string $uid)
    {
        if (!$type && !$uid) {
            return null;
        }

        $entity = find_entity_by_uid($type, $uid);

        if (!$entity) {
            throw ValidationException::withMessages([
                'entity_uid' => ['La entidad comercial no existe o no es visible'],
            ]);
        }

        return $entity;
    }

    private function resolveOwnerUserId(?string $uid, ?int $fallback = null): ?int
    {
        if (!$uid) {
            return $fallback ?? auth()->id();
        }

        $userId = User::query()->where('uid', $uid)->value('id');

        if (!$userId) {
            throw ValidationException::withMessages([
                'owner_user_uid' => ['El usuario no existe o no pertenece a este tenant'],
            ]);
        }

        return $userId;
    }
}
