<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService
    ) {
    }

    public function inventory(Request $request)
    {
        try {
            return $this->successResponse($this->reportService->inventory($request->query()));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function sales(Request $request)
    {
        try {
            return $this->successResponse($this->reportService->sales($request->query()));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function exportSales(Request $request)
    {
        try {
            return $this->reportService->exportSales($request->all());
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function filters()
    {
        return $this->successResponse($this->reportService->filters());
    }

    public function exportInventory(Request $request)
    {
        try {
            return $this->reportService->exportInventory($request->all());
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }
}
