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
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'nullable|numeric',
                'max_users' => 'nullable|integer|min:0',
                'tier' => 'nullable|string|in:STARTER,PRO,BUSINESS,ENTERPRISE',
                'billing_interval' => 'nullable|string|in:MENSUAL,ANUAL',
                'status' => 'nullable|string|in:ACTIVO,INACTIVO,LEGADO',
                'features' => 'nullable|array',
                'max_accounts' => 'nullable|integer|min:0',
                'max_contacts' => 'nullable|integer|min:0',
                'max_entities' => 'nullable|integer|min:0',
                'max_records' => 'nullable|integer|min:0',
            ]);

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

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'price' => 'sometimes|nullable|numeric',
                'max_users' => 'sometimes|nullable|integer|min:0',
                'tier' => 'sometimes|nullable|string|in:STARTER,PRO,BUSINESS,ENTERPRISE',
                'billing_interval' => 'sometimes|nullable|string|in:MENSUAL,ANUAL',
                'status' => 'sometimes|nullable|string|in:ACTIVO,INACTIVO,LEGADO',
                'features' => 'sometimes|nullable|array',
                'max_accounts' => 'sometimes|nullable|integer|min:0',
                'max_contacts' => 'sometimes|nullable|integer|min:0',
                'max_entities' => 'sometimes|nullable|integer|min:0',
                'max_records' => 'sometimes|nullable|integer|min:0',
            ]);

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
}
