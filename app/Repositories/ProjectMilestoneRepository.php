<?php

namespace App\Repositories;

use App\Models\ProjectMilestone;

class ProjectMilestoneRepository
{
    public function findByUid(string $uid): ProjectMilestone
    {
        return ProjectMilestone::query()->with(['project', 'assignedUser'])->where('uid', $uid)->firstOrFail();
    }

    public function create(array $data): ProjectMilestone
    {
        return ProjectMilestone::query()->create($data)->fresh(['project', 'assignedUser']);
    }

    public function update(ProjectMilestone $milestone, array $data): ProjectMilestone
    {
        $milestone->update($data);

        return $milestone->fresh(['project', 'assignedUser']);
    }
}
