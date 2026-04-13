<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $purchaseOrderService)
    {
    }

    public function index(Request $request)
    {
        return $this->successResponse($this->purchaseOrderService->index($request->query()));
    }

    public function show(string $uid)
    {
        return $this->successResponse($this->purchaseOrderService->show($uid));
    }

    public function receipts(string $uid)
    {
        return $this->successResponse($this->purchaseOrderService->receipts($uid));
    }

    public function store(Request $request)
    {
        return $this->wrap(fn () => $this->purchaseOrderService->create($request->all()), 'Orden de compra creada', 201);
    }

    public function update(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->purchaseOrderService->update($uid, $request->all()), 'Orden de compra actualizada');
    }

    public function approve(string $uid)
    {
        return $this->wrap(fn () => $this->purchaseOrderService->approve($uid), 'Orden de compra aprobada');
    }

    public function markReceived(string $uid)
    {
        return $this->wrap(fn () => $this->purchaseOrderService->markReceived($uid), 'Orden de compra recibida');
    }

    public function receivePartial(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->purchaseOrderService->receivePartial($uid, $request->all()), 'Recepcion parcial registrada');
    }

    public function registerPayment(Request $request, string $uid)
    {
        return $this->wrap(fn () => $this->purchaseOrderService->registerPayment($uid, $request->all()), 'Pago de orden de compra registrado', 201);
    }

    public function payables(Request $request)
    {
        return $this->successResponse($this->purchaseOrderService->payables($request->query()));
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
