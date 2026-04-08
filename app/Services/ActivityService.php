<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ActivityService
{
    public function __construct(private readonly InteractionService $interactionService)
    {
    }

    public function getAll()
    {
        $this->syncOverdueStatuses();

        return Activity::query()->with(['owner', 'assignedUser', 'activityable'])->latest('scheduled_at')->get();
    }

    public function getByUid(string $uid): Activity
    {
        $this->syncOverdueStatuses();

        return Activity::query()->with(['owner', 'assignedUser', 'activityable'])->where('uid', $uid)->firstOrFail();
    }

    public function getByDateRange(string $from, string $to)
    {
        $this->syncOverdueStatuses();

        return Activity::query()
            ->with(['owner', 'assignedUser', 'activityable'])
            ->whereBetween('scheduled_at', [$from, $to])
            ->orderBy('scheduled_at')
            ->get();
    }

    public function create(array $data): Activity
    {
        $validated = $this->validate($data);
        $payload = $this->normalizePayload($validated);

        return Activity::query()->create($payload)->fresh(['owner', 'assignedUser', 'activityable']);
    }

    public function update(string $uid, array $data): Activity
    {
        $activity = $this->getByUid($uid);
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

    public function delete(string $uid): void
    {
        $this->getByUid($uid)->delete();
    }

    private function validate(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'type' => [$partial ? 'sometimes' : 'required', 'string', 'in:task,reminder,meeting'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'status' => 'sometimes|string|in:pending,completed,overdue',
            'scheduled_at' => [$partial ? 'sometimes' : 'required', 'date'],
            'assigned_user_uid' => 'nullable|uuid',
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
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

    private function normalizePayload(array $data, ?Activity $activity = null): array
    {
        $payload = [
            'type' => $data['type'] ?? $activity?->type,
            'title' => $data['title'] ?? $activity?->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $activity?->description,
            'status' => $data['status'] ?? $activity?->status ?? 'pending',
            'scheduled_at' => $data['scheduled_at'] ?? $activity?->scheduled_at,
        ];

        if (array_key_exists('assigned_user_uid', $data)) {
            $payload['assigned_user_id'] = $this->resolveUserId($data['assigned_user_uid']);
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

    private function syncOverdueStatuses(): void
    {
        Activity::query()
            ->where('status', 'pending')
            ->where('scheduled_at', '<', now())
            ->update(['status' => 'overdue']);
    }
}
