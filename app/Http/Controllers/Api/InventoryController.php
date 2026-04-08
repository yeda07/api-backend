<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function master(Request $request)
    {
        try {
            return $this->successResponse($this->inventoryService->master($request->query()));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function adjust(Request $request)
    {
        try {
            return $this->successResponse($this->inventoryService->adjustStock($request->all()), 200, 'Stock ajustado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function reserve(Request $request)
    {
        try {
            return $this->successResponse($this->inventoryService->reserveStock($request->all()), 201, 'Stock reservado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function releaseReservation(string $uid)
    {
        try {
            return $this->successResponse($this->inventoryService->releaseReservation($uid), 200, 'Reserva liberada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function reservationsBySource(string $sourceType, string $sourceUid)
    {
        return $this->successResponse($this->inventoryService->reservationsBySource($sourceType, $sourceUid));
    }

    public function transfer(Request $request)
    {
        try {
            return $this->successResponse($this->inventoryService->transferStock($request->all()), 200, 'Movimiento realizado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function report(Request $request)
    {
        try {
            return $this->successResponse($this->inventoryService->report($request->query()));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function exportReport(Request $request)
    {
        try {
            $csv = $this->inventoryService->reportAsCsv($request->query());

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="inventory-report.csv"',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }
}
