<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly FinancialOperationsService $financialOperationsService)
    {
    }

    public function list(?string $invoiceUid = null)
    {
        $query = Payment::query()->with('invoice')->latest('payment_date');

        if ($invoiceUid) {
            $invoiceId = Invoice::query()->where('uid', $invoiceUid)->value('id');
            $query->where('invoice_id', $invoiceId ?: 0);
        }

        return $query->get();
    }

    public function register(array $data): array
    {
        $validated = Validator::make($data, [
            'invoice_uid' => 'required|uuid',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method' => 'required|string|max:255',
            'external_reference' => 'nullable|string|max:255',
            'meta' => 'nullable|array',
        ])->validate();

        return DB::transaction(function () use ($validated) {
            $invoice = Invoice::query()->with(['invoiceable', 'quotation'])->where('uid', $validated['invoice_uid'])->firstOrFail();

            if ((float) $validated['amount'] > (float) $invoice->outstanding_total) {
                throw ValidationException::withMessages([
                    'amount' => ['El pago no puede ser mayor al saldo pendiente'],
                ]);
            }

            $payment = Payment::query()->create([
                'invoice_id' => $invoice->getKey(),
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'method' => $validated['method'],
                'external_reference' => $validated['external_reference'] ?? null,
                'meta' => $validated['meta'] ?? null,
            ]);

            $paidTotal = round((float) $invoice->paid_total + (float) $validated['amount'], 2);
            $outstanding = round(max(0, (float) $invoice->total - $paidTotal), 2);
            $status = match (true) {
                $outstanding <= 0 => 'paid',
                $paidTotal > 0 => 'partial',
                default => $invoice->status,
            };

            $invoice->update([
                'paid_total' => $paidTotal,
                'outstanding_total' => $outstanding,
                'status' => $status,
            ]);

            $entity = $invoice->invoiceable;

            $this->financialOperationsService->importRecord([
                'entity_type' => get_class($entity),
                'entity_uid' => $entity->uid,
                'quotation_uid' => $invoice->quotation?->uid,
                'record_type' => 'collection_received',
                'source_system' => 'internal_finance',
                'external_reference' => $payment->external_reference ?: $payment->uid,
                'amount' => $payment->amount,
                'outstanding_amount' => 0,
                'currency' => $invoice->currency,
                'paid_at' => $payment->payment_date->toDateString(),
                'status' => 'paid',
                'meta' => [
                    'invoice_uid' => $invoice->uid,
                    'payment_uid' => $payment->uid,
                ],
            ]);

            $this->financialOperationsService->importRecord([
                'entity_type' => get_class($entity),
                'entity_uid' => $entity->uid,
                'quotation_uid' => $invoice->quotation?->uid,
                'record_type' => 'invoice_open',
                'source_system' => 'internal_finance',
                'external_reference' => $invoice->invoice_number,
                'amount' => $invoice->total,
                'outstanding_amount' => $outstanding,
                'currency' => $invoice->currency,
                'issued_at' => $invoice->issued_at?->toDateString(),
                'due_at' => $invoice->due_date?->toDateString(),
                'status' => $status === 'paid' ? 'paid' : $status,
                'paid_at' => $status === 'paid' ? $payment->payment_date->toDateString() : null,
                'meta' => [
                    'invoice_uid' => $invoice->uid,
                ],
            ]);

            return [
                'payment' => $payment->fresh('invoice'),
                'invoice' => $invoice->fresh(['quotation', 'invoiceable', 'payments']),
            ];
        });
    }
}
