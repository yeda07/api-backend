<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\SystemLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $tenants = Tenant::query()->with('plan')->get();
        $mrrTotal = round((float) $tenants->sum(fn (Tenant $tenant) => $tenant->mrr ?? $tenant->plan?->price ?? 0), 2);

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

        return $this->successResponse([
            'mrr_total' => $mrrTotal,
            'mrr_growth_percent' => $growth,
            'mrr_history' => $months,
            'tenants_activos' => $tenants->where('status', 'ACTIVO')->count(),
            'tenants_trial' => $tenants->where('status', 'TRIAL')->count(),
            'tenants_en_riesgo' => $tenants->whereIn('status', ['VENCIDO', 'SUSPENDIDO'])->count(),
            'facturas_vencidas' => Invoice::withoutGlobalScopes()->where('status', 'overdue')->count(),
            'errores_criticos_24h' => SystemLog::withoutGlobalScopes()
                ->whereIn('level', ['error', 'critical'])
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'usuarios_totales' => User::withoutGlobalScopes()->whereNotNull('tenant_id')->count(),
            'requieren_atencion' => $tenants
                ->whereIn('status', ['VENCIDO', 'SUSPENDIDO'])
                ->values()
                ->map(fn (Tenant $tenant) => $this->serializeTenantSummary($tenant)),
            'tenants_recientes' => $tenants
                ->sortByDesc('created_at')
                ->take(5)
                ->values()
                ->map(fn (Tenant $tenant) => $this->serializeTenantSummary($tenant)),
        ]);
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
            'plan_uid' => $tenant->plan?->uid,
            'plan_nombre' => $tenant->plan?->name,
            'mrr' => (float) ($tenant->mrr ?? $tenant->plan?->price ?? 0),
            'estado' => $tenant->status,
            'total_usuarios' => User::withoutGlobalScopes()->where('tenant_id', $tenant->getKey())->count(),
            'limite_usuarios' => $tenant->plan?->max_users,
            'last_access_at' => $lastAccessAt ? Carbon::parse($lastAccessAt)->toISOString() : null,
        ];
    }
}
