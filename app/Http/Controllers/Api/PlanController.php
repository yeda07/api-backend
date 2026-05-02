<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    public function index()
    {
        return $this->successResponse(
            Plan::query()
                ->withCount('tenants as total_tenants')
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validatePlan($request, false);

            $plan = Plan::query()->create($validated);

            return $this->successResponse(
                Plan::query()->withCount('tenants as total_tenants')->find($plan->getKey()),
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
                Plan::query()->withCount('tenants as total_tenants')->find($plan->getKey()),
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
                    Plan::query()->withCount('tenants as total_tenants')->find($plan->getKey()),
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
            'features.sso_saml' => 'nullable|boolean',
            'features.advanced_reports' => 'nullable|boolean',
            'features.support' => 'nullable|string|in:Solo Email,Email + Chat,Soporte Dedicado',
            'storage_gb' => $sometimes . 'nullable|numeric|min:0',
            'api_calls_month' => $sometimes . 'nullable|integer|min:0',
            'modules' => $sometimes . 'nullable|array',
            'modules.*' => 'string|max:80',
            'custom_domain' => $sometimes . 'nullable|boolean',
            'sso_saml' => $sometimes . 'nullable|boolean',
            'advanced_reports' => $sometimes . 'nullable|boolean',
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

        foreach (['storage_gb', 'api_calls_month', 'modules', 'custom_domain', 'sso_saml', 'advanced_reports', 'support'] as $key) {
            if (array_key_exists($key, $validated)) {
                $features[$key] = $validated[$key];
                unset($validated[$key]);
            }
        }

        if ($features !== []) {
            $validated['features'] = $features;
        }

        return $validated;
    }
}
