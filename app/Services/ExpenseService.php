<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\CostCenter;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function categories()
    {
        return ExpenseCategory::query()->orderBy('name')->get();
    }

    public function createCategory(array $data): ExpenseCategory
    {
        $validated = Validator::make($data, [
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        return ExpenseCategory::query()->create($validated);
    }

    public function updateCategory(string $uid, array $data): ExpenseCategory
    {
        $category = ExpenseCategory::query()->where('uid', $uid)->firstOrFail();
        $validated = Validator::make($data, [
            'name' => 'sometimes|string|max:255',
            'key' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        $category->update($validated);

        return $category->fresh();
    }

    public function deleteCategory(string $uid): void
    {
        ExpenseCategory::query()->where('uid', $uid)->firstOrFail()->delete();
    }

    public function suppliers()
    {
        return Supplier::query()->orderBy('name')->get();
    }

    public function costCenters()
    {
        return CostCenter::query()->orderBy('name')->get();
    }

    public function createSupplier(array $data): Supplier
    {
        $validated = Validator::make($data, [
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'document' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'payment_terms_days' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        return Supplier::query()->create($validated);
    }

    public function updateSupplier(string $uid, array $data): Supplier
    {
        $supplier = Supplier::query()->where('uid', $uid)->firstOrFail();
        $validated = Validator::make($data, [
            'name' => 'sometimes|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'document' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'payment_terms_days' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        $supplier->update($validated);

        return $supplier->fresh();
    }

    public function deleteSupplier(string $uid): void
    {
        Supplier::query()->where('uid', $uid)->firstOrFail()->delete();
    }

    public function createCostCenter(array $data): CostCenter
    {
        $validated = Validator::make($data, [
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        return CostCenter::query()->create($validated);
    }

    public function updateCostCenter(string $uid, array $data): CostCenter
    {
        $costCenter = CostCenter::query()->where('uid', $uid)->firstOrFail();
        $validated = Validator::make($data, [
            'name' => 'sometimes|string|max:255',
            'key' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ])->validate();

        $costCenter->update($validated);

        return $costCenter->fresh();
    }

    public function deleteCostCenter(string $uid): void
    {
        CostCenter::query()->where('uid', $uid)->firstOrFail()->delete();
    }

    public function index(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'category_uid' => 'nullable|uuid',
            'supplier_uid' => 'nullable|uuid',
            'cost_center_uid' => 'nullable|uuid',
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'status' => 'nullable|string|in:draft,submitted,approved,paid',
            'cost_center' => 'nullable|string|max:255',
        ])->validate();

        $query = Expense::query()->with(['category', 'supplier', 'owner', 'expenseable'])->latest('expense_date');

        if (!empty($validated['category_uid'])) {
            $query->where('expense_category_id', $this->resolveCategory($validated['category_uid'])->getKey());
        }

        if (!empty($validated['supplier_uid'])) {
            $query->where('supplier_id', $this->resolveSupplier($validated['supplier_uid'])->getKey());
        }

        if (!empty($validated['cost_center_uid'])) {
            $query->where('cost_center_id', $this->resolveCostCenter($validated['cost_center_uid'])->getKey());
        }

        if (!empty($validated['entity_type']) || !empty($validated['entity_uid'])) {
            $entity = $this->resolveEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);
            $query->where('expenseable_type', get_class($entity))
                ->where('expenseable_id', $entity->getKey());
        }

        foreach (['status', 'cost_center'] as $field) {
            if (!empty($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        return $query->get();
    }

    public function create(array $data): Expense
    {
        $validated = $this->validateExpense($data);
        $entity = $this->resolveOptionalEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);

        return Expense::query()->create([
            'expense_category_id' => $this->resolveCategory($validated['expense_category_uid'])->getKey(),
            'supplier_id' => !empty($validated['supplier_uid']) ? $this->resolveSupplier($validated['supplier_uid'])->getKey() : null,
            'owner_user_id' => !empty($validated['owner_user_uid']) ? $this->resolveUser($validated['owner_user_uid'])->getKey() : auth()->id(),
            'expenseable_type' => $entity ? get_class($entity) : null,
            'expenseable_id' => $entity?->getKey(),
            'cost_center_id' => !empty($validated['cost_center_uid']) ? $this->resolveCostCenter($validated['cost_center_uid'])->getKey() : null,
            'cost_center' => $validated['cost_center'] ?? null,
            'expense_number' => $validated['expense_number'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'COP',
            'expense_date' => $validated['expense_date'],
            'status' => $validated['status'] ?? 'draft',
            'meta' => $validated['meta'] ?? null,
        ])->fresh(['category', 'supplier', 'owner', 'expenseable']);
    }

    public function update(string $uid, array $data): Expense
    {
        $expense = Expense::query()->where('uid', $uid)->firstOrFail();
        $validated = $this->validateExpense($data, true);
        $payload = [];

        if (array_key_exists('expense_category_uid', $validated)) {
            $payload['expense_category_id'] = $this->resolveCategory($validated['expense_category_uid'])->getKey();
        }

        if (array_key_exists('supplier_uid', $validated)) {
            $payload['supplier_id'] = $validated['supplier_uid'] ? $this->resolveSupplier($validated['supplier_uid'])->getKey() : null;
        }

        if (array_key_exists('owner_user_uid', $validated)) {
            $payload['owner_user_id'] = $validated['owner_user_uid'] ? $this->resolveUser($validated['owner_user_uid'])->getKey() : null;
        }

        if (array_key_exists('cost_center_uid', $validated)) {
            $payload['cost_center_id'] = $validated['cost_center_uid'] ? $this->resolveCostCenter($validated['cost_center_uid'])->getKey() : null;
        }

        if (array_key_exists('entity_type', $validated) || array_key_exists('entity_uid', $validated)) {
            $entity = $this->resolveOptionalEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);
            $payload['expenseable_type'] = $entity ? get_class($entity) : null;
            $payload['expenseable_id'] = $entity?->getKey();
        }

        foreach (['cost_center', 'expense_number', 'title', 'description', 'amount', 'currency', 'expense_date', 'status', 'meta'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        $expense->update($payload);

        return $expense->fresh(['category', 'supplier', 'owner', 'expenseable']);
    }

    public function delete(string $uid): void
    {
        Expense::query()->where('uid', $uid)->firstOrFail()->delete();
    }

    public function report(array $filters = []): array
    {
        $validated = Validator::make($filters, [
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'cost_center' => 'nullable|string|max:255',
        ])->validate();

        $expenses = $this->index($validated);
        $income = collect();

        if (!empty($validated['entity_type']) || !empty($validated['entity_uid'])) {
            $entity = $this->resolveEntity($validated['entity_type'] ?? null, $validated['entity_uid'] ?? null);
            $income = \App\Models\FinancialRecord::query()
                ->where('financeable_type', get_class($entity))
                ->where('financeable_id', $entity->getKey())
                ->get();
        } else {
            $income = \App\Models\FinancialRecord::query()->get();
        }

        if (!empty($validated['cost_center'])) {
            $expenses = $expenses->where('cost_center', $validated['cost_center'])->values();
        }

        $expenseTotal = round((float) $expenses->sum('amount'), 2);
        $incomeTotal = round((float) $income->sum(function ($record) {
            return (float) $record->amount - (float) $record->outstanding_amount;
        }), 2);

        return [
            'summary' => [
                'income_total' => $incomeTotal,
                'expense_total' => $expenseTotal,
                'real_margin' => round($incomeTotal - $expenseTotal, 2),
            ],
            'expenses_by_category' => $expenses->groupBy(fn ($expense) => $expense->category?->name ?? 'Sin categoria')
                ->map(fn ($group, $category) => [
                    'category' => $category,
                    'count' => $group->count(),
                    'amount' => round((float) $group->sum('amount'), 2),
                ])->values(),
            'expenses' => $expenses->values(),
        ];
    }

    public function profitability(array $filters = []): array
    {
        $validated = Validator::make($filters, [
            'entity_type' => 'required|string',
            'entity_uid' => 'required|uuid',
            'cost_center' => 'nullable|string|max:255',
        ])->validate();

        $entity = $this->resolveEntity($validated['entity_type'], $validated['entity_uid']);
        $expenses = $this->index($validated);

        if (!empty($validated['cost_center'])) {
            $expenses = $expenses->where('cost_center', $validated['cost_center'])->values();
        }

        $income = \App\Models\FinancialRecord::query()
            ->where('financeable_type', get_class($entity))
            ->where('financeable_id', $entity->getKey())
            ->get();

        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'costCenter', 'items', 'payments'])
            ->where('source_type', get_class($entity))
            ->where('source_uid', $entity->uid)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->get();

        $expenseTotal = round((float) $expenses->sum('amount'), 2);
        $incomeTotal = round((float) $income->sum(fn ($record) => (float) $record->amount - (float) $record->outstanding_amount), 2);
        $purchaseTotal = round((float) $purchaseOrders->sum(fn (PurchaseOrder $order) => $order->total), 2);
        $purchasePaidTotal = round((float) $purchaseOrders->sum(fn (PurchaseOrder $order) => (float) $order->paid_total), 2);
        $purchaseOutstandingTotal = round((float) $purchaseOrders->sum(fn (PurchaseOrder $order) => $order->outstanding_total), 2);
        $operationalCostTotal = round($expenseTotal + $purchaseTotal, 2);

        return [
            'summary' => [
                'entity_type' => get_class($entity),
                'entity_uid' => $entity->uid,
                'income_total' => $incomeTotal,
                'expense_total' => $expenseTotal,
                'purchase_total' => $purchaseTotal,
                'purchase_paid_total' => $purchasePaidTotal,
                'purchase_outstanding_total' => $purchaseOutstandingTotal,
                'operational_cost_total' => $operationalCostTotal,
                'real_margin' => round($incomeTotal - $operationalCostTotal, 2),
            ],
            'expenses' => $expenses->values(),
            'purchase_orders' => $purchaseOrders->values(),
            'purchases_by_status' => $purchaseOrders->groupBy('status')
                ->map(fn ($group, $status) => [
                    'status' => $status,
                    'count' => $group->count(),
                    'total' => round((float) $group->sum(fn (PurchaseOrder $order) => $order->total), 2),
                ])->values(),
        ];
    }

    private function validateExpense(array $data, bool $partial = false): array
    {
        return Validator::make($data, [
            'expense_category_uid' => [$partial ? 'sometimes' : 'required', 'uuid'],
            'supplier_uid' => 'nullable|uuid',
            'owner_user_uid' => 'nullable|uuid',
            'cost_center_uid' => 'nullable|uuid',
            'entity_type' => 'nullable|string',
            'entity_uid' => 'nullable|uuid',
            'cost_center' => 'nullable|string|max:255',
            'expense_number' => 'nullable|string|max:255',
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'amount' => [$partial ? 'sometimes' : 'required', 'numeric', 'min:0.01'],
            'currency' => 'sometimes|string|max:10',
            'expense_date' => [$partial ? 'sometimes' : 'required', 'date'],
            'status' => 'sometimes|string|in:draft,submitted,approved,paid',
            'meta' => 'nullable|array',
        ])->validate();
    }

    private function resolveCategory(string $uid): ExpenseCategory
    {
        return ExpenseCategory::query()->where('uid', $uid)->firstOrFail();
    }

    private function resolveSupplier(string $uid): Supplier
    {
        return Supplier::query()->where('uid', $uid)->firstOrFail();
    }

    private function resolveCostCenter(string $uid): CostCenter
    {
        return CostCenter::query()->where('uid', $uid)->firstOrFail();
    }

    private function resolveUser(string $uid): User
    {
        return User::query()->where('uid', $uid)->firstOrFail();
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
                'entity_uid' => ['La entidad asociada no existe o no es visible'],
            ]);
        }

        return $entity;
    }

    private function resolveOptionalEntity(?string $type, ?string $uid)
    {
        if (!$type && !$uid) {
            return null;
        }

        return $this->resolveEntity($type, $uid);
    }
}
