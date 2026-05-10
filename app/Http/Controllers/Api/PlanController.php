<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    private const PLAN_MODULES = [
        ['key' => 'ventas', 'label' => 'Ventas'],
        ['key' => 'inventario', 'label' => 'Inventario'],
        ['key' => 'rh', 'label' => 'RH / Comisiones'],
        ['key' => 'reportes', 'label' => 'Reportes'],
        ['key' => 'multi-currency', 'label' => 'Multi-currency'],
        ['key' => 'api-publica', 'label' => 'API Publica'],
    ];

    public function index()
    {
        return $this->successResponse(
            Plan::query()
                ->withCount([
                    'tenants as total_tenants' => fn ($query) => $query->whereIn('status', ['ACTIVO', 'TRIAL']),
                ])
                ->orderBy('name')
                ->get()
                ->map(fn (Plan $plan) => $this->serializePlan($plan))
                ->values()
        );
    }

    public function modules()
    {
        return $this->successResponse(self::PLAN_MODULES);
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validatePlan($request, false);

            $plan = Plan::query()->create($validated);

            return $this->successResponse(
                $this->serializePlan(Plan::query()->withCount([
                    'tenants as total_tenants' => fn ($query) => $query->whereIn('status', ['ACTIVO', 'TRIAL']),
                ])->findOrFail($plan->getKey())),
                201
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function update(Request $request, string $uid)
    {
        try {
            $plan = Plan::query()->where('uid', $uid)->first();

            if (!$plan) {
                return $this->errorResponse('Plan no encontrado', 404);
            }

            $validated = $this->validatePlan($request, true);

            $plan->update($validated);

            return $this->successResponse(
                $this->serializePlan(Plan::query()->withCount([
                    'tenants as total_tenants' => fn ($query) => $query->whereIn('status', ['ACTIVO', 'TRIAL']),
                ])->findOrFail($plan->getKey())),
                200,
                'Plan actualizado'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroy(string $uid)
    {
        try {
            $plan = Plan::query()->withCount('tenants')->where('uid', $uid)->first();

            if (!$plan) {
                return $this->errorResponse('Plan no encontrado', 404);
            }

            if ($plan->tenants_count > 0) {
                $plan->update(['status' => 'INACTIVO']);

                return $this->successResponse(
                    $this->serializePlan(Plan::query()->withCount([
                        'tenants as total_tenants' => fn ($query) => $query->whereIn('status', ['ACTIVO', 'TRIAL']),
                    ])->findOrFail($plan->getKey())),
                    200,
                    'Plan desactivado porque tiene tenants asociados'
                );
            }

            $plan->delete();

            return $this->successResponse(null, 200, 'Plan eliminado');
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    private function validatePlan(Request $request, bool $partial): array
    {
        $sometimes = $partial ? 'sometimes|' : '';

        $validated = $request->validate([
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'price' => $sometimes . 'nullable|numeric|min:0',
            'max_users' => $sometimes . 'nullable|integer|min:0',
            'tier' => $sometimes . 'nullable|string|in:STARTER,PRO,BUSINESS,ENTERPRISE',
            'billing_interval' => $sometimes . 'nullable|string|in:MENSUAL,ANUAL',
            'status' => $sometimes . 'nullable|string|in:ACTIVO,INACTIVO,LEGADO',
            'is_active' => $sometimes . 'boolean',
            'features' => $sometimes . 'nullable|array',
            'features.storage_gb' => 'nullable|numeric|min:0',
            'features.api_calls_month' => 'nullable|integer|min:0',
            'features.modules' => 'nullable|array',
            'features.modules.*' => 'string|max:80',
            'features.custom_domain' => 'nullable|boolean',
            'features.sso' => 'nullable|boolean',
            'features.sso_saml' => 'nullable|boolean',
            'features.advanced_reports' => 'nullable|boolean',
            'features.sla_uptime' => 'nullable|numeric|min:0|max:100',
            'features.support_type' => 'nullable|string|in:EMAIL,EMAIL_CHAT,DEDICADO',
            'features.support' => 'nullable|string|in:Solo Email,Email + Chat,Soporte Dedicado',
            'storage_gb' => $sometimes . 'nullable|numeric|min:0',
            'api_calls_month' => $sometimes . 'nullable|integer|min:0',
            'modules' => $sometimes . 'nullable|array',
            'modules.*' => 'string|max:80',
            'custom_domain' => $sometimes . 'nullable|boolean',
            'sso' => $sometimes . 'nullable|boolean',
            'sso_saml' => $sometimes . 'nullable|boolean',
            'advanced_reports' => $sometimes . 'nullable|boolean',
            'sla_uptime' => $sometimes . 'nullable|numeric|min:0|max:100',
            'support_type' => $sometimes . 'nullable|string|in:EMAIL,EMAIL_CHAT,DEDICADO',
            'support' => $sometimes . 'nullable|string|in:Solo Email,Email + Chat,Soporte Dedicado',
            'max_accounts' => $sometimes . 'nullable|integer|min:0',
            'max_contacts' => $sometimes . 'nullable|integer|min:0',
            'max_entities' => $sometimes . 'nullable|integer|min:0',
            'max_records' => $sometimes . 'nullable|integer|min:0',
        ]);

        if (array_key_exists('is_active', $validated)) {
            $validated['status'] = $validated['is_active'] ? 'ACTIVO' : 'INACTIVO';
            unset($validated['is_active']);
        }

        $features = $validated['features'] ?? [];

        foreach (['storage_gb', 'api_calls_month', 'modules', 'custom_domain', 'sso', 'sso_saml', 'advanced_reports', 'sla_uptime', 'support_type', 'support'] as $key) {
            if (array_key_exists($key, $validated)) {
                $features[$key] = $validated[$key];
                unset($validated[$key]);
            }
        }

        if (array_key_exists('sso_saml', $features) && !array_key_exists('sso', $features)) {
            $features['sso'] = $features['sso_saml'];
        }

        if (array_key_exists('support', $features) && !array_key_exists('support_type', $features)) {
            $features['support_type'] = $this->legacySupportToFrontend($features['support']);
        }

        if (array_key_exists('support_type', $features) && !array_key_exists('support', $features)) {
            $features['support'] = $this->frontendSupportToLegacy($features['support_type']);
        }

        if ($features !== []) {
            $validated['features'] = $features;
        }

        return $validated;
    }

    private function serializePlan(Plan $plan): array
    {
        $features = $plan->features ?? [];
        $supportType = data_get($features, 'support_type') ?? $this->legacySupportToFrontend(data_get($features, 'support'));
        $legacySupport = data_get($features, 'support') ?? $this->frontendSupportToLegacy($supportType);
        $sso = (bool) data_get($features, 'sso', data_get($features, 'sso_saml', false));

        return [
            'uid' => $plan->uid,
            'name' => $plan->name,
            'price' => $plan->price !== null ? (float) $plan->price : null,
            'max_users' => $plan->max_users,
            'tier' => $plan->tier,
            'billing_interval' => $plan->billing_interval,
            'status' => $plan->status,
            'features' => [
                'storage_gb' => data_get($features, 'storage_gb'),
                'api_calls_month' => data_get($features, 'api_calls_month'),
                'modules' => data_get($features, 'modules', []),
                'support_type' => $supportType,
                'support' => $legacySupport,
                'sla_uptime' => data_get($features, 'sla_uptime'),
                'custom_domain' => (bool) data_get($features, 'custom_domain', false),
                'sso' => $sso,
                'sso_saml' => $sso,
                'advanced_reports' => (bool) data_get($features, 'advanced_reports', false),
            ],
            'total_tenants' => (int) ($plan->total_tenants ?? 0),
            'created_at' => optional($plan->created_at)?->toISOString(),
        ];
    }

    private function frontendSupportToLegacy(?string $supportType): ?string
    {
        return match ($supportType) {
            'EMAIL' => 'Solo Email',
            'EMAIL_CHAT' => 'Email + Chat',
            'DEDICADO' => 'Soporte Dedicado',
            default => null,
        };
    }

    private function legacySupportToFrontend(?string $support): ?string
    {
        return match ($support) {
            'Solo Email' => 'EMAIL',
            'Email + Chat' => 'EMAIL_CHAT',
            'Soporte Dedicado' => 'DEDICADO',
            default => null,
        };
    }
}
