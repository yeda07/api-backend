<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryCategoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index()
    {
        return $this->successResponse($this->inventoryService->listCategories());
    }

    public function store(Request $request)
    {
        try {
            return $this->successResponse($this->inventoryService->createCategory($request->all()), 201, 'Categoria creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function update(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->inventoryService->updateCategory($uid, $request->all()), 200, 'Categoria actualizada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroy(string $uid)
    {
        try {
            $this->inventoryService->deleteCategory($uid);

            return $this->successResponse(null, 200, 'Categoria eliminada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
