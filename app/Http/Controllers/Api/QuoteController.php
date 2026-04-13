<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Services\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuoteController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
        private readonly InvoiceService $invoiceService
    ) {
    }

    public function index()
    {
        return $this->successResponse($this->quotationService->getAll());
    }

    public function store(Request $request)
    {
        try {
            return $this->successResponse($this->quotationService->create($request->all()), 201, 'Cotizacion creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function addItem(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->quotationService->addItem($uid, $request->all()), 201, 'Item agregado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function approve(string $uid)
    {
        try {
            return $this->successResponse($this->quotationService->update($uid, ['status' => 'approved']), 200, 'Cotizacion aprobada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function reject(string $uid)
    {
        try {
            return $this->successResponse($this->quotationService->update($uid, ['status' => 'rejected']), 200, 'Cotizacion rechazada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function convert(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->invoiceService->createFromQuotation(array_merge(
                $request->all(),
                ['quotation_uid' => $uid]
            )), 201, 'Cotizacion convertida a factura');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
