<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\InventoryProduct;
use App\Models\Invoice;
use App\Models\Quotation;
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
            $accountsTotal = Account::query()->count();
            $contactsTotal = Contact::query()->count();
            $crmEntitiesTotal = CrmEntity::query()->count();
            $tagsTotal = Tag::query()->count();
            $tasksTotal = Task::query()->count();
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
                    'accounts' => $accountsTotal,
                    'contacts' => $contactsTotal,
                    'crm_entities' => $crmEntitiesTotal,
                    'tags' => $tagsTotal,
                    'tasks' => $tasksTotal,
                ],
                'kpis' => [
                    'conversion_rate' => $this->conversionRate($accountsTotal, $crmEntitiesTotal),
                    'mrr' => $this->monthlyRecurringRevenue(),
                    'at_risk_count' => $this->atRiskCount(),
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
                'overdue_tasks' => $this->overdueTasks(),
                'recent_quotations' => $this->recentQuotations(),
                'low_stock_products' => $this->lowStockProducts(),
                'monthly_sales' => $this->monthlySales(),
            ];
        };

        try {
            return Cache::store($preferredStore)->remember($cacheKey, now()->addMinutes(5), $resolver);
        } catch (\Throwable $e) {
            return Cache::store('failover')->remember($cacheKey, now()->addMinutes(5), $resolver);
        }
    }

    private function conversionRate(int $accountsTotal, int $crmEntitiesTotal): float
    {
        $base = $accountsTotal + $crmEntitiesTotal;

        if ($base === 0) {
            return 0.0;
        }

        return round(($accountsTotal / $base) * 100, 2);
    }

    private function monthlyRecurringRevenue(): float
    {
        return round((float) Invoice::query()
            ->whereIn('status', ['issued', 'partial', 'paid'])
            ->whereBetween('issued_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('total'), 2);
    }

    private function atRiskCount(): int
    {
        $riskTags = Tag::query()->where('category', 'risk')->get();

        return $riskTags->sum(fn (Tag $tag) => $tag->accounts()->count() + $tag->contacts()->count());
    }

    private function overdueTasks(): array
    {
        return Task::query()
            ->with(['assignedUser', 'taskable'])
            ->whereDate('due_date', today())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 WHEN 'low' THEN 2 ELSE 3 END")
            ->orderBy('due_date')
            ->limit(10)
            ->get()
            ->map(fn (Task $task) => [
                'uid' => $task->uid,
                'title' => $task->title,
                'account_name' => $this->displayName($task->taskable),
                'assigned_to_name' => $task->assignedUser?->name,
                'due_date' => $task->due_date?->startOfDay()->toISOString(),
                'priority' => $task->priority,
            ])
            ->values()
            ->all();
    }

    private function recentQuotations(): array
    {
        return Quotation::query()
            ->with(['quoteable', 'items'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Quotation $quotation) => [
                'uid' => $quotation->uid,
                'number' => $quotation->quote_number,
                'account_name' => $this->displayName($quotation->quoteable),
                'total' => (float) $quotation->total,
                'status' => $this->dashboardQuotationStatus($quotation->status),
                'created_at' => $quotation->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function lowStockProducts(): array
    {
        return InventoryProduct::query()
            ->withSum('stocks as physical_stock_sum', 'physical_stock')
            ->withSum('stocks as reserved_stock_sum', 'reserved_stock')
            ->where('is_active', true)
            ->where('reorder_point', '>', 0)
            ->get()
            ->map(function (InventoryProduct $product) {
                $currentStock = max(0, (int) ($product->physical_stock_sum ?? 0) - (int) ($product->reserved_stock_sum ?? 0));
                $minimumStock = (int) $product->reorder_point;

                if ($currentStock >= $minimumStock) {
                    return null;
                }

                return [
                    'uid' => $product->uid,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'current_stock' => $currentStock,
                    'minimum_stock' => $minimumStock,
                    'stock_status' => $currentStock < ($minimumStock * 0.5) ? 'critical' : 'low',
                    '_stock_ratio' => $minimumStock > 0 ? $currentStock / $minimumStock : 1,
                ];
            })
            ->filter()
            ->sortBy('_stock_ratio')
            ->take(10)
            ->map(function (array $product) {
                unset($product['_stock_ratio']);

                return $product;
            })
            ->values()
            ->all();
    }

    private function monthlySales(): array
    {
        $months = collect(range(11, 0))
            ->map(fn (int $offset) => now()->subMonths($offset)->startOfMonth())
            ->values();

        $start = $months->first()->toDateString();
        $end = $months->last()->copy()->endOfMonth()->toDateString();

        $totals = Invoice::query()
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$start, $end])
            ->get(['issued_at', 'total'])
            ->groupBy(fn (Invoice $invoice) => $invoice->issued_at->format('Y-m'))
            ->map(fn ($invoices) => round((float) $invoices->sum('total'), 2));

        return $months
            ->map(fn ($month) => [
                'month' => $month->format('Y-m'),
                'label' => $this->monthLabel((int) $month->format('n')),
                'actual' => (float) ($totals[$month->format('Y-m')] ?? 0),
                'goal' => null,
            ])
            ->all();
    }

    private function displayName(mixed $model): ?string
    {
        return $model?->display_name
            ?? $model?->name
            ?? trim((string) (($model?->first_name ?? '') . ' ' . ($model?->last_name ?? '')))
            ?: null;
    }

    private function dashboardQuotationStatus(string $status): string
    {
        return match ($status) {
            'approved' => 'approved',
            'rejected', 'cancelled' => 'rejected',
            'draft' => 'review',
            default => 'pending',
        };
    }

    private function monthLabel(int $month): string
    {
        return [
            1 => 'Ene',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dic',
        ][$month] ?? '';
    }
}
