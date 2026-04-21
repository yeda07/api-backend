<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InventoryReservation;
use App\Models\Quotation;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly CreditService $creditService,
        private readonly FinancialOperationsService $financialOperationsService,
        private readonly DocumentValidationService $documentValidationService
    ) {
    }

    public function list(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'status' => 'nullable|string|in:draft,issued,partial,paid,overdue',
        ])->validate();

        $query = Invoice::query()->with(['quotation', 'invoiceable', 'payments'])->latest();

        if (!empty($validated['entity_type']) || !empty($validated['entity_uid'])) {
            $entity = $this->resolveEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);
            $query->where('invoiceable_type', get_class($entity))
                ->where('invoiceable_id', $entity->getKey());
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return $query->get();
    }

    public function createFromQuotation(array $data): Invoice
    {
        $validated = Validator::make($data, [
            'quotation_uid' => 'required|uuid',
            'invoice_number' => 'required|string|max:255',
            'currency' => 'required|string|max:10',
            'exchange_rate' => 'sometimes|numeric|min:0.000001',
            'due_date' => 'nullable|date',
            'status' => 'sometimes|string|in:draft,issued',
        ])->validate();

        return DB::transaction(function () use ($validated) {
            $quotation = Quotation::query()->with(['items.product', 'items.warehouse', 'quoteable'])->where('uid', $validated['quotation_uid'])->firstOrFail();
            $entity = $quotation->quoteable;

            if (!$entity) {
                throw ValidationException::withMessages([
                    'quotation_uid' => ['La cotizacion no tiene cliente asociado'],
                ]);
            }

            $this->creditService->ensureCanOperate($entity);
            if ($entity instanceof Account) {
                $this->documentValidationService->ensureReadyForAccount($entity);
            }

            foreach ($quotation->items as $item) {
                if ($item->quantity <= 0) {
                    continue;
                }

                if (!$item->product_id || !$item->warehouse_id) {
                    throw ValidationException::withMessages([
                        'quotation_uid' => ['Todos los items deben tener producto y bodega para facturar'],
                    ]);
                }

                if ($item->reserved_quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'quotation_uid' => ['No puedes facturar sin stock reservado suficiente para todos los items'],
                    ]);
                }
            }

            $exchangeRate = (float) ($validated['exchange_rate'] ?? $quotation->exchange_rate ?? 1);
            $invoiceTotal = round((float) $quotation->total * $exchangeRate, 2);
            $invoiceSubtotal = round((float) $quotation->subtotal * $exchangeRate, 2);
            $invoiceDiscount = round((float) $quotation->discount_total * $exchangeRate, 2);

            $invoice = Invoice::query()->create([
                'quotation_id' => $quotation->getKey(),
                'invoiceable_type' => get_class($entity),
                'invoiceable_id' => $entity->getKey(),
                'invoice_number' => $validated['invoice_number'],
                'status' => $validated['status'] ?? 'issued',
                'quote_currency' => $quotation->currency,
                'exchange_rate' => $exchangeRate,
                'currency' => $validated['currency'],
                'subtotal' => $invoiceSubtotal,
                'discount_total' => $invoiceDiscount,
                'total' => $invoiceTotal,
                'paid_total' => 0,
                'outstanding_total' => $invoiceTotal,
                'issued_at' => now()->toDateString(),
                'due_date' => $validated['due_date'] ?? now()->addDays(30)->toDateString(),
                'meta' => [
                    'quotation_total' => (float) $quotation->total,
                ],
            ]);

            foreach ($quotation->items as $item) {
                $reservations = InventoryReservation::query()
                    ->where('source_type', 'quotation_item')
                    ->where('source_uid', $item->uid)
                    ->where('status', 'active')
                    ->get();

                foreach ($reservations as $reservation) {
                    $this->inventoryService->consumeReservation($reservation->uid, [
                        'reference_type' => 'invoice',
                        'reference_uid' => $invoice->uid,
                        'comment' => 'Consumo por facturacion',
                    ]);
                }
            }

            $recordStatus = $invoice->due_date && now()->gt($invoice->due_date) ? 'overdue' : 'open';
            if ($invoice->status === 'draft') {
                $recordStatus = 'open';
            }

            $this->financialOperationsService->importRecord([
                'entity_type' => get_class($entity),
                'entity_uid' => $entity->uid,
                'quotation_uid' => $quotation->uid,
                'record_type' => 'invoice_open',
                'source_system' => 'internal_finance',
                'external_reference' => $invoice->invoice_number,
                'amount' => $invoice->total,
                'outstanding_amount' => $invoice->outstanding_total,
                'currency' => $invoice->currency,
                'issued_at' => $invoice->issued_at?->toDateString(),
                'due_at' => $invoice->due_date?->toDateString(),
                'status' => $recordStatus,
                'meta' => [
                    'invoice_uid' => $invoice->uid,
                ],
            ]);

            return $invoice->fresh(['quotation', 'invoiceable', 'payments']);
        });
    }

    public function syncOverdue(): array
    {
        return DB::transaction(function () {
            $invoices = Invoice::query()
                ->whereIn('status', ['issued', 'partial'])
                ->whereDate('due_date', '<', now()->toDateString())
                ->get();

            $updated = 0;

            foreach ($invoices as $invoice) {
                $invoice->update(['status' => 'overdue']);

                $entity = $invoice->invoiceable;
                if ($entity) {
                    $this->financialOperationsService->importRecord([
                        'entity_type' => get_class($entity),
                        'entity_uid' => $entity->uid,
                        'quotation_uid' => $invoice->quotation?->uid,
                        'record_type' => 'invoice_open',
                        'source_system' => 'internal_finance',
                        'external_reference' => $invoice->invoice_number,
                        'amount' => $invoice->total,
                        'outstanding_amount' => $invoice->outstanding_total,
                        'currency' => $invoice->currency,
                        'issued_at' => $invoice->issued_at?->toDateString(),
                        'due_at' => $invoice->due_date?->toDateString(),
                        'status' => 'overdue',
                        'meta' => [
                            'invoice_uid' => $invoice->uid,
                        ],
                    ]);
                }

                $updated++;
            }

            return [
                'updated_invoices' => $updated,
            ];
        });
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
                'entity_uid' => ['La entidad no existe o no es visible'],
            ]);
        }

        return $entity;
    }
}
