<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenseService)
    {
    }

    public function categories()
    {
        return $this->successResponse($this->expenseService->categories());
    }

    public function storeCategory(Request $request)
    {
        return $this->wrap(fn () => $this->expenseService->createCategory($request->all()), 'Categoria de gasto creada', 201);
    }

    public function updateCategory(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->expenseService->updateCategory($uid, $request->all()), 'Categoria de gasto actualizada');
    }

    public function destroyCategory(string $uid)
    {
        return $this->wrap(function () use ($uid) {
            $this->expenseService->deleteCategory($uid);
            return null;
        }, 'Categoria de gasto eliminada');
    }

    public function suppliers()
    {
        return $this->successResponse($this->expenseService->suppliers());
    }

    public function costCenters()
    {
        return $this->successResponse($this->expenseService->costCenters());
    }

    public function storeSupplier(Request $request)
    {
        return $this->wrap(fn () => $this->expenseService->createSupplier($request->all()), 'Proveedor creado', 201);
    }

    public function updateSupplier(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->expenseService->updateSupplier($uid, $request->all()), 'Proveedor actualizado');
    }

    public function destroySupplier(string $uid)
    {
        return $this->wrap(function () use ($uid) {
            $this->expenseService->deleteSupplier($uid);
            return null;
        }, 'Proveedor eliminado');
    }

    public function storeCostCenter(Request $request)
    {
        return $this->wrap(fn () => $this->expenseService->createCostCenter($request->all()), 'Centro de costo creado', 201);
    }

    public function updateCostCenter(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->expenseService->updateCostCenter($uid, $request->all()), 'Centro de costo actualizado');
    }

    public function destroyCostCenter(string $uid)
    {
        return $this->wrap(function () use ($uid) {
            $this->expenseService->deleteCostCenter($uid);
            return null;
        }, 'Centro de costo eliminado');
    }

    public function index(Request $request)
    {
        return $this->successResponse($this->expenseService->index($request->query()));
    }

    public function store(Request $request)
    {
        return $this->wrap(fn () => $this->expenseService->create($request->all()), 'Gasto creado', 201);
    }

    public function update(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->expenseService->update($uid, $request->all()), 'Gasto actualizado');
    }

    public function destroy(string $uid)
    {
        return $this->wrap(function () use ($uid) {
            $this->expenseService->delete($uid);
            return null;
        }, 'Gasto eliminado');
    }

    public function report(Request $request)
    {
        return $this->successResponse($this->expenseService->report($request->query()));
    }

    public function profitability(Request $request)
    {
        return $this->successResponse($this->expenseService->profitability($request->query()));
    }

    private function wrap(\Closure $callback, ?string $message = null, int $status = 200)
    {
        try {
            return $this->successResponse($callback(), $status, $message);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
