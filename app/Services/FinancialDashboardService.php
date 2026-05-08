<?php

namespace App\Services;

use App\Models\FinancialRecord;
use App\Models\Invoice;
use App\Models\QuotationItem;
use Carbon\CarbonImmutable;

class FinancialDashboardService
{
    public function getMonthlySales(): float
    {
        return round((float) Invoice::query()
            ->whereMonth('issued_at', now()->month)
            ->whereYear('issued_at', now()->year)
            ->sum('total'), 2);
    }

    public function getPendingInvoices(): array
    {
        return [
            'count' => Invoice::query()->whereIn('status', ['issued', 'partial'])->count(),
            'total' => round((float) Invoice::query()->whereIn('status', ['issued', 'partial'])->sum('outstanding_total'), 2),
        ];
    }

    public function getOverdueInvoices(): array
    {
        return [
            'count' => Invoice::query()->where('status', 'overdue')->count(),
            'total' => round((float) Invoice::query()->where('status', 'overdue')->sum('outstanding_total'), 2),
        ];
    }

    public function getAverageMargin(): float
    {
        return round((float) QuotationItem::query()->avg('margin_percent'), 2);
    }

    public function getWeeklySales(): array
    {
        return Invoice::query()
            ->whereNotNull('issued_at')
            ->where('issued_at', '>=', now()->subWeeks(8)->startOfWeek())
            ->orderBy('issued_at')
            ->get()
            ->groupBy(fn (Invoice $invoice) => $invoice->issued_at?->format('o-W'))
            ->map(fn ($invoices, string $week) => [
                'week' => $week,
                'total_sales' => round((float) $invoices->sum('total'), 2),
            ])
            ->values()
            ->all();
    }

    public function getWeeklySalesAmounts(): array
    {
        return collect($this->getWeeklySales())
            ->pluck('total_sales')
            ->map(fn ($amount) => round((float) $amount, 2))
            ->values()
            ->all();
    }

    public function getRecentInvoices(): array
    {
        return Invoice::query()
            ->with(['invoiceable'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'uid' => $invoice->uid,
                'invoice_number' => $invoice->invoice_number,
                'client_name' => $invoice->invoiceable?->display_name
                    ?? $invoice->invoiceable?->name
                    ?? null,
                'status' => $invoice->status,
                'currency' => $invoice->currency,
                'amount' => (float) $invoice->total,
                'total' => (float) $invoice->total,
                'outstanding_total' => (float) $invoice->outstanding_total,
                'invoiceable_uid' => $invoice->invoiceable_uid,
                'issued_at' => $invoice->issued_at?->toDateString(),
            ])
            ->all();
    }

    public function dashboard(): array
    {
        $pending = $this->getPendingInvoices();
        $overdue = $this->getOverdueInvoices();
        $records = FinancialRecord::query()->get();
        $monthlySales = $this->getMonthlySales();
        $previousMonthlySales = $this->getSalesForMonth(now()->copy()->subMonthNoOverflow()->format('Y-m'));
        $growth = $previousMonthlySales > 0
            ? round((($monthlySales - $previousMonthlySales) / $previousMonthlySales) * 100, 2)
            : ($monthlySales > 0 ? 100.0 : 0.0);
        $overdueClientsCount = Invoice::query()
            ->where('status', 'overdue')
            ->where('outstanding_total', '>', 0)
            ->get(['invoiceable_type', 'invoiceable_id'])
            ->map(fn (Invoice $invoice) => $invoice->invoiceable_type . ':' . $invoice->invoiceable_id)
            ->unique()
            ->count();
        $averageMargin = $this->getAverageMargin();

        return [
            'stats' => [
                'monthly_sales' => $monthlySales,
                'monthly_sales_growth_percent' => $growth,
                'pending_invoices_count' => $pending['count'],
                'pending_invoices_amount' => $pending['total'],
                'overdue_portfolio' => $overdue['total'],
                'overdue_clients_count' => $overdueClientsCount,
                'average_margin_percent' => $averageMargin,
                'margin_target_percent' => 45,
            ],
            'totals' => [
                'paid' => round((float) $records->where('status', 'paid')->sum('amount'), 2),
                'outstanding' => round((float) $records->sum('outstanding_amount'), 2),
                'overdue' => round((float) $records->where('status', 'overdue')->sum('outstanding_amount'), 2),
            ],
            'counts' => [
                'paid' => $records->where('status', 'paid')->count(),
                'overdue' => $records->where('status', 'overdue')->count(),
                'records' => $records->count(),
            ],
            'monthly_sales' => $monthlySales,
            'pending_invoices' => $pending,
            'overdue_invoices' => $overdue,
            'average_margin' => $averageMargin,
            'weekly_sales' => $this->getWeeklySalesAmounts(),
            'weekly_sales_details' => $this->getWeeklySales(),
            'recent_invoices' => $this->getRecentInvoices(),
        ];
    }

    private function getSalesForMonth(string $period): float
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $period . '-01')->startOfMonth();

        return round((float) Invoice::query()
            ->whereBetween('issued_at', [$start, $start->endOfMonth()])
            ->sum('total'), 2);
    }
}
