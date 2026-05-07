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
        $done = $milestones->filter(fn ($milestone) => $milestone->getRawOriginal('status') === 'done')->count();
        $inProgress = $milestones->filter(fn ($milestone) => $milestone->getRawOriginal('status') === 'in_progress')->count();
        $pending = $milestones->filter(fn ($milestone) => $milestone->getRawOriginal('status') === 'pending')->count();
        $completion = $total > 0 ? round(($done / $total) * 100, 2) : 0.0;
        $hoursEstimated = round((float) ($project->estimated_hours ?: $project->assignments()->sum('hours_allocated')), 2);

        return [
            'project_uid' => $project->uid,
            'status' => $project->status,
            'progress_percent' => $completion,
            'completion_pct' => $completion,
            'milestones_total' => $total,
            'milestones_completed' => $done,
            'hours_estimated' => $hoursEstimated,
            'hours_logged' => round((float) $project->actual_hours, 2),
            'milestones' => [
                'total' => $total,
                'done' => $done,
                'in_progress' => $inProgress,
                'pending' => $pending,
            ],
        ];
    }
}
