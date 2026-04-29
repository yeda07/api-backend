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
        $validated = $this->validate($data);

        return $this->projectMilestoneRepository->create([
            'tenant_id' => auth()->user()->tenant_id,
            'project_id' => $project->getKey(),
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
        $validated = $this->validate($data, true);

        return $this->projectMilestoneRepository->update($milestone, $validated);
    }

    public function updateStatus(string $uid, string $status): ProjectMilestone
    {
        return $this->updateMilestone($uid, ['status' => $status]);
    }

    private function validate(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => 'sometimes|string|in:pending,in_progress,done',
            'order' => 'sometimes|integer|min:1',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
