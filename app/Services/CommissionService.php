<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CommissionAssignment;
use App\Models\CommissionEntry;
use App\Models\CommissionPlan;
use App\Models\CommissionRule;
use App\Models\CommissionRun;
use App\Models\CommissionRunItem;
use App\Models\CommissionTarget;
use App\Models\Contact;
use App\Models\CrmEntity;
use App\Models\FinancialRecord;
use App\Models\InventoryProduct;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\ApiIndex;
use App\Support\SimplePdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommissionService
{
    public function __construct(private readonly FinancialOperationsService $financialOperationsService) {}

    public function plans(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'search' => 'nullable|string|max:255',
        ])->validate();
        $query = CommissionPlan::query()->with('roles')->orderBy('name');

        if (! empty($validated['search'])) {
            $search = '%'.mb_strtolower($validated['search']).'%';
            $query->whereRaw('LOWER(name) LIKE ?', [$search]);
        }

        return ApiIndex::paginateOrGet(
            $query,
            $filters,
            'commission_plans_page'
        );
    }

    public function getPlan(string $uid): CommissionPlan
    {
        return $this->resolvePlan($uid);
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
            if (! empty($payload)) {
                $plan->update($payload);
            }

            if (array_key_exists('role_uids', $validated)) {
                $plan->roles()->sync($this->resolveRoleIds($validated['role_uids'] ?? []));
            }

            return $plan->fresh('roles');
        });
    }

    public function deletePlan(string $uid): void
    {
        $plan = $this->resolvePlan($uid);

        if ($plan->assignments()->exists()) {
            throw ValidationException::withMessages([
                'plan' => ['No puedes eliminar un plan con asignaciones'],
            ]);
        }

        $plan->delete();
    }

    public function assignments(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'user_uid' => 'nullable|string',
            'manager_uid' => 'nullable|string',
            'team_uid' => 'nullable|uuid',
            'search' => 'nullable|string|max:255',
            'active' => 'nullable',
        ])->validate();
        $query = CommissionAssignment::query()->with(['user.roles', 'commissionPlan.roles'])->latest('starts_at');

        if (! empty($validated['user_uid'])) {
            $query->where('user_id', $this->resolveUserId($validated['user_uid']));
        }

        if (! empty($validated['manager_uid'])) {
            $query->whereIn('user_id', $this->resolveTeamUserIds($validated['manager_uid']));
        }

        if (! empty($validated['team_uid'])) {
            $query->whereIn('user_id', $this->resolveTeamMemberUserIds($validated['team_uid']));
        }

        if (! empty($validated['search'])) {
            $search = '%'.mb_strtolower($validated['search']).'%';
            $query->whereHas('user', fn ($userQuery) => $userQuery->whereRaw('LOWER(name) LIKE ?', [$search]));
        }

        if (array_key_exists('active', $validated) && $validated['active'] !== null && $validated['active'] !== '') {
            $query->where('active', filter_var($validated['active'], FILTER_VALIDATE_BOOLEAN));
        }

        return ApiIndex::paginateOrGet($query, $filters, 'commission_assignments_page');
    }

    public function getAssignment(string $uid): CommissionAssignment
    {
        return CommissionAssignment::query()->with(['user.roles', 'commissionPlan.roles'])->where('uid', $uid)->firstOrFail();
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

    public function createBulkAssignments(array $data): array
    {
        $validated = Validator::make($data, [
            'user_uids' => 'required_without:user_ids|array|min:1',
            'user_uids.*' => 'uuid',
            'user_ids' => 'required_without:user_uids|array|min:1',
            'user_ids.*' => 'uuid',
            'commission_plan_uid' => 'required|uuid',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'active' => 'sometimes|boolean',
        ])->validate();

        $userUids = $validated['user_uids'] ?? $validated['user_ids'];

        return DB::transaction(function () use ($validated, $userUids) {
            return collect(array_values(array_unique($userUids)))
                ->map(fn (string $userUid) => $this->createAssignment([
                    'user_uid' => $userUid,
                    'commission_plan_uid' => $validated['commission_plan_uid'],
                    'starts_at' => $validated['starts_at'],
                    'ends_at' => $validated['ends_at'] ?? null,
                    'active' => $validated['active'] ?? true,
                ]))
                ->values()
                ->all();
        });
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

    public function deleteAssignment(string $uid): void
    {
        $this->getAssignment($uid)->delete();
    }

    public function targets(?string $userUid = null, array $filters = [])
    {
        $query = CommissionTarget::query()->with('user')->orderByDesc('period');

        if ($userUid) {
            $query->where('user_id', $this->resolveUserId($userUid));
        }

        return ApiIndex::paginateOrGet($query, $filters, 'commission_targets_page');
    }

    public function getTarget(string $uid): CommissionTarget
    {
        return CommissionTarget::query()->with('user')->where('uid', $uid)->firstOrFail();
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

    public function updateTarget(string $uid, array $data): CommissionTarget
    {
        $target = $this->getTarget($uid);
        $validated = $this->validateTarget($data, true);
        $payload = [];

        if (array_key_exists('user_uid', $validated)) {
            $payload['user_id'] = $this->resolveUserId($validated['user_uid']);
        }

        if (array_key_exists('period', $validated)) {
            $payload['period'] = $validated['period'];
        }

        if (array_key_exists('target_amount', $validated)) {
            $payload['target_amount'] = $validated['target_amount'];
        }

        $target->update($payload);

        return $target->fresh('user');
    }

    public function deleteTarget(string $uid): void
    {
        $this->getTarget($uid)->delete();
    }

    public function rules(array $filters = [])
    {
        return ApiIndex::paginateOrGet(
            CommissionRule::query()->with('product')->orderBy('name'),
            $filters,
            'commission_rules_page'
        );
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
        $data = $this->normalizeFinancialRecordPayload($data);
        $validated = $this->validateFinancialRecord($data);

        return DB::transaction(function () use ($validated) {
            $quotation = $this->resolveQuotation($validated['quotation_uid'] ?? null);
            $entityType = $validated['entity_type'] ?? $quotation?->quoteable_type;
            $entityUid = $validated['entity_uid'] ?? $quotation?->quoteable?->uid;

            if (! $quotation && (! $entityType || ! $entityUid)) {
                $record = $this->createStandaloneFinancialRecord($validated);
                $entries = collect();

                return [
                    'financial_record' => $record,
                    'commission_entries' => $entries->values(),
                    'summary' => [
                        'entries_count' => 0,
                        'commission_total' => 0,
                    ],
                ];
            }

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

    public function entries(?string $userUid = null, array $filters = [])
    {
        $query = CommissionEntry::query()
            ->with(['user', 'rule.product', 'quotation', 'quotationItem', 'financialRecord', 'commissionRun'])
            ->latest('earned_at');

        if ($userUid) {
            $query->where('user_id', $this->resolveUserId($userUid));
        }

        return ApiIndex::paginateOrGet($query, $filters, 'commission_entries_page');
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
        $progressPercent = $targetAmount > 0
            ? round(($salesAchieved / $targetAmount) * 100, 2)
            : 0.0;

        return [
            'kpis' => [
                'monthly_target' => $targetAmount,
                'sales_achieved' => $salesAchieved,
                'progress_percent' => $progressPercent,
                'projected_commission' => $projected,
                'liquidated_commission' => $liquidated,
            ],
            'tiers' => $this->buildTierProgress($user->getKey(), $salesAchieved),
            'recentSales' => CommissionEntry::query()
                ->with(['quotation'])
                ->where('user_id', $user->getKey())
                ->latest('earned_at')
                ->limit(10)
                ->get()
                ->map(fn (CommissionEntry $entry) => [
                    'uid' => $entry->uid,
                    'date' => $entry->earned_at,
                    'client' => $entry->quotation?->client_name ?? $this->commissionEntryClientName($entry),
                    'amount' => round((float) $entry->base_amount, 2),
                    'commission_generated' => round((float) $entry->commission_amount, 2),
                ])
                ->values()
                ->all(),
        ];
    }

    public function simulate(array $data): array
    {
        if (! empty($data['plan_uid'])) {
            return $this->simulatePlan($data);
        }

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
        $validated = Validator::make($filters, [
            'user_uid' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:255',
            'period' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
        ])->validate();
        $query = CommissionRun::query()->with(['user', 'commissionPlan.roles', 'items'])->latest('period_start');

        if (! empty($validated['user_uid'])) {
            $query->where('user_id', $this->resolveUserId($validated['user_uid']));
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = '%'.mb_strtolower($validated['search']).'%';
            $query->whereHas('user', fn ($userQuery) => $userQuery->whereRaw('LOWER(name) LIKE ?', [$search]));
        }

        if (! empty($validated['period'])) {
            $periodStart = Carbon::createFromFormat('Y-m-d', $validated['period'].'-01')->startOfMonth()->toDateString();
            $periodEnd = Carbon::createFromFormat('Y-m-d', $validated['period'].'-01')->endOfMonth()->toDateString();

            $query->whereDate('period_start', '<=', $periodEnd)
                ->whereDate('period_end', '>=', $periodStart);
        }

        return ApiIndex::paginateOrGet($query, $filters, 'commission_runs_page');
    }

    public function periods(): array
    {
        return CommissionRun::query()
            ->whereNotNull('period_start')
            ->orderByDesc('period_start')
            ->pluck('period_start')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m'))
            ->unique()
            ->values()
            ->all();
    }

    public function historyPdf(array $filters = []): string
    {
        $period = $filters['period'] ?? now()->format('Y-m');
        $start = $this->parsePeriodStart((string) $period);
        $end = $start->copy()->endOfMonth();
        $entries = CommissionEntry::query()
            ->with(['user', 'quotation'])
            ->whereBetween('earned_at', [$start->toDateString(), $end->toDateString()])
            ->latest('earned_at')
            ->get();

        $lines = [
            'Periodo: '.$start->format('Y-m'),
            'Comisiones: '.$entries->count(),
            'Base total: '.number_format((float) $entries->sum('base_amount'), 2),
            'Comision total: '.number_format((float) $entries->sum('commission_amount'), 2),
            'Generado: '.now()->toDateTimeString(),
            '',
        ];

        if ($this->booleanFilter($filters, ['include_sales_detail', 'detalle_ventas'], true)) {
            foreach ($entries->take(30) as $entry) {
                $lines[] = ($entry->earned_at?->toDateString() ?? '-').' | '
                    .($entry->user?->name ?? 'Usuario').' | '
                    .number_format((float) $entry->base_amount, 2).' | '
                    .number_format((float) $entry->commission_amount, 2);
            }
        }

        return SimplePdf::document('Historial de Comisiones', $lines);
    }

    private function parsePeriodStart(string $period): Carbon
    {
        $normalized = str($period)->lower()->trim()->replace('_', '-')->toString();

        if (preg_match('/^\d{4}-\d{2}$/', $normalized)) {
            return Carbon::createFromFormat('Y-m-d', $normalized.'-01')->startOfMonth();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
            return Carbon::parse($normalized)->startOfMonth();
        }

        if (preg_match('/^([a-záéíóúñ]+)-(\d{4})$/u', $normalized, $matches)) {
            $month = $this->spanishMonthNumber($matches[1]);

            if ($month !== null) {
                return Carbon::create((int) $matches[2], $month, 1)->startOfMonth();
            }
        }

        throw ValidationException::withMessages([
            'period' => ['El periodo debe tener formato YYYY-MM o mes-YYYY, por ejemplo 2025-02 o febrero-2025'],
        ]);
    }

    private function spanishMonthNumber(string $month): ?int
    {
        return [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ][$month] ?? null;
    }

    private function booleanFilter(array $filters, array $keys, bool $default): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $filters)) {
                return filter_var($filters[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $default;
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

            if ($ratePercent <= 0 && ! $planCalculation['plan']) {
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
        if ($quotation->quoteable_type === Account::class) {
            return 'B2B';
        }

        if ($quotation->quoteable_type === Contact::class) {
            return 'B2C';
        }

        if ($quotation->quoteable_type === CrmEntity::class) {
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
        $data = $this->normalizePlanPayload($data, $partial);

        $validator = Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'type' => [$partial ? 'sometimes' : 'required', 'string', 'in:sale,margin,target'],
            'base_percent' => [$partial ? 'sometimes' : 'required', 'numeric', 'min:0', 'max:100'],
            'tiers_json' => 'sometimes|array',
            'tiers_json.*.uid' => 'sometimes|string|max:255',
            'tiers_json.*.threshold' => 'required_with:tiers_json|numeric|min:0',
            'tiers_json.*.percent' => 'required_without:tiers_json.*.percentage|numeric|min:0|max:100',
            'tiers_json.*.percentage' => 'required_without:tiers_json.*.percent|numeric|min:0|max:100',
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

        if (! empty($validated['tiers_json'])) {
            $validated['tiers_json'] = collect($validated['tiers_json'])
                ->map(fn (array $tier) => [
                    'uid' => $tier['uid'] ?? null,
                    'threshold' => $tier['threshold'],
                    'percent' => $tier['percent'] ?? $tier['percentage'],
                ])
                ->all();
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

    private function validateTarget(array $data, bool $partial = false): array
    {
        if (array_key_exists('goal_value', $data) && ! array_key_exists('target_amount', $data)) {
            $data['target_amount'] = $data['goal_value'];
        }

        $validator = Validator::make($data, [
            'user_uid' => [$partial ? 'sometimes' : 'required', 'uuid'],
            'metric' => 'sometimes|string|in:total_sales',
            'period' => [$partial ? 'sometimes' : 'required', 'string', 'regex:/^\\d{4}-(\\d{2}|Q[1-4])$/'],
            'target_amount' => [$partial ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'goal_value' => 'sometimes|numeric|min:0',
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
            'entity_type' => 'nullable|string|max:255',
            'entity_uid' => 'nullable|uuid',
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

    private function createStandaloneFinancialRecord(array $validated): FinancialRecord
    {
        return FinancialRecord::query()->create([
            'owner_user_id' => auth()->id(),
            'quotation_id' => null,
            'record_type' => $validated['record_type'],
            'source_system' => $validated['source_system'] ?? 'manual',
            'external_reference' => $validated['external_reference'] ?? null,
            'amount' => $validated['amount'],
            'outstanding_amount' => $validated['outstanding_amount'] ?? 0,
            'currency' => $validated['currency'] ?? null,
            'issued_at' => $validated['issued_at'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'paid_at' => $validated['paid_at'],
            'status' => $validated['status'] ?? 'paid',
            'meta' => $validated['meta'] ?? null,
        ])->fresh(['owner', 'quotation', 'financeable']);
    }

    private function normalizePlanPayload(array $data, bool $partial = false): array
    {
        if (array_key_exists('base_percentage', $data) && ! array_key_exists('base_percent', $data)) {
            $data['base_percent'] = $data['base_percentage'];
        }

        if (array_key_exists('tiers', $data) && ! array_key_exists('tiers_json', $data)) {
            $data['tiers_json'] = $data['tiers'];
        }

        if (array_key_exists('is_active', $data) && ! array_key_exists('active', $data)) {
            $data['active'] = $data['is_active'];
        }

        if (! $partial && ! array_key_exists('type', $data) && ! empty($data)) {
            $data['type'] = 'sale';
        }

        return $data;
    }

    private function normalizeFinancialRecordPayload(array $data): array
    {
        if (array_key_exists('type', $data) && ! array_key_exists('record_type', $data)) {
            $data['record_type'] = match ($data['type']) {
                'sale' => 'collection_received',
                default => $data['type'],
            };
        }

        if (array_key_exists('recorded_at', $data) && ! array_key_exists('paid_at', $data)) {
            $data['paid_at'] = $data['recorded_at'];
        }

        if (! array_key_exists('paid_at', $data)) {
            $data['paid_at'] = now()->toDateString();
        }

        if (! array_key_exists('status', $data)) {
            $data['status'] = 'paid';
        }

        if (! array_key_exists('source_system', $data)) {
            $data['source_system'] = 'manual';
        }

        if (array_key_exists('description', $data)) {
            $data['meta'] = array_merge($data['meta'] ?? [], ['description' => $data['description']]);
        }

        return $data;
    }

    private function simulatePlan(array $data): array
    {
        if (! array_key_exists('total_sales', $data)
            && (array_key_exists('accumulated_sales', $data) || array_key_exists('hypothetical_sale', $data))) {
            $data['total_sales'] = (float) ($data['accumulated_sales'] ?? 0) + (float) ($data['hypothetical_sale'] ?? 0);
        }

        $validated = Validator::make($data, [
            'plan_uid' => 'required|uuid',
            'total_sales' => 'required|numeric|min:0',
            'margin_amount' => 'nullable|numeric|min:0',
        ])->validate();

        $plan = $this->resolvePlan($validated['plan_uid']);
        $basisAmount = $plan->type === 'margin'
            ? (float) ($validated['margin_amount'] ?? 0)
            : (float) $validated['total_sales'];
        $metric = $basisAmount;
        $appliedPercent = (float) $plan->base_percent;
        $tierApplied = 0;

        foreach (collect($plan->tiers_json ?? [])->sortBy('threshold')->values() as $index => $tier) {
            if ($metric >= (float) ($tier['threshold'] ?? 0)) {
                $appliedPercent = (float) ($tier['percent'] ?? $tier['percentage'] ?? $appliedPercent);
                $tierApplied = $index + 1;
            }
        }

        return [
            'plan_uid' => $plan->uid,
            'total_sales' => round((float) $validated['total_sales'], 2),
            'commission_amount' => round($basisAmount * ($appliedPercent / 100), 2),
            'effective_percentage' => round($appliedPercent, 2),
            'tier_applied' => $tierApplied,
        ];
    }

    private function resolveProductId(?string $uid): ?int
    {
        if (! $uid) {
            return null;
        }

        $productId = InventoryProduct::query()->where('uid', $uid)->value('id');

        if (! $productId) {
            throw ValidationException::withMessages([
                'product_uid' => ['El producto no existe o no pertenece a este tenant'],
            ]);
        }

        return $productId;
    }

    private function resolveQuotation(?string $uid): ?Quotation
    {
        if (! $uid) {
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

        while (! empty($pending)) {
            $subordinates = User::query()->whereIn('manager_id', $pending)->pluck('id')->all();
            $newIds = array_values(array_diff($subordinates, $visible));
            $visible = array_merge($visible, $newIds);
            $pending = $newIds;
        }

        return $visible;
    }

    private function resolveTeamMemberUserIds(string $teamUid): array
    {
        $team = Team::query()->with('members')->where('uid', $teamUid)->firstOr(function () {
            throw ValidationException::withMessages([
                'team_uid' => ['El equipo no existe o no pertenece a este tenant'],
            ]);
        });

        return collect([$team->manager_user_id])
            ->merge($team->members->pluck('id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
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

    private function buildTierProgress(int $userId, float $salesAchieved): array
    {
        $assignment = $this->resolveActiveAssignment($userId, now());
        $tiers = collect($assignment?->commissionPlan?->tiers ?? [])
            ->sortBy('threshold')
            ->values();

        $previousThreshold = 0.0;

        return $tiers
            ->map(function (array $tier, int $index) use ($salesAchieved, &$previousThreshold) {
                $threshold = round((float) ($tier['threshold'] ?? 0), 2);
                $percent = round((float) ($tier['percent'] ?? $tier['percentage'] ?? 0), 2);
                $rangeSize = max(0.01, $threshold - $previousThreshold);
                $coveredInRange = min(max(0, $salesAchieved - $previousThreshold), $rangeSize);
                $completedPct = round(min(100, ($coveredInRange / $rangeSize) * 100), 2);
                $achieved = round(min($salesAchieved, $threshold), 2);
                $status = match (true) {
                    $salesAchieved >= $threshold => 'COMPLETED',
                    $salesAchieved > $previousThreshold => 'IN_PROGRESS',
                    default => 'PENDING',
                };
                $row = [
                    'uid' => (string) ($tier['uid'] ?? 'tier-'.($index + 1)),
                    'name' => 'Tramo '.($index + 1),
                    'range_text' => $this->moneyRange($previousThreshold, $threshold),
                    'percent' => $percent,
                    'completed' => $completedPct,
                    'status' => $status,
                    'amount_achieved' => $achieved,
                    'amount_target' => $threshold,
                ];

                $previousThreshold = $threshold;

                return $row;
            })
            ->all();
    }

    private function moneyRange(float $from, float $to): string
    {
        return '$'.number_format($from, 0, ',', '.').' - $'.number_format($to, 0, ',', '.');
    }

    private function commissionEntryClientName(CommissionEntry $entry): string
    {
        $quotation = $entry->quotation
            ?? ($entry->quotation_id ? Quotation::withoutGlobalScopes()->whereKey($entry->quotation_id)->first() : null);
        $quoteable = $quotation?->quoteable;
        $quoteableClass = $quotation?->quoteable_type;

        if (! $quoteable && $quoteableClass && $quotation?->quoteable_id && is_subclass_of($quoteableClass, Model::class)) {
            $quoteable = $quoteableClass::withoutGlobalScopes()->whereKey($quotation->quoteable_id)->first();
        }

        return $quotation?->client_name
            ?? $quoteable?->display_name
            ?? $quoteable?->name
            ?? $quotation?->title
            ?? $quotation?->quote_number
            ?? '—';
    }

    private function calculateForUser(int $userId, float $salesAmount, float $marginAmount, Carbon $date): array
    {
        $assignment = $this->resolveActiveAssignment($userId, $date);

        if (! $assignment?->commissionPlan) {
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
