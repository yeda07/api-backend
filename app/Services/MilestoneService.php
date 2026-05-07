<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Repositories\ProjectMilestoneRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MilestoneService
{
    public function __construct(private readonly ProjectMilestoneRepository $projectMilestoneRepository)
    {
    }

    public function createMilestone(string $projectUid, array $data): ProjectMilestone
    {
        $project = Project::query()->where('uid', $projectUid)->firstOrFail();
        $data = $this->normalizePayload($data);
        $validated = $this->validate($data);

        return $this->projectMilestoneRepository->create([
            'tenant_id' => auth()->user()->tenant_id,
            'project_id' => $project->getKey(),
            'assigned_user_id' => !empty($validated['assigned_to_uid']) ? $this->resolveUserId($validated['assigned_to_uid']) : null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => $validated['status'] ?? 'pending',
            'order' => $validated['order'] ?? (($project->milestones()->max('order') ?? 0) + 1),
        ]);
    }

    public function updateMilestone(string $uid, array $data): ProjectMilestone
    {
        $milestone = $this->projectMilestoneRepository->findByUid($uid);
        $data = $this->normalizePayload($data);
        $validated = $this->validate($data, true);

        $payload = $validated;

        if (array_key_exists('assigned_to_uid', $payload)) {
            $payload['assigned_user_id'] = $payload['assigned_to_uid'] ? $this->resolveUserId($payload['assigned_to_uid']) : null;
            unset($payload['assigned_to_uid']);
        }

        return $this->projectMilestoneRepository->update($milestone, $payload);
    }

    public function updateStatus(string $uid, string $status): ProjectMilestone
    {
        return $this->updateMilestone($uid, ['status' => $status]);
    }

    private function validate(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => 'sometimes|string|in:pending,in_progress,done,completed',
            'assigned_to_uid' => 'nullable|uuid',
            'order' => 'sometimes|integer|min:1',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function normalizePayload(array $data): array
    {
        if (array_key_exists('title', $data) && !array_key_exists('name', $data)) {
            $data['name'] = $data['title'];
        }

        if (($data['status'] ?? null) === 'completed') {
            $data['status'] = 'done';
        }

        unset($data['title']);

        return $data;
    }

    private function resolveUserId(string $userUid): int
    {
        $userId = \App\Models\User::query()->where('uid', $userUid)->value('id');

        if (!$userId) {
            throw ValidationException::withMessages([
                'assigned_to_uid' => ['El usuario asignado no existe o no pertenece al tenant'],
            ]);
        }

        return $userId;
    }
}
