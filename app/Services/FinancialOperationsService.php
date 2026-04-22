<?php

namespace App\Services;

use App\Support\ApiIndex;
use App\Models\FinancialRecord;
use App\Models\Quotation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FinancialOperationsService
{
    public function __construct(private readonly FinancialDashboardService $financialDashboardService)
    {
    }

    public function records(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'status' => 'nullable|string|in:paid,partial,open,overdue',
            'record_type' => 'nullable|string|in:invoice_paid,collection_received,invoice_open',
            'source_system' => 'nullable|string|max:255',
        ])->validate();

        $query = FinancialRecord::query()->with(['owner', 'quotation', 'financeable'])->latest('paid_at');

        if (!empty($validated['entity_type']) || !empty($validated['entity_uid'])) {
            $entity = $this->resolveEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);
            $query->where('financeable_type', get_class($entity))
                ->where('financeable_id', $entity->getKey());
        }

        foreach (['status', 'record_type', 'source_system'] as $field) {
            if (!empty($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        return ApiIndex::paginateOrGet($query, $filters, 'financial_records_page');
    }

    public function importRecord(array $data): FinancialRecord
    {
        $validated = $this->validateImport($data);
        $entity = $this->resolveEntity($validated['entity_type'], $validated['entity_uid']);
        $quotation = $this->resolveQuotation($validated['quotation_uid'] ?? null);
        $isSettledRecord = in_array($validated['status'], ['paid', 'partial'], true);
        $paidAt = $validated['paid_at'] ?? ($isSettledRecord ? ($validated['issued_at'] ?? now()->toDateString()) : null);

        $payload = [
            'owner_user_id' => $entity->owner_user_id ?? auth()->id(),
            'quotation_id' => $quotation?->getKey(),
            'financeable_type' => get_class($entity),
            'financeable_id' => $entity->getKey(),
            'record_type' => $validated['record_type'],
            'source_system' => $validated['source_system'] ?? 'external_accounting',
            'external_reference' => $validated['external_reference'] ?? null,
            'amount' => $validated['amount'],
            'outstanding_amount' => $validated['outstanding_amount'] ?? 0,
            'currency' => $validated['currency'] ?? null,
            'issued_at' => $validated['issued_at'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'paid_at' => $paidAt,
            'status' => $validated['status'],
            'meta' => $validated['meta'] ?? null,
        ];

        return FinancialRecord::query()->updateOrCreate(
            [
                'external_reference' => $validated['external_reference'] ?? null,
                'source_system' => $payload['source_system'],
            ],
            $payload
        )->fresh(['owner', 'quotation', 'financeable']);
    }

    public function customerSummary(string $entityType, string $entityUid): array
    {
        $entity = $this->resolveEntity($entityType, $entityUid);
        $records = FinancialRecord::query()
            ->with(['quotation'])
            ->where('financeable_type', get_class($entity))
            ->where('financeable_id', $entity->getKey())
            ->latest('issued_at')
            ->get();

        return [
            'entity_uid' => $entity->uid,
            'entity_type' => get_class($entity),
            'totals' => [
                'invoiced' => round((float) $records->whereIn('record_type', ['invoice_paid', 'invoice_open'])->sum('amount'), 2),
                'paid' => round((float) $records->whereIn('status', ['paid', 'partial'])->sum(function ($record) {
                    return (float) $record->amount - (float) $record->outstanding_amount;
                }), 2),
                'outstanding' => round((float) $records->sum('outstanding_amount'), 2),
                'overdue' => round((float) $records->where('status', 'overdue')->sum('outstanding_amount'), 2),
            ],
            'counts' => [
                'records' => $records->count(),
                'overdue' => $records->where('status', 'overdue')->count(),
                'open' => $records->whereIn('status', ['open', 'partial', 'overdue'])->count(),
            ],
            'last_payment_at' => optional($records->where('status', 'paid')->sortByDesc('paid_at')->first())->paid_at?->toDateString(),
            'records' => $records,
        ];
    }

    public function dashboardSummary(): array
    {
        return $this->financialDashboardService->dashboard();
    }

    public function alerts(): array
    {
        $records = FinancialRecord::query()
            ->with('financeable')
            ->whereIn('status', ['overdue', 'partial', 'open'])
            ->get();

        $overdueInvoices = $records
            ->where('status', 'overdue')
            ->map(fn (FinancialRecord $record) => [
                'record_uid' => $record->uid,
                'entity_uid' => $record->financeable_uid,
                'external_reference' => $record->external_reference,
                'currency' => $record->currency,
                'outstanding_amount' => (float) $record->outstanding_amount,
                'due_at' => $record->due_at?->toDateString(),
            ])
            ->values();

        $customerRisk = $records
            ->groupBy(fn (FinancialRecord $record) => ($record->financeable_type ?? '') . '|' . ($record->financeable_id ?? ''))
            ->map(function ($group) {
                /** @var FinancialRecord $first */
                $first = $group->first();
                $outstanding = round((float) $group->sum('outstanding_amount'), 2);
                $overdue = round((float) $group->where('status', 'overdue')->sum('outstanding_amount'), 2);

                return [
                    'entity_uid' => $first?->financeable_uid,
                    'entity_type' => $first?->financeable_type,
                    'outstanding_amount' => $outstanding,
                    'overdue_amount' => $overdue,
                    'risk_level' => match (true) {
                        $overdue > 0 => 'high',
                        $outstanding > 0 => 'medium',
                        default => 'low',
                    },
                ];
            })
            ->filter(fn (array $row) => !empty($row['entity_uid']) && ($row['outstanding_amount'] > 0 || $row['overdue_amount'] > 0))
            ->sortByDesc('overdue_amount')
            ->values();

        return [
            'summary' => [
                'overdue_invoices_count' => $overdueInvoices->count(),
                'customers_at_risk_count' => $customerRisk->where('risk_level', 'high')->count(),
                'overdue_total' => round((float) $overdueInvoices->sum('outstanding_amount'), 2),
            ],
            'overdue_invoices' => $overdueInvoices,
            'customer_risk' => $customerRisk,
        ];
    }

    private function validateImport(array $data): array
    {
        $validator = Validator::make($data, [
            'entity_type' => 'required|string',
            'entity_uid' => 'required|uuid',
            'quotation_uid' => 'nullable|uuid',
            'record_type' => 'required|string|in:invoice_paid,collection_received,invoice_open',
            'source_system' => 'nullable|string|max:255',
            'external_reference' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'outstanding_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'issued_at' => 'nullable|date',
            'due_at' => 'nullable|date',
            'paid_at' => 'nullable|date',
            'status' => 'required|string|in:paid,partial,open,overdue',
            'meta' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function resolveEntity(?string $type, ?string $uid)
    {
        if (!$type || !$uid) {
            throw ValidationException::withMessages([
                'entity_uid' => ['Debes enviar entity_type y entity_uid'],
            ]);
        }

        $entity = find_entity_by_uid($type, $uid);

        if (!$entity) {
            throw ValidationException::withMessages([
                'entity_uid' => ['La entidad financiera no existe o no es visible'],
            ]);
        }

        return $entity;
    }

    private function resolveQuotation(?string $uid): ?Quotation
    {
        if (!$uid) {
            return null;
        }

        return Quotation::query()->where('uid', $uid)->firstOr(function () {
            throw ValidationException::withMessages([
                'quotation_uid' => ['La cotizacion no existe o no pertenece a este tenant'],
            ]);
        });
    }
}
