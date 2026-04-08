<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PriceBookService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PriceBookController extends Controller
{
    public function __construct(private readonly PriceBookService $priceBookService)
    {
    }

    public function index()
    {
        return $this->successResponse($this->priceBookService->getAll());
    }

    public function show(string $uid)
    {
        return $this->successResponse($this->priceBookService->getByUid($uid));
    }

    public function store(Request $request)
    {
        try {
            return $this->successResponse($this->priceBookService->create($request->all()), 201, 'Lista de precios creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function update(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->priceBookService->update($uid, $request->all()), 200, 'Lista de precios actualizada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroy(string $uid)
    {
        try {
            $this->priceBookService->delete($uid);

            return $this->successResponse(null, 200, 'Lista de precios eliminada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
