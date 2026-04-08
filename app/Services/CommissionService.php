<?php

namespace App\Services;

use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\FinancialRecord;
use App\Models\InventoryProduct;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommissionService
{
    public function rules()
    {
        return CommissionRule::query()->with('product')->orderBy('name')->get();
    }

    public function createRule(array $data): CommissionRule
    {
        $validated = $this->validateRule($data);

        return CommissionRule::query()->create([
            'name' => $validated['name'],
            'product_id' => $this->resolveProductId($validated['product_uid'] ?? null),
            'customer_type' => $validated['customer_type'] ?? null,
            'rate_percent' => $validated['rate_percent'],
            'is_active' => $validated['is_active'] ?? true,
        ])->fresh('product');
    }

    public function updateRule(string $uid, array $data): CommissionRule
    {
        $rule = CommissionRule::query()->where('uid', $uid)->firstOrFail();
        $validated = $this->validateRule($data, true);
        $payload = [];

        foreach (['name', 'customer_type', 'rate_percent', 'is_active'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (array_key_exists('product_uid', $validated)) {
            $payload['product_id'] = $this->resolveProductId($validated['product_uid']);
        }

        $rule->update($payload);

        return $rule->fresh('product');
    }

    public function deleteRule(string $uid): void
    {
        CommissionRule::query()->where('uid', $uid)->firstOrFail()->delete();
    }

    public function recordFinancialEvent(array $data): array
    {
        $validated = $this->validateFinancialRecord($data);

        return DB::transaction(function () use ($validated) {
            $quotation = $this->resolveQuotation($validated['quotation_uid'] ?? null);

            $record = FinancialRecord::query()->create([
                'owner_user_id' => $quotation?->owner_user_id ?? auth()->id(),
                'quotation_id' => $quotation?->getKey(),
                'record_type' => $validated['record_type'],
                'external_reference' => $validated['external_reference'] ?? null,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'] ?? $quotation?->currency,
                'paid_at' => $validated['paid_at'],
                'status' => $validated['status'] ?? 'paid',
                'meta' => $validated['meta'] ?? null,
            ])->fresh('quotation');

            $entries = $quotation
                ? $this->generateEntriesForPayment($quotation, $record)
                : collect();

            return [
                'financial_record' => $record,
                'commission_entries' => $entries->values(),
                'summary' => [
                    'entries_count' => $entries->count(),
                    'commission_total' => round((float) $entries->sum(fn ($entry) => (float) $entry->commission_amount), 2),
                ],
            ];
        });
    }

    public function entries(?string $userUid = null)
    {
        $query = CommissionEntry::query()
            ->with(['user', 'rule.product', 'quotation', 'quotationItem', 'financialRecord'])
            ->latest('earned_at');

        if ($userUid) {
            $userId = \App\Models\User::query()->where('uid', $userUid)->value('id');
            $query->where('user_id', $userId ?: 0);
        }

        return $query->get();
    }

    public function payEntry(string $uid, ?string $paidAt = null): CommissionEntry
    {
        $entry = CommissionEntry::query()->where('uid', $uid)->firstOrFail();
        $entry->update([
            'status' => 'paid',
            'paid_at' => $paidAt ?: now()->toDateString(),
        ]);

        return $entry->fresh(['user', 'rule.product', 'quotation', 'quotationItem', 'financialRecord']);
    }

    public function mySummary(): array
    {
        $entries = CommissionEntry::query()
            ->where('user_id', auth()->id())
            ->get();

        return [
            'user_uid' => auth()->user()?->uid,
            'totals' => [
                'earned' => round((float) $entries->where('status', 'earned')->sum('commission_amount'), 2),
                'paid' => round((float) $entries->where('status', 'paid')->sum('commission_amount'), 2),
                'all_time' => round((float) $entries->sum('commission_amount'), 2),
            ],
            'counts' => [
                'earned' => $entries->where('status', 'earned')->count(),
                'paid' => $entries->where('status', 'paid')->count(),
            ],
            'entries' => CommissionEntry::query()
                ->with(['rule.product', 'quotation', 'quotationItem', 'financialRecord'])
                ->where('user_id', auth()->id())
                ->latest('earned_at')
                ->get(),
        ];
    }

    private function generateEntriesForPayment(Quotation $quotation, FinancialRecord $record): Collection
    {
        $quotation->loadMissing('items.product');

        if ($quotation->items->isEmpty() || $quotation->total <= 0) {
            return collect();
        }

        $sellerId = $quotation->owner_user_id ?: $quotation->created_by_user_id;
        $customerType = $this->resolveCustomerType($quotation);
        $paymentRatio = min(1, ((float) $record->amount / max(0.01, (float) $quotation->total)));

        return $quotation->items->map(function (QuotationItem $item) use ($quotation, $record, $sellerId, $customerType, $paymentRatio) {
            $baseAmount = round(((float) $item->line_total) * $paymentRatio, 2);
            $rule = $this->resolveRule($item, $customerType);
            $ratePercent = (float) ($rule?->rate_percent ?? 0);
            $commissionAmount = round($baseAmount * ($ratePercent / 100), 2);

            return CommissionEntry::query()->create([
                'user_id' => $sellerId,
                'rule_id' => $rule?->getKey(),
                'quotation_id' => $quotation->getKey(),
                'quotation_item_id' => $item->getKey(),
                'financial_record_id' => $record->getKey(),
                'customer_type' => $customerType,
                'base_amount' => $baseAmount,
                'rate_percent' => $ratePercent,
                'commission_amount' => $commissionAmount,
                'status' => 'earned',
                'earned_at' => $record->paid_at,
                'meta' => [
                    'payment_ratio' => $paymentRatio,
                    'quotation_total' => (float) $quotation->total,
                    'quotation_item_total' => (float) $item->line_total,
                ],
            ])->fresh(['user', 'rule.product', 'quotation', 'quotationItem', 'financialRecord']);
        });
    }

    private function resolveRule(QuotationItem $item, string $customerType): ?CommissionRule
    {
        return CommissionRule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($item) {
                $query->whereNull('product_id')
                    ->orWhere('product_id', $item->product_id);
            })
            ->where(function ($query) use ($customerType) {
                $query->whereNull('customer_type')
                    ->orWhere('customer_type', $customerType);
            })
            ->orderByRaw('CASE WHEN product_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN customer_type IS NULL THEN 1 ELSE 0 END')
            ->first();
    }

    private function resolveCustomerType(Quotation $quotation): string
    {
        if ($quotation->quoteable_type === \App\Models\Account::class) {
            return 'B2B';
        }

        if ($quotation->quoteable_type === \App\Models\Contact::class) {
            return 'B2C';
        }

        if ($quotation->quoteable_type === \App\Models\CrmEntity::class) {
            return $quotation->quoteable?->type ?? 'B2B';
        }

        return $quotation->priceBook?->channel ?? 'B2B';
    }

    private function validateRule(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'product_uid' => 'nullable|uuid',
            'customer_type' => 'nullable|string|in:B2B,B2C,B2G',
            'rate_percent' => [$partial ? 'sometimes' : 'required', 'numeric', 'min:0', 'max:100'],
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function validateFinancialRecord(array $data): array
    {
        $validator = Validator::make($data, [
            'quotation_uid' => 'nullable|uuid',
            'record_type' => 'required|string|in:invoice_paid,collection_received',
            'external_reference' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'paid_at' => 'required|date',
            'status' => 'sometimes|string|in:paid',
            'meta' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function resolveProductId(?string $uid): ?int
    {
        if (!$uid) {
            return null;
        }

        $productId = InventoryProduct::query()->where('uid', $uid)->value('id');

        if (!$productId) {
            throw ValidationException::withMessages([
                'product_uid' => ['El producto no existe o no pertenece a este tenant'],
            ]);
        }

        return $productId;
    }

    private function resolveQuotation(?string $uid): ?Quotation
    {
        if (!$uid) {
            return null;
        }

        return Quotation::query()->with(['items.product', 'priceBook', 'quoteable'])->where('uid', $uid)->firstOr(function () {
            throw ValidationException::withMessages([
                'quotation_uid' => ['La cotizacion no existe o no pertenece a este tenant'],
            ]);
        });
    }
}
