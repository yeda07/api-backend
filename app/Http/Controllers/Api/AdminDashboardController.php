<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\SystemLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $validated = Validator::make($request->query(), [
            'period' => 'nullable|string|in:7d,30d,90d,12m',
            'recent_page' => 'nullable|integer|min:1',
            'recent_per_page' => 'nullable|integer|min:1|max:50',
        ])->validate();

        $period = $validated['period'] ?? null;
        $periodStart = $this->periodStart($period);
        $tenants = Tenant::query()->with('plan')->get();
        $periodTenants = $periodStart
            ? $tenants->filter(fn (Tenant $tenant) => $tenant->created_at && $tenant->created_at->gte($periodStart))
            : $tenants;
        $mrrTotal = round((float) $periodTenants->sum(fn (Tenant $tenant) => $tenant->mrr ?? $tenant->plan?->price ?? 0), 2);
        $recentPage = max((int) $request->integer('recent_page', 1), 1);
        $recentPerPage = min(max((int) $request->integer('recent_per_page', 10), 1), 50);

        $months = collect(range(5, 0))
            ->reverse()
            ->map(function (int $offset) use ($tenants) {
                $month = Carbon::now()->subMonths($offset)->startOfMonth();
                $value = $tenants
                    ->filter(fn (Tenant $tenant) => $tenant->created_at && $tenant->created_at->lte($month->copy()->endOfMonth()))
                    ->sum(fn (Tenant $tenant) => $tenant->mrr ?? $tenant->plan?->price ?? 0);

                return [
                    'mes' => $month->translatedFormat('M'),
                    'valor' => round((float) $value, 2),
                ];
            })
            ->values();

        $previous = (float) ($months->slice(-2, 1)->first()['valor'] ?? 0);
        $growth = $previous > 0 ? round((($mrrTotal - $previous) / $previous) * 100, 2) : 0;
        $tenantsAtRisk = $tenants
            ->whereIn('status', ['VENCIDO', 'SUSPENDIDO'])
            ->sortByDesc('created_at')
            ->take(5)
            ->values()
            ->map(fn (Tenant $tenant) => $this->serializeTenantSummary($tenant));

        $recentTenantsCollection = $tenants
            ->sortByDesc('created_at')
            ->values();
        $recentTotal = $recentTenantsCollection->count();
        $recentItems = $recentTenantsCollection
            ->slice(($recentPage - 1) * $recentPerPage, $recentPerPage)
            ->values()
            ->map(fn (Tenant $tenant) => $this->serializeTenantSummary($tenant));

        return $this->successResponse([
            'period' => $period,
            'mrr_total' => $mrrTotal,
            'mrr_growth_percent' => $growth,
            'mrr_history' => $months,
            'tenants_activos' => $periodTenants->where('status', 'ACTIVO')->count(),
            'tenants_trial' => $periodTenants->where('status', 'TRIAL')->count(),
            'tenants_en_riesgo_count' => $periodTenants->whereIn('status', ['VENCIDO', 'SUSPENDIDO'])->count(),
            'facturas_vencidas' => Invoice::withoutGlobalScopes()
                ->where('status', 'overdue')
                ->when($periodStart, fn ($query) => $query->where('created_at', '>=', $periodStart))
                ->count(),
            'errores_criticos_24h' => SystemLog::withoutGlobalScopes()
                ->whereIn('level', ['error', 'critical'])
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'usuarios_totales' => User::withoutGlobalScopes()
                ->whereNotNull('tenant_id')
                ->when($periodStart, fn ($query) => $query->where('created_at', '>=', $periodStart))
                ->count(),
            'tenants_en_riesgo' => $tenantsAtRisk,
            'requieren_atencion' => $tenantsAtRisk,
            'tenants_recientes' => $recentItems,
            'tenants_recientes_pagination' => [
                'page' => $recentPage,
                'per_page' => $recentPerPage,
                'total' => $recentTotal,
                'last_page' => (int) ceil(max($recentTotal, 1) / $recentPerPage),
            ],
        ]);
    }

    private function periodStart(?string $period): ?Carbon
    {
        return match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '12m' => now()->subMonths(12),
            default => null,
        };
    }

    private function serializeTenantSummary(Tenant $tenant): array
    {
        $lastAccessAt = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->max('last_login_at');

        return [
            'uid' => $tenant->uid,
            'nombre' => $tenant->name,
            'dominio' => $tenant->domain,
            'pais' => $tenant->country,
            'email_contacto' => $tenant->contact_email,
            'plan_uid' => $tenant->plan?->uid,
            'plan_nombre' => $tenant->plan?->name,
            'mrr' => (float) ($tenant->mrr ?? $tenant->plan?->price ?? 0),
            'estado' => $tenant->status,
            'total_usuarios' => User::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->count(),
            'limite_usuarios' => $tenant->plan?->max_users,
            'almacenamiento_usado_gb' => $tenant->storage_used_gb !== null ? (float) $tenant->storage_used_gb : 0.0,
            'limite_almacenamiento_gb' => $tenant->storage_limit_gb !== null ? (float) $tenant->storage_limit_gb : null,
            'api_calls_mes' => (int) ($tenant->api_calls_mes ?? 0),
            'limite_api_calls' => (int) (($tenant->limite_api_calls ?? 0) ?: data_get($tenant->plan?->features, 'api_calls_month', 0)),
            'created_at' => optional($tenant->created_at)?->toISOString(),
            'last_access_at' => $lastAccessAt ? Carbon::parse($lastAccessAt)->toISOString() : null,
        ];
    }
}
