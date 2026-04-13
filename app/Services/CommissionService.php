<?php

namespace App\Services;

use App\Models\CommissionAssignment;
use App\Models\CommissionEntry;
use App\Models\CommissionPlan;
use App\Models\CommissionRule;
use App\Models\CommissionRun;
use App\Models\CommissionRunItem;
use App\Models\CommissionTarget;
use App\Models\FinancialRecord;
use App\Models\InventoryProduct;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommissionService
{
    public function __construct(private readonly FinancialOperationsService $financialOperationsService)
    {
    }

    public function plans()
    {
        return CommissionPlan::query()->with('roles')->orderBy('name')->get();
    }

    public function createPlan(array $data): CommissionPlan
    {
        $validated = $this->validatePlan($data);

        return DB::transaction(function () use ($validated) {
            $plan = CommissionPlan::query()->create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'base_percent' => $validated['base_percent'],
                'tiers_json' => $validated['tiers_json'] ?? [],
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
                'active' => $validated['active'] ?? true,
            ]);

            if (array_key_exists('role_uids', $validated)) {
                $plan->roles()->sync($this->resolveRoleIds($validated['role_uids'] ?? []));
            }

            return $plan->fresh('roles');
        });
    }

    public function updatePlan(string $uid, array $data): CommissionPlan
    {
        $plan = $this->resolvePlan($uid);
        $validated = $this->validatePlan($data, true);
        $payload = [];

        foreach (['name', 'type', 'base_percent', 'tiers_json', 'starts_at', 'ends_at', 'active'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        return DB::transaction(function () use ($plan, $payload, $validated) {
            if (!empty($payload)) {
                $plan->update($payload);
            }

            if (array_key_exists('role_uids', $validated)) {
                $plan->roles()->sync($this->resolveRoleIds($validated['role_uids'] ?? []));
            }

            return $plan->fresh('roles');
        });
    }

    public function assignments(array $filters = [])
    {
        $query = CommissionAssignment::query()->with(['user.roles', 'commissionPlan.roles'])->latest('starts_at');

        if (!empty($filters['user_uid'])) {
            $query->where('user_id', $this->resolveUserId($filters['user_uid']));
        }

        if (!empty($filters['manager_uid'])) {
            $query->whereIn('user_id', $this->resolveTeamUserIds($filters['manager_uid']));
        }

        if (array_key_exists('active', $filters) && $filters['active'] !== null && $filters['active'] !== '') {
            $query->where('active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->get();
    }

    public function createAssignment(array $data): CommissionAssignment
    {
        $validated = $this->validateAssignment($data);
        $user = $this->resolveUser($validated['user_uid']);
        $plan = $this->resolvePlan($validated['commission_plan_uid']);

        $this->ensureUserMatchesPlanRoles($user, $plan);
        $this->validateAssignmentOverlap($user->getKey(), $validated['starts_at'], $validated['ends_at'] ?? null);

        return CommissionAssignment::query()->create([
            'user_id' => $user->getKey(),
            'commission_plan_id' => $plan->getKey(),
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'active' => $validated['active'] ?? true,
        ])->fresh(['user.roles', 'commissionPlan.roles']);
    }

    public function updateAssignment(string $uid, array $data): CommissionAssignment
    {
        $assignment = CommissionAssignment::query()->where('uid', $uid)->firstOrFail();
        $validated = $this->validateAssignment($data, true);
        $payload = [];
        $user = $assignment->user;
        $plan = $assignment->commissionPlan;

        if (array_key_exists('user_uid', $validated)) {
            $user = $this->resolveUser($validated['user_uid']);
            $payload['user_id'] = $user->getKey();
        }

        if (array_key_exists('commission_plan_uid', $validated)) {
            $plan = $this->resolvePlan($validated['commission_plan_uid']);
            $payload['commission_plan_id'] = $plan->getKey();
        }

        foreach (['starts_at', 'ends_at', 'active'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        $startsAt = $payload['starts_at'] ?? $assignment->starts_at?->toDateString();
        $endsAt = array_key_exists('ends_at', $payload) ? $payload['ends_at'] : $assignment->ends_at?->toDateString();
        $this->ensureUserMatchesPlanRoles($user, $plan);
        $this->validateAssignmentOverlap($user->getKey(), $startsAt, $endsAt, $assignment->getKey());

        $assignment->update($payload);

        return $assignment->fresh(['user.roles', 'commissionPlan.roles']);
    }

    public function targets(?string $userUid = null)
    {
        $query = CommissionTarget::query()->with('user')->orderByDesc('period');

        if ($userUid) {
            $query->where('user_id', $this->resolveUserId($userUid));
        }

        return $query->get();
    }

    public function upsertTarget(array $data): CommissionTarget
    {
        $validated = $this->validateTarget($data);
        $userId = $this->resolveUserId($validated['user_uid']);

        CommissionTarget::query()->updateOrCreate(
            ['tenant_id' => auth()->user()?->tenant_id, 'user_id' => $userId, 'period' => $validated['period']],
            ['target_amount' => $validated['target_amount']]
        );

        return CommissionTarget::query()
            ->where('tenant_id', auth()->user()?->tenant_id)
            ->where('user_id', $userId)
            ->where('period', $validated['period'])
            ->firstOrFail();
    }

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
            $entityType = $quotation?->quoteable_type;
            $entityUid = $quotation?->quoteable?->uid;

            $record = $this->financialOperationsService->importRecord([
                'entity_type' => $entityType,
                'entity_uid' => $entityUid,
                'quotation_uid' => $validated['quotation_uid'] ?? null,
                'record_type' => $validated['record_type'],
                'source_system' => $validated['source_system'] ?? 'manual',
                'external_reference' => $validated['external_reference'] ?? null,
                'amount' => $validated['amount'],
                'outstanding_amount' => $validated['outstanding_amount'] ?? 0,
                'currency' => $validated['currency'] ?? $quotation?->currency,
                'issued_at' => $validated['issued_at'] ?? null,
                'due_at' => $validated['due_at'] ?? null,
                'paid_at' => $validated['paid_at'],
                'status' => $validated['status'] ?? 'paid',
                'meta' => $validated['meta'] ?? null,
            ]);

            $entries = $quotation ? $this->generateEntriesForPayment($quotation, $record) : collect();

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
            ->with(['user', 'rule.product', 'quotation', 'quotationItem', 'financialRecord', 'commissionRun'])
            ->latest('earned_at');

        if ($userUid) {
            $query->where('user_id', $this->resolveUserId($userUid));
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

        return $entry->fresh(['user', 'rule.product', 'quotation', 'quotationItem', 'financialRecord', 'commissionRun']);
    }

    public function mySummary(): array
    {
        $entries = CommissionEntry::query()->where('user_id', auth()->id())->get();

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
                ->with(['rule.product', 'quotation', 'quotationItem', 'financialRecord', 'commissionRun'])
                ->where('user_id', auth()->id())
                ->latest('earned_at')
                ->get(),
        ];
    }

    public function dashboard(string $userUid): array
    {
        $user = $this->resolveUser($userUid);
        $period = now()->format('Y-m');
        $currentStart = now()->startOfMonth()->toDateString();
        $currentEnd = now()->endOfMonth()->toDateString();
        $entries = CommissionEntry::query()
            ->where('user_id', $user->getKey())
            ->whereBetween('earned_at', [$currentStart, $currentEnd])
            ->get();
        $runs = CommissionRun::query()
            ->where('user_id', $user->getKey())
            ->whereDate('period_start', '<=', $currentEnd)
            ->whereDate('period_end', '>=', $currentStart)
            ->get();
        $target = $this->resolveTargetForPeriod($user->getKey(), $period);
        $salesAchieved = round((float) $entries->sum('base_amount'), 2);
        $projected = round((float) $entries->sum('commission_amount'), 2);
        $liquidated = round((float) $runs->whereIn('status', ['approved', 'paid'])->sum('commission_amount'), 2);
        $targetAmount = round((float) ($target?->target_amount ?? 0), 2);

        return [
            'user_uid' => $user->uid,
            'period' => $period,
            'monthly_target' => $targetAmount,
            'sales_achieved' => $salesAchieved,
            'projected_commission' => $projected,
            'liquidated_commission' => $liquidated,
            'progress_percent' => $targetAmount > 0 ? round(min(100, ($salesAchieved / $targetAmount) * 100), 2) : 0,
            'active_assignment' => $this->resolveActiveAssignment($user->getKey(), now())?->load('commissionPlan.roles'),
            'recent_entries' => CommissionEntry::query()
                ->with(['rule.product', 'quotation', 'financialRecord'])
                ->where('user_id', $user->getKey())
                ->latest('earned_at')
                ->limit(10)
                ->get(),
        ];
    }

    public function simulate(array $data): array
    {
        $validated = $this->validateSimulation($data);
        $user = $this->resolveUser($validated['user_uid']);
        $period = $validated['period'] ?? now()->format('Y-m');
        $calculation = $this->calculateForUser(
            $user->getKey(),
            (float) $validated['sale_amount'],
            (float) ($validated['margin_amount'] ?? 0),
            Carbon::createFromFormat('Y-m', $period)->endOfMonth()
        );

        return [
            'user_uid' => $user->uid,
            'period' => $period,
            'sale_amount' => round((float) $validated['sale_amount'], 2),
            'margin_amount' => round((float) ($validated['margin_amount'] ?? 0), 2),
            'plan' => $calculation['plan'],
            'target' => $calculation['target'],
            'basis' => $calculation['basis'],
            'applied_percent' => $calculation['applied_percent'],
            'commission_amount' => $calculation['commission_amount'],
            'tier_breakdown' => $calculation['tier_breakdown'],
        ];
    }

    public function runs(array $filters = [])
    {
        $query = CommissionRun::query()->with(['user', 'commissionPlan.roles', 'items'])->latest('period_start');

        if (!empty($filters['user_uid'])) {
            $query->where('user_id', $this->resolveUserId($filters['user_uid']));
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    public function createRun(array $data): CommissionRun
    {
        $validated = $this->validateRun($data);
        $user = $this->resolveUser($validated['user_uid']);
        $assignment = $this->resolveActiveAssignment($user->getKey(), Carbon::parse($validated['period_end']));

        return DB::transaction(function () use ($validated, $user, $assignment) {
            $entries = CommissionEntry::query()
                ->where('user_id', $user->getKey())
                ->whereNull('commission_run_id')
                ->whereBetween('earned_at', [$validated['period_start'], $validated['period_end']])
                ->orderBy('earned_at')
                ->get();

            $run = CommissionRun::query()->create([
                'user_id' => $user->getKey(),
                'commission_plan_id' => $assignment?->commission_plan_id,
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'sales_amount' => round((float) $entries->sum('base_amount'), 2),
                'margin_amount' => round((float) $entries->sum(fn (CommissionEntry $entry) => (float) data_get($entry->meta, 'margin_amount', 0)), 2),
                'commission_amount' => round((float) $entries->sum('commission_amount'), 2),
                'status' => 'pending',
                'meta' => ['entries_count' => $entries->count()],
            ]);

            foreach ($entries as $entry) {
                CommissionRunItem::query()->create([
                    'tenant_id' => $run->tenant_id,
                    'commission_run_id' => $run->getKey(),
                    'commission_entry_id' => $entry->getKey(),
                    'source_type' => 'commission_entry',
                    'source_uid' => $entry->uid,
                    'base_amount' => $entry->base_amount,
                    'applied_percent' => $entry->rate_percent,
                    'commission_amount' => $entry->commission_amount,
                    'rule_snapshot_json' => $entry->meta,
                ]);
            }

            if ($entries->isNotEmpty()) {
                CommissionEntry::query()->whereIn('id', $entries->pluck('id'))->update(['commission_run_id' => $run->getKey()]);
            }

            return $run->fresh(['user', 'commissionPlan.roles', 'items']);
        });
    }

    public function approveRun(string $uid): CommissionRun
    {
        $run = CommissionRun::query()->where('uid', $uid)->firstOrFail();
        $run->update([
            'status' => 'approved',
            'approved_at' => now()->toDateString(),
        ]);

        return $run->fresh(['user', 'commissionPlan.roles', 'items']);
    }

    public function payRun(string $uid, ?string $paidAt = null): CommissionRun
    {
        $run = CommissionRun::query()->where('uid', $uid)->firstOrFail();
        $paymentDate = $paidAt ?: now()->toDateString();

        DB::transaction(function () use ($run, $paymentDate) {
            $run->update([
                'status' => 'paid',
                'paid_at' => $paymentDate,
            ]);

            CommissionEntry::query()
                ->where('commission_run_id', $run->getKey())
                ->update([
                    'status' => 'paid',
                    'paid_at' => $paymentDate,
                ]);
        });

        return $run->fresh(['user', 'commissionPlan.roles', 'items']);
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
        $paidAt = Carbon::parse($record->paid_at ?? now());

        return $quotation->items->map(function (QuotationItem $item) use ($quotation, $record, $sellerId, $customerType, $paymentRatio, $paidAt) {
            $baseAmount = round(((float) $item->line_total) * $paymentRatio, 2);
            $marginAmount = round((((float) $item->margin_amount) * (int) $item->quantity) * $paymentRatio, 2);
            $planCalculation = $this->calculateForUser($sellerId, $baseAmount, $marginAmount, $paidAt);
            $rule = $this->resolveRule($item, $customerType);
            $ratePercent = $planCalculation['applied_percent'];
            $commissionAmount = $planCalculation['commission_amount'];

            if ($ratePercent <= 0 && !$planCalculation['plan']) {
                $ratePercent = (float) ($rule?->rate_percent ?? 0);
                $commissionAmount = round($baseAmount * ($ratePercent / 100), 2);
            }

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
                    'plan' => $planCalculation['plan'],
                    'target' => $planCalculation['target'],
                    'tier_breakdown' => $planCalculation['tier_breakdown'],
                    'basis' => $planCalculation['basis'],
                    'margin_amount' => $marginAmount,
                    'payment_ratio' => $paymentRatio,
                    'quotation_total' => (float) $quotation->total,
                    'quotation_item_total' => (float) $item->line_total,
                ],
            ])->fresh(['user', 'rule.product', 'quotation', 'quotationItem', 'financialRecord', 'commissionRun']);
        });
    }

    private function resolveRule(QuotationItem $item, string $customerType): ?CommissionRule
    {
        return CommissionRule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($item) {
                $query->whereNull('product_id')->orWhere('product_id', $item->product_id);
            })
            ->where(function ($query) use ($customerType) {
                $query->whereNull('customer_type')->orWhere('customer_type', $customerType);
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

    private function validatePlan(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'type' => [$partial ? 'sometimes' : 'required', 'string', 'in:sale,margin,target'],
            'base_percent' => [$partial ? 'sometimes' : 'required', 'numeric', 'min:0', 'max:100'],
            'tiers_json' => 'sometimes|array',
            'tiers_json.*.threshold' => 'required_with:tiers_json|numeric|min:0',
            'tiers_json.*.percent' => 'required_with:tiers_json|numeric|min:0|max:100',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'active' => 'sometimes|boolean',
            'role_uids' => 'sometimes|array',
            'role_uids.*' => 'uuid',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if (!empty($validated['tiers_json'])) {
            usort($validated['tiers_json'], fn ($a, $b) => $a['threshold'] <=> $b['threshold']);
        }

        return $validated;
    }

    private function validateAssignment(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'user_uid' => [$partial ? 'sometimes' : 'required', 'uuid'],
            'commission_plan_uid' => [$partial ? 'sometimes' : 'required', 'uuid'],
            'starts_at' => [$partial ? 'sometimes' : 'required', 'date'],
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function validateTarget(array $data): array
    {
        $validator = Validator::make($data, [
            'user_uid' => 'required|uuid',
            'period' => 'required|string|regex:/^\\d{4}-\\d{2}$/',
            'target_amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function validateSimulation(array $data): array
    {
        $validator = Validator::make($data, [
            'user_uid' => 'required|uuid',
            'sale_amount' => 'required|numeric|min:0.01',
            'margin_amount' => 'nullable|numeric|min:0',
            'period' => 'nullable|string|regex:/^\\d{4}-\\d{2}$/',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function validateRun(array $data): array
    {
        $validator = Validator::make($data, [
            'user_uid' => 'required|uuid',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
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
            'source_system' => 'nullable|string|max:255',
            'external_reference' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'outstanding_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'issued_at' => 'nullable|date',
            'due_at' => 'nullable|date',
            'paid_at' => 'required|date',
            'status' => 'sometimes|string|in:paid,partial',
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

    private function resolveRoleIds(array $roleUids): array
    {
        if (empty($roleUids)) {
            return [];
        }

        $roles = Role::query()->whereIn('uid', $roleUids)->get();

        if ($roles->count() !== count(array_unique($roleUids))) {
            throw ValidationException::withMessages([
                'role_uids' => ['Uno o mas roles no existen o no pertenecen a este tenant'],
            ]);
        }

        return $roles->pluck('id')->all();
    }

    private function resolveUser(string $uid): User
    {
        return User::query()->where('uid', $uid)->firstOr(function () {
            throw ValidationException::withMessages([
                'user_uid' => ['El vendedor no existe o no pertenece a este tenant'],
            ]);
        });
    }

    private function resolveUserId(string $uid): int
    {
        return $this->resolveUser($uid)->getKey();
    }

    private function resolvePlan(string $uid): CommissionPlan
    {
        return CommissionPlan::query()->with('roles')->where('uid', $uid)->firstOr(function () {
            throw ValidationException::withMessages([
                'commission_plan_uid' => ['El plan de comision no existe o no pertenece a este tenant'],
            ]);
        });
    }

    private function resolveTeamUserIds(string $managerUid): array
    {
        $manager = $this->resolveUser($managerUid);
        $visible = [$manager->getKey()];
        $pending = [$manager->getKey()];

        while (!empty($pending)) {
            $subordinates = User::query()->whereIn('manager_id', $pending)->pluck('id')->all();
            $newIds = array_values(array_diff($subordinates, $visible));
            $visible = array_merge($visible, $newIds);
            $pending = $newIds;
        }

        return $visible;
    }

    private function ensureUserMatchesPlanRoles(User $user, CommissionPlan $plan): void
    {
        $roleIds = $plan->roles->pluck('id')->all();

        if (empty($roleIds)) {
            return;
        }

        $userRoleIds = $user->roles()->pluck('roles.id')->all();

        if (empty(array_intersect($roleIds, $userRoleIds))) {
            throw ValidationException::withMessages([
                'user_uid' => ['El vendedor no cumple con los roles aplicables del plan'],
            ]);
        }
    }

    private function validateAssignmentOverlap(int $userId, string $startsAt, ?string $endsAt, ?int $ignoreId = null): void
    {
        $query = CommissionAssignment::query()
            ->where('user_id', $userId)
            ->where('active', true)
            ->whereDate('starts_at', '<=', $endsAt ?? '9999-12-31')
            ->where(function ($query) use ($startsAt) {
                $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $startsAt);
            });

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'assignment' => ['Ya existe una asignacion activa que se solapa con ese periodo'],
            ]);
        }
    }

    private function resolveActiveAssignment(int $userId, Carbon $date): ?CommissionAssignment
    {
        return CommissionAssignment::query()
            ->with('commissionPlan.roles')
            ->where('user_id', $userId)
            ->where('active', true)
            ->whereDate('starts_at', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $date->toDateString());
            })
            ->latest('starts_at')
            ->first();
    }

    private function resolveTargetForPeriod(int $userId, string $period): ?CommissionTarget
    {
        return CommissionTarget::query()->where('user_id', $userId)->where('period', $period)->first();
    }

    private function calculateForUser(int $userId, float $salesAmount, float $marginAmount, Carbon $date): array
    {
        $assignment = $this->resolveActiveAssignment($userId, $date);

        if (!$assignment?->commissionPlan) {
            return [
                'plan' => null,
                'target' => null,
                'basis' => 'sale',
                'applied_percent' => 0,
                'commission_amount' => 0,
                'tier_breakdown' => [],
            ];
        }

        $plan = $assignment->commissionPlan;
        $period = $date->format('Y-m');
        $periodStart = $date->copy()->startOfMonth()->toDateString();
        $periodEnd = $date->copy()->endOfMonth()->toDateString();
        $existingEntries = CommissionEntry::query()
            ->where('user_id', $userId)
            ->whereBetween('earned_at', [$periodStart, $periodEnd])
            ->get();
        $salesTotal = round((float) $existingEntries->sum('base_amount') + $salesAmount, 2);
        $marginTotal = round((float) $existingEntries->sum(fn (CommissionEntry $entry) => (float) data_get($entry->meta, 'margin_amount', 0)) + $marginAmount, 2);
        $target = $this->resolveTargetForPeriod($userId, $period);

        $metric = match ($plan->type) {
            'margin' => $marginTotal,
            'target' => $target && (float) $target->target_amount > 0 ? round(($salesTotal / (float) $target->target_amount) * 100, 2) : $salesTotal,
            default => $salesTotal,
        };

        $appliedPercent = (float) $plan->base_percent;
        $tierBreakdown = collect($plan->tiers_json ?? [])
            ->sortBy('threshold')
            ->values()
            ->map(function (array $tier) use (&$appliedPercent, $metric) {
                $threshold = (float) ($tier['threshold'] ?? 0);
                $percent = (float) ($tier['percent'] ?? 0);
                $applies = $metric >= $threshold;

                if ($applies) {
                    $appliedPercent = $percent;
                }

                return ['threshold' => $threshold, 'percent' => $percent, 'applies' => $applies];
            })
            ->all();

        $commissionBase = $plan->type === 'margin' ? $marginAmount : $salesAmount;

        return [
            'plan' => ['uid' => $plan->uid, 'name' => $plan->name, 'type' => $plan->type],
            'target' => $target ? ['uid' => $target->uid, 'period' => $target->period, 'target_amount' => (float) $target->target_amount] : null,
            'basis' => $plan->type,
            'applied_percent' => round($appliedPercent, 2),
            'commission_amount' => round($commissionBase * ($appliedPercent / 100), 2),
            'tier_breakdown' => $tierBreakdown,
        ];
    }
}
