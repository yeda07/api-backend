<?php

namespace App\Services;

use App\Models\Project;

class ProgressService
{
    public function calculateProgress(Project|string $project): array
    {
        $project = $project instanceof Project
            ? $project->loadMissing('milestones')
            : Project::query()->with('milestones')->where('uid', $project)->firstOrFail();

        $milestones = $project->milestones;
        $total = $milestones->count();
        $done = $milestones->where('status', 'done')->count();
        $inProgress = $milestones->where('status', 'in_progress')->count();
        $pending = $milestones->where('status', 'pending')->count();

        return [
            'project_uid' => $project->uid,
            'status' => $project->status,
            'progress_percent' => $total > 0 ? round(($done / $total) * 100, 2) : 0.0,
            'milestones' => [
                'total' => $total,
                'done' => $done,
                'in_progress' => $inProgress,
                'pending' => $pending,
            ],
        ];
    }
}
