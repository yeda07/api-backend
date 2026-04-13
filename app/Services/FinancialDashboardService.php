<?php

namespace App\Services;

use App\Models\FinancialRecord;
use App\Models\Invoice;
use App\Models\QuotationItem;

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
            ->selectRaw("strftime('%Y-%W', issued_at) as week_key, SUM(total) as total_sales")
            ->whereNotNull('issued_at')
            ->groupBy('week_key')
            ->orderByDesc('week_key')
            ->limit(8)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($row) => [
                'week' => $row->week_key,
                'total_sales' => round((float) $row->total_sales, 2),
            ])
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
                'status' => $invoice->status,
                'currency' => $invoice->currency,
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

        return [
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
            'monthly_sales' => $this->getMonthlySales(),
            'pending_invoices' => $pending,
            'overdue_invoices' => $overdue,
            'average_margin' => $this->getAverageMargin(),
            'weekly_sales' => $this->getWeeklySales(),
            'recent_invoices' => $this->getRecentInvoices(),
        ];
    }
}
