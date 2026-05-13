<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ProjectMilestone;
use App\Support\ApiIndex;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class ScheduleService
{
    public function items(array $filters = []): LengthAwarePaginator
    {
        $validated = Validator::make($filters, [
            'status' => 'nullable|string',
            'source' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ])->validate();

        $sources = $this->csv($validated['source'] ?? null);
        $statuses = $this->csv($validated['status'] ?? null);
        $search = mb_strtolower((string) ($validated['search'] ?? ''));
        $items = collect();

        if ($sources === [] || in_array('agenda', $sources, true)) {
            $items = $items->merge($this->activityItems($statuses, $search));
        }

        if ($sources === [] || in_array('project', $sources, true)) {
            $items = $items->merge($this->milestoneItems($statuses, $search));
        }

        if ($sources === [] || in_array('pipeline', $sources, true)) {
            $items = $items->merge([]);
        }

        $items = $items
            ->sortBy(fn (array $item) => $item['date'] ?? '9999-12-31 23:59:59')
            ->values();

        $perPage = ApiIndex::perPage($filters);
        $page = ApiIndex::page($filters);
        $pageItems = $items->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $pageItems,
            $items->count(),
            $perPage,
            $page,
            ['pageName' => 'schedule_page']
        );
    }

    private function activityItems(array $statuses, string $search)
    {
        return Activity::query()
            ->with(['assignedUser', 'activityable'])
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->whereRaw('LOWER(title) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(description) LIKE ?', ['%'.$search.'%']);
                });
            })
            ->get()
            ->map(fn (Activity $activity) => [
                'uid' => $activity->uid,
                'source' => 'agenda',
                'type' => $activity->type,
                'title' => $activity->title,
                'description' => $activity->description,
                'status' => $activity->status,
                'date' => $activity->scheduled_at?->toDateTimeString(),
                'scheduled_at' => $activity->scheduled_at,
                'due_date' => null,
                'assigned_to_uid' => $activity->assigned_to_uid,
                'assigned_to_name' => $activity->assigned_to_name,
                'entity_uid' => $activity->activityable_uid,
            ]);
    }

    private function milestoneItems(array $statuses, string $search)
    {
        $today = Carbon::today();

        return ProjectMilestone::query()
            ->with(['project', 'assignedUser'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(description) LIKE ?', ['%'.$search.'%'])
                        ->orWhereHas('project', fn ($projectQuery) => $projectQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']));
                });
            })
            ->get()
            ->map(function (ProjectMilestone $milestone) use ($today) {
                $rawStatus = $milestone->getRawOriginal('status');
                $status = in_array($rawStatus, ['done', 'completed'], true)
                    ? 'completed'
                    : $rawStatus;

                if ($status !== 'completed' && $milestone->due_date && $milestone->due_date->lt($today)) {
                    $status = 'overdue';
                }

                return [
                    'uid' => $milestone->uid,
                    'source' => 'project',
                    'type' => 'milestone',
                    'title' => $milestone->name,
                    'description' => $milestone->description,
                    'status' => $status,
                    'date' => $milestone->due_date?->toDateString(),
                    'scheduled_at' => null,
                    'due_date' => $milestone->due_date?->toDateString(),
                    'project_uid' => $milestone->project_uid,
                    'project_name' => $milestone->project?->name,
                    'assigned_to_uid' => $milestone->assigned_to_uid,
                    'assigned_to_name' => $milestone->assigned_to_name,
                ];
            })
            ->filter(fn (array $item) => $statuses === [] || in_array($item['status'], $statuses, true))
            ->values();
    }

    private function csv(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
