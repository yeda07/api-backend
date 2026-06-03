<?php

namespace App\Services;

use App\Models\FinancialRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlatformBillingService
{
    public function __construct(private TenantSchemaService $tenantSchemaService)
    {
    }

    public function invoices(array $filters = []): Collection
    {
        return $this->tenants($filters)
            ->flatMap(function (Tenant $tenant) use ($filters) {
                return $this->tenantSchemaService->runForTenant($tenant, function () use ($tenant, $filters) {
                    return $this->invoiceQuery($filters)
                        ->where('tenant_id', $tenant->getKey())
                        ->get();
                });
            })
            ->sortByDesc(fn (Invoice $invoice) => optional($invoice->issued_at)->timestamp ?? optional($invoice->created_at)->timestamp ?? 0)
            ->values();
    }

    public function overdueCount(?\DateTimeInterface $periodStart = null): int
    {
        return $this->invoices([
            'status' => 'overdue',
            'from_created_at' => $periodStart,
        ])->count();
    }

    public function markPaid(array $uids): array
    {
        $uidLookup = array_flip($uids);
        $updated = [];

        foreach ($this->tenants([]) as $tenant) {
            $tenantUpdated = $this->tenantSchemaService->runForTenant($tenant, function () use ($tenant, $uidLookup) {
                return DB::transaction(function () use ($tenant, $uidLookup) {
                    $invoices = Invoice::withoutGlobalScopes()
                        ->with(['tenant.plan'])
                        ->where('tenant_id', $tenant->getKey())
                        ->whereIn('uid', array_keys($uidLookup))
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
                            'external_reference' => 'ADMIN-'.$invoice->uid,
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

                        $updated[] = $invoice->fresh(['tenant.plan']);
                    }

                    return $updated;
                });
            });

            array_push($updated, ...$tenantUpdated);
        }

        return $updated;
    }

    private function tenants(array $filters): Collection
    {
        $query = Tenant::query()->with('plan')->orderBy('id');

        if (! empty($filters['tenant_uid'])) {
            $query->where('uid', $filters['tenant_uid']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('domain', 'like', '%'.$search.'%')
                    ->orWhere('contact_email', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['plan_uid'])) {
            $planId = Plan::query()->where('uid', $filters['plan_uid'])->value('id');
            $query->where('plan_id', $planId);
        }

        if (! empty($filters['plan_nombre'])) {
            $planName = $filters['plan_nombre'];
            $query->whereHas('plan', fn (Builder $builder) => $builder->where('name', 'like', '%'.$planName.'%'));
        }

        return $query->get();
    }

    private function invoiceQuery(array $filters): Builder
    {
        return Invoice::withoutGlobalScopes()
            ->with(['tenant.plan'])
            ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['from']), fn (Builder $query) => $query->whereDate('issued_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn (Builder $query) => $query->whereDate('issued_at', '<=', $filters['to']))
            ->when(! empty($filters['from_created_at']), fn (Builder $query) => $query->where('created_at', '>=', $filters['from_created_at']));
    }
}
