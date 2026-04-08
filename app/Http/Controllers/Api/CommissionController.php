<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CommissionController extends Controller
{
    public function __construct(private readonly CommissionService $commissionService)
    {
    }

    public function rules()
    {
        return $this->successResponse($this->commissionService->rules());
    }

    public function storeRule(Request $request)
    {
        try {
            return $this->successResponse($this->commissionService->createRule($request->all()), 201, 'Regla de comision creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function updateRule(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->commissionService->updateRule($uid, $request->all()), 200, 'Regla de comision actualizada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroyRule(string $uid)
    {
        try {
            $this->commissionService->deleteRule($uid);

            return $this->successResponse(null, 200, 'Regla de comision eliminada');
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function recordFinancialEvent(Request $request)
    {
        try {
            return $this->successResponse($this->commissionService->recordFinancialEvent($request->all()), 201, 'Recaudo registrado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function entries(Request $request)
    {
        return $this->successResponse($this->commissionService->entries($request->query('user_uid')));
    }

    public function payEntry(Request $request, string $uid)
    {
        try {
            return $this->successResponse(
                $this->commissionService->payEntry($uid, $request->input('paid_at')),
                200,
                'Comision marcada como pagada'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function mySummary()
    {
        return $this->successResponse($this->commissionService->mySummary());
    }
}
