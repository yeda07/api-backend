<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function getAll()
    {
        return Task::query()->with(['owner', 'assignedUser', 'taskable'])->latest()->get();
    }

    public function getByUid(string $uid): Task
    {
        return Task::query()->with(['owner', 'assignedUser', 'taskable'])->where('uid', $uid)->firstOrFail();
    }

    public function create(array $data): Task
    {
        $validated = $this->validate($data);
        $payload = $this->normalizePayload($validated);

        return Task::query()->create($payload)->fresh(['owner', 'assignedUser', 'taskable']);
    }

    public function update(string $uid, array $data): Task
    {
        $task = $this->getByUid($uid);
        $validated = $this->validate($data, true);
        $payload = $this->normalizePayload($validated, $task);

        $task->update($payload);

        return $task->fresh(['owner', 'assignedUser', 'taskable']);
    }

    public function delete(string $uid): void
    {
        $this->getByUid($uid)->delete();
    }

    private function validate(array $data, bool $partial = false): array
    {
        $rules = [
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'status' => 'sometimes|string|in:pending,in_progress,completed,cancelled',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
            'due_date' => 'nullable|date',
            'assigned_user_uid' => 'nullable|uuid',
            'taskable_type' => 'nullable|string',
            'taskable_uid' => 'nullable|uuid',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if (!empty($validated['taskable_type']) xor !empty($validated['taskable_uid'])) {
            throw ValidationException::withMessages([
                'taskable_uid' => ['Debes enviar taskable_type y taskable_uid juntos'],
            ]);
        }

        return $validated;
    }

    private function normalizePayload(array $data, ?Task $task = null): array
    {
        $payload = [
            'title' => $data['title'] ?? $task?->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $task?->description,
            'status' => $data['status'] ?? $task?->status ?? 'pending',
            'priority' => $data['priority'] ?? $task?->priority ?? 'medium',
            'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $task?->due_date?->toDateString(),
        ];

        if (array_key_exists('assigned_user_uid', $data)) {
            $payload['assigned_user_id'] = $this->resolveUserId($data['assigned_user_uid']);
        }

        if (array_key_exists('taskable_type', $data) || array_key_exists('taskable_uid', $data)) {
            if (!empty($data['taskable_type']) && !empty($data['taskable_uid'])) {
                $entity = find_entity_by_uid($data['taskable_type'], $data['taskable_uid']);

                if (!$entity) {
                    throw ValidationException::withMessages([
                        'taskable_uid' => ['La entidad relacionada no existe o no es visible'],
                    ]);
                }

                $payload['taskable_type'] = get_class($entity);
                $payload['taskable_id'] = $entity->getKey();
            } else {
                $payload['taskable_type'] = null;
                $payload['taskable_id'] = null;
            }
        }

        $targetStatus = $payload['status'] ?? $task?->status;
        $payload['completed_at'] = $targetStatus === 'completed'
            ? ($task?->completed_at ?? now())
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
}
