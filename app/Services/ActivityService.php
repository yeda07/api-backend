<?php

namespace App\Services;

use App\Support\ApiIndex;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ActivityService
{
    public function __construct(private readonly InteractionService $interactionService)
    {
    }

    public function getAll(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'type' => 'nullable|string|in:task,call,meeting,email,note,reminder,nota,llamada,reunion,demo,seguimiento',
            'status' => 'nullable',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'paginate' => 'sometimes',
        ])->validate();

        $statuses = $this->normalizeStatusFilter($validated['status'] ?? null);

        $query = Activity::query()
            ->with($this->activityIndexRelations())
            ->latest('scheduled_at');

        if (!empty($validated['entity_type']) || !empty($validated['entity_uid'])) {
            if (empty($validated['entity_type']) || empty($validated['entity_uid'])) {
                throw ValidationException::withMessages([
                    'entity_uid' => ['Debes enviar entity_type y entity_uid juntos'],
                ]);
            }

            $entity = find_entity_by_uid($validated['entity_type'], $validated['entity_uid']);

            if (!$entity) {
                throw ValidationException::withMessages([
                    'entity_uid' => ['La entidad no existe o no es visible'],
                ]);
            }

            $query->where('activityable_type', get_class($entity))
                ->where('activityable_id', $entity->getKey());
        }

        if (!empty($validated['type'])) {
            $query->where('type', $this->normalizeActivityType($validated['type']));
        }

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if (!empty($validated['search'])) {
            $search = '%' . mb_strtolower($validated['search']) . '%';
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->whereRaw('LOWER(title) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$search]);
            });
        }

        if (!empty($validated['date_from'])) {
            $query->where('scheduled_at', '>=', $this->dateBoundary($validated['date_from'], true));
        }

        if (!empty($validated['date_to'])) {
            $query->where('scheduled_at', '<=', $this->dateBoundary($validated['date_to'], false));
        }

        $withoutPagination = filter_var($filters['paginate'] ?? true, FILTER_VALIDATE_BOOLEAN) === false;

        if ($withoutPagination) {
            $limit = min(max((int) ($filters['per_page'] ?? 25), 1), 100);

            return $query->limit($limit)->get()
                ->map(fn (Activity $activity) => $this->serializeActivityIndex($activity))
                ->values();
        }

        $result = ApiIndex::paginateOrGet(
            $query,
            $filters,
            'activities_page'
        );

        return method_exists($result, 'through')
            ? $result->through(fn (Activity $activity) => $this->serializeActivityIndex($activity))
            : $result->map(fn (Activity $activity) => $this->serializeActivityIndex($activity))->values();
    }

    public function getByUid(string $uid): Activity
    {
        return Activity::query()->with($this->activityIndexRelations())->where('uid', $uid)->firstOrFail();
    }

    public function getByUidPayload(string $uid): array
    {
        return $this->serializeActivityIndex($this->getByUid($uid));
    }

    public function getByDateRange(string $from, string $to)
    {
        return Activity::query()
            ->with($this->activityIndexRelations())
            ->whereBetween('scheduled_at', [$from, $to])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Activity $activity) => $this->serializeActivityIndex($activity))
            ->values();
    }

    public function create(array $data): Activity
    {
        $data = $this->normalizeActivityPayload($data);
        $validated = $this->validate($data);
        $payload = $this->normalizePayload($validated);

        return Activity::query()->create($payload)->fresh(['owner', 'assignedUser', 'activityable']);
    }

    public function createPayload(array $data): array
    {
        return $this->serializeActivityIndex($this->create($data));
    }

    public function update(string $uid, array $data): Activity
    {
        $activity = $this->getByUid($uid);
        $data = $this->normalizeActivityPayload($data, true);
        $validated = $this->validate($data, true);
        $previousStatus = $activity->status;
        $payload = $this->normalizePayload($validated, $activity);

        $activity->update($payload);
        $activity = $activity->fresh(['owner', 'assignedUser', 'activityable']);

        if ($previousStatus !== $activity->status && $activity->activityable) {
            $this->interactionService->recordStatusChange($activity->activityable, $previousStatus, $activity->status, [
                'activity_uid' => $activity->uid,
                'activity_title' => $activity->title,
                'activity_type' => $activity->type,
            ]);
        }

        return $activity;
    }

    public function updatePayload(string $uid, array $data): array
    {
        return $this->serializeActivityIndex($this->update($uid, $data));
    }

    public function delete(string $uid): void
    {
        $this->getByUid($uid)->delete();
    }

    private function validate(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'type' => [$partial ? 'sometimes' : 'required', 'string', 'in:task,call,meeting,email,note,reminder'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'status' => 'sometimes|string|in:pending,in_progress,completed,cancelled,overdue',
            'priority' => 'sometimes|string|in:low,medium,high',
            'scheduled_at' => [$partial ? 'sometimes' : 'required', 'date'],
            'assigned_user_uid' => 'nullable|uuid',
            'assigned_to_uid' => 'nullable|uuid',
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'contact_uid' => 'nullable|uuid',
            'account_uid' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if (!empty($validated['assigned_user_uid']) && !empty($validated['assigned_to_uid']) && $validated['assigned_user_uid'] !== $validated['assigned_to_uid']) {
            throw ValidationException::withMessages([
                'assigned_to_uid' => ['No puede diferir de assigned_user_uid'],
            ]);
        }

        if (!empty($validated['entity_type']) xor !empty($validated['entity_uid'])) {
            throw ValidationException::withMessages([
                'entity_uid' => ['Debes enviar entity_type y entity_uid juntos'],
            ]);
        }

        if (!empty($validated['contact_uid']) && !empty($validated['account_uid'])) {
            $contact = Contact::query()->where('uid', $validated['contact_uid'])->first();

            if ($contact && $contact->account_uid && $contact->account_uid !== $validated['account_uid']) {
                throw ValidationException::withMessages([
                    'account_uid' => ['La cuenta enviada no corresponde al contacto'],
                ]);
            }
        }

        return $validated;
    }

    private function normalizeActivityPayload(array $data, bool $partial = false): array
    {
        if (array_key_exists('content', $data) && !array_key_exists('description', $data)) {
            $data['description'] = $data['content'];
        }

        if (array_key_exists('date', $data) && !array_key_exists('scheduled_at', $data)) {
            $data['scheduled_at'] = $data['date'];
        }

        if (array_key_exists('type', $data)) {
            $data['type'] = $this->normalizeActivityType($data['type']);
        }

        if (!$partial && empty($data['title']) && !empty($data['description'])) {
            $data['title'] = str($data['description'])->limit(80, '')->toString();
        }

        unset($data['content'], $data['date']);

        return $data;
    }

    private function normalizeActivityType(string $type): string
    {
        return match ($type) {
            'nota' => 'note',
            'llamada' => 'call',
            'reunion', 'demo' => 'meeting',
            'seguimiento' => 'reminder',
            default => $type,
        };
    }

    private function normalizeStatusFilter(mixed $status): array
    {
        if ($status === null || $status === '') {
            return [];
        }

        $values = is_array($status)
            ? $status
            : explode(',', (string) $status);

        $values = collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $allowed = ['pending', 'in_progress', 'completed', 'cancelled', 'overdue'];
        $invalid = array_values(array_diff($values, $allowed));

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'status' => ['Estados no soportados: '.implode(', ', $invalid)],
            ]);
        }

        return $values;
    }

    private function normalizePayload(array $data, ?Activity $activity = null): array
    {
        $payload = [
            'type' => $data['type'] ?? $activity?->type,
            'title' => $data['title'] ?? $activity?->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $activity?->description,
            'status' => $data['status'] ?? $activity?->status ?? 'pending',
            'priority' => $data['priority'] ?? $activity?->priority ?? 'medium',
            'scheduled_at' => $data['scheduled_at'] ?? $activity?->scheduled_at,
        ];

        if (array_key_exists('assigned_user_uid', $data) || array_key_exists('assigned_to_uid', $data)) {
            $payload['assigned_user_id'] = $this->resolveUserId($data['assigned_to_uid'] ?? $data['assigned_user_uid'] ?? null);
        }

        if (array_key_exists('contact_uid', $data) || array_key_exists('account_uid', $data)) {
            if (!empty($data['contact_uid'])) {
                $data['entity_type'] = 'contact';
                $data['entity_uid'] = $data['contact_uid'];
            } elseif (!empty($data['account_uid'])) {
                $data['entity_type'] = 'account';
                $data['entity_uid'] = $data['account_uid'];
            } else {
                $data['entity_type'] = null;
                $data['entity_uid'] = null;
            }
        }

        if (array_key_exists('entity_type', $data) || array_key_exists('entity_uid', $data)) {
            if (!empty($data['entity_type']) && !empty($data['entity_uid'])) {
                $entity = find_entity_by_uid($data['entity_type'], $data['entity_uid']);

                if (!$entity) {
                    throw ValidationException::withMessages([
                        'entity_uid' => ['La entidad no existe o no es visible'],
                    ]);
                }

                $payload['activityable_type'] = get_class($entity);
                $payload['activityable_id'] = $entity->getKey();
                $payload['owner_user_id'] = $entity->owner_user_id ?? auth()->id();
            } else {
                $payload['activityable_type'] = null;
                $payload['activityable_id'] = null;
            }
        }

        $targetStatus = $payload['status'] ?? $activity?->status;
        $payload['completed_at'] = $targetStatus === 'completed'
            ? ($activity?->completed_at ?? now())
            : null;

        return $payload;
    }

    private function resolveUserId(?string $uid): ?int
    {
        if (!$uid) {
            return null;
        }

        $userId = User::query()->where('uid', $uid)->value('id');

        if (!$userId) {
            throw ValidationException::withMessages([
                'assigned_user_uid' => ['El usuario asignado no existe o no pertenece a este tenant'],
            ]);
        }

        return $userId;
    }

    private function dateBoundary(string $value, bool $start): Carbon
    {
        $date = Carbon::parse($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            ? ($start ? $date->startOfDay() : $date->endOfDay())
            : $date;
    }

    public function syncOverdueStatuses(?int $tenantId = null): int
    {
        if ($tenantId !== null) {
            return $this->syncOverdueStatusesForTenant($tenantId);
        }

        return Tenant::query()
            ->pluck('id')
            ->sum(fn (int $id) => $this->syncOverdueStatusesForTenant($id));
    }

    private function syncOverdueStatusesForTenant(int $tenantId): int
    {
        return Activity::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('scheduled_at', '<', now())
            ->update(['status' => 'overdue']);
    }

    private function activityIndexRelations(): array
    {
        return [
            'owner',
            'assignedUser',
            'activityable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Contact::class => ['account'],
                ]);
            },
        ];
    }

    private function serializeActivityIndex(Activity $activity): array
    {
        return [
            'uid' => $activity->uid,
            'type' => $activity->type,
            'title' => $activity->title,
            'description' => $activity->description,
            'status' => $activity->status,
            'priority' => $activity->priority,
            'scheduled_at' => $activity->scheduled_at,
            'completed_at' => $activity->completed_at,
            'owner_user_uid' => $activity->owner?->uid,
            'owner_user_name' => $activity->owner?->name,
            'assigned_user_uid' => $activity->assignedUser?->uid,
            'assigned_to_uid' => $activity->assignedUser?->uid,
            'assigned_to_name' => $activity->assignedUser?->name,
            'activityable_type' => $activity->activityable_type,
            'activityable_uid' => $activity->activityable_uid,
            'contact_uid' => $activity->contact_uid,
            'contact_name' => $activity->contact_name,
            'account_uid' => $activity->account_uid,
            'account_name' => $activity->account_name,
            'created_at' => $activity->created_at,
            'updated_at' => $activity->updated_at,
        ];
    }
}
