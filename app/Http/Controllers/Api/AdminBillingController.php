<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\ApiIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminBillingController extends Controller
{
    public function summary()
    {
        $currentMonth = now()->startOfMonth();

        $invoices = Invoice::withoutGlobalScopes()
            ->whereDate('issued_at', '>=', $currentMonth)
            ->get();

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

    public function index(Request $request)
    {
        $validated = Validator::make($request->query(), [
            'tenant_uid' => 'nullable|uuid',
            'estado' => 'nullable|string|in:PAGADA,PENDIENTE,VENCIDA,CANCELADA',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ])->validate();

        $query = Invoice::withoutGlobalScopes()->with(['tenant.plan'])->latest('issued_at');

        if (!empty($validated['tenant_uid'])) {
            $tenantId = Tenant::query()->where('uid', $validated['tenant_uid'])->value('id');
            $query->where('tenant_id', $tenantId);
        }

        if (!empty($validated['estado'])) {
            $query->where('status', $this->toInvoiceStatus($validated['estado']));
        }

        if (!empty($validated['from'])) {
            $query->whereDate('issued_at', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->whereDate('issued_at', '<=', $validated['to']);
        }

        $result = ApiIndex::paginateOrGet($query, $validated, 'billing_page');

        if ($result instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $mapped = $result->getCollection()->map(fn (Invoice $invoice) => $this->serializeInvoice($invoice));
            $result->setCollection($mapped);

            return $this->successResponse($result);
        }

        return $this->successResponse(collect($result)->map(fn (Invoice $invoice) => $this->serializeInvoice($invoice))->values());
    }

    public function export(Request $request)
    {
        $validated = Validator::make($request->query(), [
            'tenant_uid' => 'nullable|uuid',
            'estado' => 'nullable|string|in:PAGADA,PENDIENTE,VENCIDA,CANCELADA',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'format' => 'nullable|string|in:json,csv',
        ])->validate();

        $invoices = $this->billingQuery($validated)
            ->orderByDesc('issued_at')
            ->get();

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

    public function markPaid(string $uid)
    {
        return $this->successResponse($this->markInvoicesAsPaid([$uid]), 200, 'Factura marcada como pagada');
    }

    public function markPaidBulk(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'uuid',
            ]);

            return $this->successResponse(
                $this->markInvoicesAsPaid($validated['ids']),
                200,
                'Facturas marcadas como pagadas'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    private function markInvoicesAsPaid(array $uids): array
    {
        return DB::transaction(function () use ($uids) {
            $invoices = Invoice::withoutGlobalScopes()
                ->whereIn('uid', $uids)
                ->get();

            $updated = [];

            foreach ($invoices as $invoice) {
                $invoice->update([
                    'status' => 'paid',
                    'paid_total' => $invoice->total,
                    'outstanding_total' => 0,
                ]);

                Payment::withoutGlobalScopes()->create([
                    'tenant_id' => $invoice->tenant_id,
                    'invoice_id' => $invoice->getKey(),
                    'amount' => $invoice->total,
                    'payment_date' => now()->toDateString(),
                    'method' => 'admin_mark_paid',
                    'external_reference' => 'ADMIN-' . $invoice->uid,
                    'meta' => [
                        'source' => 'admin_billing',
                    ],
                ]);

                FinancialRecord::withoutGlobalScopes()
                    ->where('tenant_id', $invoice->tenant_id)
                    ->where('external_reference', $invoice->invoice_number)
                    ->update([
                        'status' => 'paid',
                        'outstanding_amount' => 0,
                        'paid_at' => now()->toDateString(),
                    ]);

                $updated[] = $this->serializeInvoice($invoice->fresh(['tenant.plan']));
            }

            return [
                'updated' => count($updated),
                'invoices' => $updated,
            ];
        });
    }

    private function billingQuery(array $validated)
    {
        $query = Invoice::withoutGlobalScopes()->with(['tenant.plan']);

        if (!empty($validated['tenant_uid'])) {
            $tenantId = Tenant::query()->where('uid', $validated['tenant_uid'])->value('id');
            $query->where('tenant_id', $tenantId);
        }

        if (!empty($validated['estado'])) {
            $query->where('status', $this->toInvoiceStatus($validated['estado']));
        }

        if (!empty($validated['from'])) {
            $query->whereDate('issued_at', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->whereDate('issued_at', '<=', $validated['to']);
        }

        return $query;
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
