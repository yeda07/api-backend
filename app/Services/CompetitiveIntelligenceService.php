<?php

namespace App\Services;

use App\Support\ApiIndex;
use App\Models\Account;
use App\Models\Battlecard;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\Competitor;
use App\Models\LostReason;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        $data = $this->normalizeCompetitorPayload($data);

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
        $data = $this->normalizeCompetitorPayload($data, true);

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
            'search' => 'nullable|string|max:255',
            'competitor_uid' => 'nullable|uuid',
            'is_active' => 'nullable|boolean',
        ])->validate();

        $query = Battlecard::query()->with('competitor')->latest();

        if (!empty($validated['competitor_uid'])) {
            $query->where('competitor_id', $this->findCompetitor($validated['competitor_uid'])->getKey());
        }

        if (!empty($validated['search'])) {
            $search = '%' . mb_strtolower($validated['search']) . '%';
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->whereRaw('LOWER(title) LIKE ?', [$search])
                    ->orWhereHas('competitor', fn ($competitorQuery) => $competitorQuery->whereRaw('LOWER(name) LIKE ?', [$search]));
            });
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        return ApiIndex::paginateOrGet($query, $filters, 'battlecards_page');
    }

    public function createBattlecard(array $data): Battlecard
    {
        $data = $this->normalizeBattlecardPayload($data);

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
        $data = $this->normalizeBattlecardPayload($data);

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
        $result = ApiIndex::paginateOrGet($this->lostReasonsQuery($filters), $filters, 'lost_reasons_page');

        return method_exists($result, 'through')
            ? $result->through(fn (LostReason $lostReason) => $this->serializeLostReason($lostReason))
            : $result->map(fn (LostReason $lostReason) => $this->serializeLostReason($lostReason))->values();
    }

    public function createLostReason(array $data): LostReason
    {
        $data = $this->normalizeLostReasonPayload($data);
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
            'currency' => $validated['currency'] ?? null,
            'meta' => $validated['meta'] ?? null,
        ])->fresh(['competitor', 'opportunity', 'owner', 'lossable']);
    }

    public function updateLostReason(string $uid, array $data): LostReason
    {
        $lostReason = $this->findLostReason($uid);
        $data = $this->normalizeLostReasonPayload($data);
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

        foreach (['reason_type', 'summary', 'details', 'lost_at', 'estimated_value', 'currency', 'meta'] as $field) {
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
        unset($filters['page'], $filters['per_page']);

        $lostReasons = $this->lostReasonsQuery($filters)->get();

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
            'latest' => $lostReasons
                ->take(10)
                ->map(fn (LostReason $lostReason) => $this->serializeLostReason($lostReason))
                ->values(),
        ];
    }

    public function lostReasonsHeatmap(array $filters = []): array
    {
        $validated = Validator::make($filters, [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ])->validate();

        return DB::table('lost_reasons')
            ->where('lost_reasons.tenant_id', auth()->user()?->tenant_id)
            ->leftJoin('competitors', 'lost_reasons.competitor_id', '=', 'competitors.id')
            ->when(! empty($validated['date_from']), fn ($query) => $query->whereDate('lost_reasons.lost_at', '>=', $validated['date_from']))
            ->when(! empty($validated['date_to']), fn ($query) => $query->whereDate('lost_reasons.lost_at', '<=', $validated['date_to']))
            ->select([
                'competitors.uid as competitor_uid',
                'competitors.name as competitor_name',
                'lost_reasons.reason_type as reason_key',
            ])
            ->selectRaw('COUNT(lost_reasons.id) as count')
            ->groupBy('competitors.uid', 'competitors.name', 'lost_reasons.reason_type')
            ->orderBy('competitors.name')
            ->orderBy('lost_reasons.reason_type')
            ->get()
            ->map(fn ($row) => [
                'competitor_uid' => $row->competitor_uid,
                'competitor_name' => $row->competitor_name,
                'reason_key' => $row->reason_key,
                'reason_label' => $this->lostReasonLabel($row->reason_key),
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    private function lostReasonsQuery(array $filters)
    {
        $validated = Validator::make($filters, [
            'search' => 'nullable|string|max:255',
            'competitor_uid' => 'nullable|uuid',
            'opportunity_uid' => 'nullable|uuid',
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'reason_type' => 'nullable|string',
        ])->validate();

        $query = LostReason::query()
            ->with(['competitor:id,uid,name', 'opportunity:id,uid,title', 'owner:id,uid,name', 'lossable'])
            ->latest('lost_at');

        if (!empty($validated['competitor_uid'])) {
            $query->where('competitor_id', $this->findCompetitor($validated['competitor_uid'])->getKey());
        }

        if (!empty($validated['search'])) {
            $search = '%' . mb_strtolower($validated['search']) . '%';
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->whereRaw('LOWER(summary) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(details) LIKE ?', [$search])
                    ->orWhereHas('competitor', fn ($competitorQuery) => $competitorQuery->whereRaw('LOWER(name) LIKE ?', [$search]));
            });
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

        return $query;
    }

    private function serializeLostReason(LostReason $lostReason): array
    {
        return [
            'uid' => $lostReason->uid,
            'competitor_uid' => $lostReason->competitor?->uid,
            'competitor_name' => $lostReason->competitor?->name,
            'opportunity_uid' => $lostReason->opportunity?->uid,
            'owner_user_uid' => $lostReason->owner?->uid,
            'entity_uid' => $lostReason->entity_uid,
            'entity_type' => $this->normalizeLossableType($lostReason->lossable_type),
            'account_name' => $lostReason->account_name ?? $this->resolveLossableLabel($lostReason),
            'deal_value' => round((float) ($lostReason->estimated_value ?? 0), 2),
            'lost_reason_category' => $lostReason->lost_reason_category,
            'lost_reason_detail' => $lostReason->lost_reason_detail,
            'closed_date' => $lostReason->lost_at?->toISOString(),
            'sales_rep' => $lostReason->owner?->name,
            'reason_type' => $lostReason->reason_type,
            'summary' => $lostReason->summary,
            'details' => $lostReason->details,
            'lost_at' => $lostReason->lost_at,
            'estimated_value' => round((float) ($lostReason->estimated_value ?? 0), 2),
            'currency' => $lostReason->currency,
            'meta' => $lostReason->meta,
            'created_at' => $lostReason->created_at,
            'updated_at' => $lostReason->updated_at,
        ];
    }

    private function lostReasonLabel(?string $reasonType): string
    {
        return match ($reasonType) {
            'price' => 'Precio',
            'features' => 'Producto',
            'relationship' => 'Relacion',
            'timing' => 'Timing',
            'implementation', 'procurement' => 'Servicio',
            default => 'Otro',
        };
    }

    private function normalizeLossableType(?string $type): ?string
    {
        return match ($type) {
            Account::class => 'account',
            Contact::class => 'contact',
            CrmEntity::class => 'crm-entity',
            null, '' => null,
            default => str(class_basename($type))->snake()->toString(),
        };
    }

    private function resolveLossableLabel(LostReason $lostReason): ?string
    {
        if (! $lostReason->lossable_type || ! $lostReason->lossable_id) {
            return $lostReason->opportunity?->title;
        }

        $entity = $lostReason->relationLoaded('lossable')
            ? $lostReason->lossable
            : null;

        return $entity?->display_name
            ?? $entity?->name
            ?? $entity?->title
            ?? $lostReason->opportunity?->title;
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
            'currency' => 'nullable|string|max:10',
            'meta' => 'nullable|array',
        ])->validate();
    }

    private function normalizeCompetitorPayload(array $data, bool $partial = false): array
    {
        if (!$partial && empty($data['key']) && !empty($data['name'])) {
            $data['key'] = str($data['name'])->lower()->slug('-')->toString();
        }

        if (array_key_exists('description', $data) && !array_key_exists('notes', $data)) {
            $data['notes'] = $data['description'];
        }

        unset($data['description'], $data['strength_score']);

        return $data;
    }

    private function normalizeBattlecardPayload(array $data): array
    {
        if (array_key_exists('description', $data) && !array_key_exists('summary', $data)) {
            $data['summary'] = $data['description'];
        }

        if (array_key_exists('strengths', $data) && !array_key_exists('differentiators', $data)) {
            $data['differentiators'] = $data['strengths'];
        }

        if (array_key_exists('weaknesses', $data) && !array_key_exists('recommended_actions', $data)) {
            $data['recommended_actions'] = $data['weaknesses'];
        }

        if (array_key_exists('objections', $data) && !array_key_exists('objection_handlers', $data)) {
            $data['objection_handlers'] = collect($data['objections'])
                ->map(function ($objection) {
                    if (!is_array($objection)) {
                        return $objection;
                    }

                    return [
                        'objection' => $objection['objection'] ?? null,
                        'response' => $objection['response'] ?? null,
                    ];
                })
                ->all();
        }

        unset($data['competitor_name'], $data['description'], $data['strengths'], $data['weaknesses'], $data['objections']);

        return $data;
    }

    private function normalizeLostReasonPayload(array $data): array
    {
        if (array_key_exists('lost_reason_category', $data) && !array_key_exists('reason_type', $data)) {
            $data['reason_type'] = match ($data['lost_reason_category']) {
                'Precio' => 'price',
                'Producto' => 'features',
                'Relacion', 'Relación' => 'relationship',
                'Timing' => 'timing',
                'Servicio' => 'implementation',
                default => 'other',
            };
        }

        if (array_key_exists('lost_reason_detail', $data) && !array_key_exists('details', $data)) {
            $data['details'] = $data['lost_reason_detail'];
        }

        if (array_key_exists('deal_value', $data) && !array_key_exists('estimated_value', $data)) {
            $data['estimated_value'] = $data['deal_value'];
        }

        if (array_key_exists('closed_date', $data) && !array_key_exists('lost_at', $data)) {
            $data['lost_at'] = $data['closed_date'];
        }

        if (!array_key_exists('summary', $data) && !empty($data['details'])) {
            $data['summary'] = str($data['details'])->limit(250, '')->toString();
        }

        unset(
            $data['account_name'],
            $data['competitor_name'],
            $data['lost_reason_category'],
            $data['lost_reason_detail'],
            $data['deal_value'],
            $data['closed_date'],
            $data['sales_rep']
        );

        return $data;
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
