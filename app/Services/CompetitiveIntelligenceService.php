<?php

namespace App\Services;

use App\Support\ApiIndex;
use App\Models\Battlecard;
use App\Models\Competitor;
use App\Models\LostReason;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CompetitiveIntelligenceService
{
    public function competitors(array $filters = [])
    {
        return ApiIndex::paginateOrGet(
            Competitor::query()->orderBy('name'),
            $filters,
            'competitors_page'
        );
    }

    public function createCompetitor(array $data): Competitor
    {
        $validated = Validator::make($data, [
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:100',
            'website' => 'nullable|url|max:255',
            'strengths' => 'nullable|array',
            'weaknesses' => 'nullable|array',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        $this->ensureUniqueCompetitorKey($validated['key']);

        return Competitor::query()->create([
            'name' => $validated['name'],
            'key' => $validated['key'],
            'website' => $validated['website'] ?? null,
            'strengths' => $validated['strengths'] ?? [],
            'weaknesses' => $validated['weaknesses'] ?? [],
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);
    }

    public function updateCompetitor(string $uid, array $data): Competitor
    {
        $competitor = $this->findCompetitor($uid);

        $validated = Validator::make($data, [
            'name' => 'sometimes|string|max:255',
            'key' => 'sometimes|string|max:100',
            'website' => 'nullable|url|max:255',
            'strengths' => 'nullable|array',
            'weaknesses' => 'nullable|array',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        if (isset($validated['key']) && $validated['key'] !== $competitor->key) {
            $this->ensureUniqueCompetitorKey($validated['key'], $competitor->getKey());
        }

        $competitor->update($validated);

        return $competitor->fresh();
    }

    public function deleteCompetitor(string $uid): void
    {
        $this->findCompetitor($uid)->delete();
    }

    public function battlecards(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'competitor_uid' => 'nullable|uuid',
            'is_active' => 'nullable|boolean',
        ])->validate();

        $query = Battlecard::query()->with('competitor')->latest();

        if (!empty($validated['competitor_uid'])) {
            $query->where('competitor_id', $this->findCompetitor($validated['competitor_uid'])->getKey());
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        return ApiIndex::paginateOrGet($query, $filters, 'battlecards_page');
    }

    public function createBattlecard(array $data): Battlecard
    {
        $validated = Validator::make($data, [
            'competitor_uid' => 'required|uuid',
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'differentiators' => 'nullable|array',
            'objection_handlers' => 'nullable|array',
            'recommended_actions' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        return Battlecard::query()->create([
            'competitor_id' => $this->findCompetitor($validated['competitor_uid'])->getKey(),
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'differentiators' => $validated['differentiators'] ?? [],
            'objection_handlers' => $validated['objection_handlers'] ?? [],
            'recommended_actions' => $validated['recommended_actions'] ?? [],
            'is_active' => $validated['is_active'] ?? true,
        ])->fresh('competitor');
    }

    public function updateBattlecard(string $uid, array $data): Battlecard
    {
        $battlecard = $this->findBattlecard($uid);

        $validated = Validator::make($data, [
            'competitor_uid' => 'sometimes|uuid',
            'title' => 'sometimes|string|max:255',
            'summary' => 'nullable|string',
            'differentiators' => 'nullable|array',
            'objection_handlers' => 'nullable|array',
            'recommended_actions' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        $payload = $validated;

        if (isset($validated['competitor_uid'])) {
            $payload['competitor_id'] = $this->findCompetitor($validated['competitor_uid'])->getKey();
            unset($payload['competitor_uid']);
        }

        $battlecard->update($payload);

        return $battlecard->fresh('competitor');
    }

    public function deleteBattlecard(string $uid): void
    {
        $this->findBattlecard($uid)->delete();
    }

    public function lostReasons(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'competitor_uid' => 'nullable|uuid',
            'opportunity_uid' => 'nullable|uuid',
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'reason_type' => 'nullable|string',
        ])->validate();

        $query = LostReason::query()->with(['competitor', 'opportunity', 'owner', 'lossable'])->latest('lost_at');

        if (!empty($validated['competitor_uid'])) {
            $query->where('competitor_id', $this->findCompetitor($validated['competitor_uid'])->getKey());
        }

        if (!empty($validated['opportunity_uid'])) {
            $query->where('opportunity_id', $this->findOpportunity($validated['opportunity_uid'])->getKey());
        }

        if (!empty($validated['entity_type']) || !empty($validated['entity_uid'])) {
            $entity = $this->resolveEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);
            $query->where('lossable_type', get_class($entity))
                ->where('lossable_id', $entity->getKey());
        }

        if (!empty($validated['reason_type'])) {
            $query->where('reason_type', $validated['reason_type']);
        }

        return ApiIndex::paginateOrGet($query, $filters, 'lost_reasons_page');
    }

    public function createLostReason(array $data): LostReason
    {
        $validated = $this->validateLostReason($data);
        $entity = $this->resolveOptionalEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);

        return LostReason::query()->create([
            'competitor_id' => !empty($validated['competitor_uid']) ? $this->findCompetitor($validated['competitor_uid'])->getKey() : null,
            'opportunity_id' => !empty($validated['opportunity_uid']) ? $this->findOpportunity($validated['opportunity_uid'])->getKey() : null,
            'owner_user_id' => !empty($validated['owner_user_uid']) ? $this->findUser($validated['owner_user_uid'])->getKey() : auth()->id(),
            'lossable_type' => $entity ? get_class($entity) : null,
            'lossable_id' => $entity?->getKey(),
            'reason_type' => $validated['reason_type'],
            'summary' => $validated['summary'],
            'details' => $validated['details'] ?? null,
            'lost_at' => $validated['lost_at'],
            'estimated_value' => $validated['estimated_value'] ?? null,
            'meta' => $validated['meta'] ?? null,
        ])->fresh(['competitor', 'opportunity', 'owner', 'lossable']);
    }

    public function updateLostReason(string $uid, array $data): LostReason
    {
        $lostReason = $this->findLostReason($uid);
        $validated = $this->validateLostReason($data, true);
        $payload = [];

        if (array_key_exists('competitor_uid', $validated)) {
            $payload['competitor_id'] = $validated['competitor_uid'] ? $this->findCompetitor($validated['competitor_uid'])->getKey() : null;
        }

        if (array_key_exists('opportunity_uid', $validated)) {
            $payload['opportunity_id'] = $validated['opportunity_uid'] ? $this->findOpportunity($validated['opportunity_uid'])->getKey() : null;
        }

        if (array_key_exists('owner_user_uid', $validated)) {
            $payload['owner_user_id'] = $validated['owner_user_uid'] ? $this->findUser($validated['owner_user_uid'])->getKey() : null;
        }

        if (array_key_exists('entity_type', $validated) || array_key_exists('entity_uid', $validated)) {
            $entity = $this->resolveOptionalEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);
            $payload['lossable_type'] = $entity ? get_class($entity) : null;
            $payload['lossable_id'] = $entity?->getKey();
        }

        foreach (['reason_type', 'summary', 'details', 'lost_at', 'estimated_value', 'meta'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        $lostReason->update($payload);

        return $lostReason->fresh(['competitor', 'opportunity', 'owner', 'lossable']);
    }

    public function deleteLostReason(string $uid): void
    {
        $this->findLostReason($uid)->delete();
    }

    public function lostReasonsReport(array $filters = []): array
    {
        $lostReasons = $this->lostReasons($filters);

        return [
            'summary' => [
                'count' => $lostReasons->count(),
                'estimated_value_total' => round((float) $lostReasons->sum('estimated_value'), 2),
            ],
            'by_reason_type' => $lostReasons->groupBy('reason_type')
                ->map(fn ($group, $reasonType) => [
                    'reason_type' => $reasonType,
                    'count' => $group->count(),
                    'estimated_value_total' => round((float) $group->sum('estimated_value'), 2),
                ])
                ->values(),
            'by_competitor' => $lostReasons->groupBy(fn (LostReason $lostReason) => $lostReason->competitor?->name ?? 'Sin competidor')
                ->map(fn ($group, $competitor) => [
                    'competitor' => $competitor,
                    'count' => $group->count(),
                    'estimated_value_total' => round((float) $group->sum('estimated_value'), 2),
                ])
                ->values(),
            'latest' => $lostReasons->take(10)->values(),
        ];
    }

    private function validateLostReason(array $data, bool $partial = false): array
    {
        return Validator::make($data, [
            'competitor_uid' => 'nullable|uuid',
            'opportunity_uid' => 'nullable|uuid',
            'owner_user_uid' => 'nullable|uuid',
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'reason_type' => [$partial ? 'sometimes' : 'required', 'string', 'in:price,features,relationship,timing,implementation,procurement,other'],
            'summary' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'details' => 'nullable|string',
            'lost_at' => [$partial ? 'sometimes' : 'required', 'date'],
            'estimated_value' => 'nullable|numeric|min:0',
            'meta' => 'nullable|array',
        ])->validate();
    }

    private function ensureUniqueCompetitorKey(string $key, ?int $ignoreId = null): void
    {
        $exists = Competitor::withoutGlobalScopes()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('key', $key)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'key' => ['Ya existe un competidor con esta clave en el tenant'],
            ]);
        }
    }

    private function findCompetitor(string $uid): Competitor
    {
        $competitor = Competitor::withoutGlobalScopes()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('uid', $uid)
            ->first();

        if (!$competitor) {
            throw ValidationException::withMessages([
                'competitor_uid' => ['El competidor no existe o no pertenece a este tenant'],
            ]);
        }

        return $competitor;
    }

    private function findBattlecard(string $uid): Battlecard
    {
        $battlecard = Battlecard::withoutGlobalScopes()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('uid', $uid)
            ->first();

        if (!$battlecard) {
            throw ValidationException::withMessages([
                'battlecard_uid' => ['La battlecard no existe o no pertenece a este tenant'],
            ]);
        }

        return $battlecard;
    }

    private function findLostReason(string $uid): LostReason
    {
        $lostReason = LostReason::withoutGlobalScopes()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('uid', $uid)
            ->first();

        if (!$lostReason) {
            throw ValidationException::withMessages([
                'lost_reason_uid' => ['El registro de perdida no existe o no pertenece a este tenant'],
            ]);
        }

        return $lostReason;
    }

    private function findOpportunity(string $uid): Opportunity
    {
        $opportunity = Opportunity::query()->where('uid', $uid)->first();

        if (!$opportunity) {
            throw ValidationException::withMessages([
                'opportunity_uid' => ['La oportunidad no existe o no es visible'],
            ]);
        }

        return $opportunity;
    }

    private function findUser(string $uid): User
    {
        $user = User::query()->where('uid', $uid)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'owner_user_uid' => ['El usuario no existe o no es visible'],
            ]);
        }

        return $user;
    }

    private function resolveEntity(?string $type, ?string $uid)
    {
        if (!$type || !$uid) {
            throw ValidationException::withMessages([
                'entity_uid' => ['Debes enviar entity_type y entity_uid'],
            ]);
        }

        $entity = find_entity_by_uid($type, $uid);

        if (!$entity) {
            throw ValidationException::withMessages([
                'entity_uid' => ['La entidad asociada no existe o no es visible'],
            ]);
        }

        return $entity;
    }

    private function resolveOptionalEntity(?string $type, ?string $uid)
    {
        if (!$type && !$uid) {
            return null;
        }

        return $this->resolveEntity($type, $uid);
    }
}
