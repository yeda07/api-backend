<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\PlatformBillingService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminBillingController extends Controller
{
    public function summary(PlatformBillingService $billing)
    {
        $currentMonth = now()->startOfMonth();

        $invoices = $billing->invoices(['from' => $currentMonth->toDateString()]);

        return $this->successResponse([
            'cobrado_este_mes' => round((float) $invoices->where('status', 'paid')->sum('paid_total'), 2),
            'pendiente_cobro' => round((float) $invoices->where('status', 'issued')->sum('outstanding_total'), 2),
            'facturas_vencidas' => round((float) $invoices->where('status', 'overdue')->sum('outstanding_total'), 2),
            'total_facturas' => $invoices->count(),
            'pagadas' => $invoices->where('status', 'paid')->count(),
            'pendientes' => $invoices->where('status', 'issued')->count(),
            'vencidas' => $invoices->where('status', 'overdue')->count(),
        ]);
    }

    public function index(Request $request, PlatformBillingService $billing)
    {
        $validated = Validator::make($request->query(), [
            'tenant_uid' => 'nullable|uuid',
            'search' => 'nullable|string|max:255',
            'plan_uid' => 'nullable|uuid',
            'plan_nombre' => 'nullable|string|max:255',
            'estado' => 'nullable|string|in:PAGADA,PENDIENTE,VENCIDA,CANCELADA',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ])->validate();

        $invoices = $billing->invoices($this->billingFilters($validated));
        $rows = $invoices->map(fn (Invoice $invoice) => $this->serializeInvoice($invoice))->values();

        if (array_key_exists('page', $validated) || array_key_exists('per_page', $validated) || config('performance.force_index_pagination', false)) {
            return $this->successResponse($this->paginateRows($rows, $validated, 'billing_page'));
        }

        return $this->successResponse($rows);
    }

    public function export(Request $request, PlatformBillingService $billing)
    {
        $validated = Validator::make($request->query(), [
            'tenant_uid' => 'nullable|uuid',
            'search' => 'nullable|string|max:255',
            'plan_uid' => 'nullable|uuid',
            'plan_nombre' => 'nullable|string|max:255',
            'estado' => 'nullable|string|in:PAGADA,PENDIENTE,VENCIDA,CANCELADA',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'format' => 'nullable|string|in:json,csv',
        ])->validate();

        $invoices = $billing->invoices($this->billingFilters($validated));

        $rows = $invoices
            ->map(fn (Invoice $invoice) => $this->serializeInvoice($invoice))
            ->values();

        $summary = [
            'total_facturas' => $rows->count(),
            'total_monto' => round((float) $rows->sum('total'), 2),
            'total_pagado' => round((float) $rows->where('status', 'PAGADA')->sum('total'), 2),
            'total_pendiente' => round((float) $rows->where('status', 'PENDIENTE')->sum('total'), 2),
            'total_vencido' => round((float) $rows->where('status', 'VENCIDA')->sum('total'), 2),
        ];

        if (($validated['format'] ?? 'json') === 'csv') {
            return response($this->toCsv($rows->all()), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="billing-report.csv"',
            ]);
        }

        return $this->successResponse([
            'summary' => $summary,
            'rows' => $rows,
        ]);
    }

    public function markPaid(string $uid, PlatformBillingService $billing)
    {
        $invoices = $this->serializeInvoices($billing->markPaid([$uid]));

        return $this->successResponse($invoices[0] ?? null, 200, 'Factura marcada como pagada');
    }

    public function markPaidBulk(Request $request, PlatformBillingService $billing)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'uuid',
            ]);

            return $this->successResponse(
                $this->serializeInvoices($billing->markPaid($validated['ids'])),
                200,
                'Facturas marcadas como pagadas'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    private function billingFilters(array $validated): array
    {
        return array_merge($validated, [
            'status' => ! empty($validated['estado']) ? $this->toInvoiceStatus($validated['estado']) : null,
        ]);
    }

    private function toCsv(array $rows): string
    {
        $headers = ['uid', 'tenant', 'periodo', 'plan', 'total', 'status', 'issued_at', 'due_at'];
        $lines = [implode(',', $headers)];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($value) => $this->csvValue($value), [
                $row['uid'],
                $row['tenant_nombre'],
                $row['periodo'],
                $row['plan_nombre'],
                $row['total'],
                $row['status'],
                $row['issued_at'],
                $row['due_at'],
            ]));
        }

        return implode("\n", $lines) . "\n";
    }

    private function csvValue(mixed $value): string
    {
        $value = (string) $value;

        return '"' . str_replace('"', '""', $value) . '"';
    }

    private function serializeInvoice(Invoice $invoice): array
    {
        $tenant = $invoice->tenant;
        $plan = $tenant?->plan;

        return [
            'uid' => $invoice->uid,
            'tenant_uid' => $tenant?->uid,
            'tenant_nombre' => $tenant?->name,
            'periodo' => optional($invoice->issued_at)?->translatedFormat('F Y'),
            'plan_nombre' => $plan?->name,
            'subtotal' => (float) $invoice->subtotal,
            'tax' => 0,
            'total' => (float) $invoice->total,
            'status' => $this->toFrontendStatus($invoice->status),
            'issued_at' => optional($invoice->issued_at)?->toISOString(),
            'due_at' => optional($invoice->due_date)?->toISOString(),
        ];
    }

    private function serializeInvoices(array $invoices): array
    {
        return collect($invoices)
            ->map(fn (Invoice $invoice) => $this->serializeInvoice($invoice))
            ->values()
            ->all();
    }

    private function paginateRows($rows, array $filters, string $pageName): LengthAwarePaginator
    {
        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? config('performance.default_per_page', 25)), 1), (int) config('performance.max_per_page', 100));

        return new LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
            ]
        );
    }

    private function toInvoiceStatus(string $status): string
    {
        return match ($status) {
            'PAGADA' => 'paid',
            'PENDIENTE' => 'issued',
            'VENCIDA' => 'overdue',
            'CANCELADA' => 'draft',
            default => 'issued',
        };
    }

    private function toFrontendStatus(string $status): string
    {
        return match ($status) {
            'paid' => 'PAGADA',
            'overdue' => 'VENCIDA',
            'draft' => 'CANCELADA',
            default => 'PENDIENTE',
        };
    }
}
