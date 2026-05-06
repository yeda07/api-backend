<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinancialDashboardService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly FinancialDashboardService $financialDashboardService
    ) {
    }

    public function inventory(Request $request)
    {
        try {
            return $this->successResponse($this->inventoryService->report($request->query()));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function sales()
    {
        return $this->successResponse($this->financialDashboardService->dashboard());
    }
}
