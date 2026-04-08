<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function core(): array
    {
        $user = auth()->user();
        $tenantUid = $user->tenant?->uid;
        $cacheKey = "dashboard:core:tenant:{$tenantUid}:user:{$user->uid}";
        $preferredStore = config('cache.dashboard_store', 'redis');
        $resolver = function () {
            $accountsToday = Account::query()->whereDate('created_at', today())->count();
            $contactsToday = Contact::query()->whereDate('created_at', today())->count();
            $crmEntitiesToday = CrmEntity::query()->whereDate('created_at', today())->count();
            $overdueTasksToday = Task::query()
                ->whereDate('due_date', today())
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();

            return [
                'summary' => [
                    'new_customers_today' => $accountsToday + $contactsToday + $crmEntitiesToday,
                    'overdue_tasks_today' => $overdueTasksToday,
                    'tasks_supported' => true,
                ],
                'breakdown' => [
                    'accounts_created_today' => $accountsToday,
                    'contacts_created_today' => $contactsToday,
                    'crm_entities_created_today' => $crmEntitiesToday,
                    'tasks_due_today' => Task::query()->whereDate('due_date', today())->count(),
                ],
                'totals' => [
                    'accounts' => Account::query()->count(),
                    'contacts' => Contact::query()->count(),
                    'crm_entities' => CrmEntity::query()->count(),
                    'tags' => Tag::query()->count(),
                    'tasks' => Task::query()->count(),
                ],
                'top_tags' => Tag::query()
                    ->withCount(['accounts', 'contacts', 'crmEntities'])
                    ->get()
                    ->map(function (Tag $tag) {
                        $usageCount = $tag->accounts_count + $tag->contacts_count + $tag->crm_entities_count;

                        return [
                            'uid' => $tag->uid,
                            'name' => $tag->name,
                            'color' => $tag->color,
                            'category' => $tag->category,
                            'usage_count' => $usageCount,
                        ];
                    })
                    ->sortByDesc('usage_count')
                    ->take(5)
                    ->values()
                    ->all(),
            ];
        };

        try {
            return Cache::store($preferredStore)->remember($cacheKey, now()->addMinutes(5), $resolver);
        } catch (\Throwable $e) {
            return Cache::store('failover')->remember($cacheKey, now()->addMinutes(5), $resolver);
        }
    }
}
