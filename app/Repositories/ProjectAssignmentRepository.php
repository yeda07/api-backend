<?php

namespace App\Repositories;

use App\Models\ProjectAssignment;

class ProjectAssignmentRepository
{
    public function findByUid(string $uid): ProjectAssignment
    {
        return ProjectAssignment::query()->with(['project', 'user'])->where('uid', $uid)->firstOrFail();
    }

    public function forProject(int $projectId)
    {
        return ProjectAssignment::query()
            ->with('user')
            ->where('project_id', $projectId)
            ->orderBy('created_at')
            ->get();
    }

    public function existsForProjectAndUser(int $projectId, int $userId): bool
    {
        return ProjectAssignment::query()
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function create(array $data): ProjectAssignment
    {
        return ProjectAssignment::query()->create($data)->fresh(['project', 'user']);
    }

    public function delete(ProjectAssignment $assignment): void
    {
        $assignment->delete();
    }
}
