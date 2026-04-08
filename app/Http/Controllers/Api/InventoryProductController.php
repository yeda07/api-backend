<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryProductController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index()
    {
        return $this->successResponse($this->inventoryService->listProducts());
    }

    public function store(Request $request)
    {
        try {
            return $this->successResponse($this->inventoryService->createProduct($request->all()), 201, 'Producto creado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function update(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->inventoryService->updateProduct($uid, $request->all()), 200, 'Producto actualizado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroy(string $uid)
    {
        try {
            $this->inventoryService->deleteProduct($uid);

            return $this->successResponse(null, 200, 'Producto eliminado');
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
