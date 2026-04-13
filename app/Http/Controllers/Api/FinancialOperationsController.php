<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CreditService;
use App\Services\FinancialOperationsService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FinancialOperationsController extends Controller
{
    public function __construct(
        private readonly FinancialOperationsService $financialOperationsService,
        private readonly InvoiceService $invoiceService,
        private readonly PaymentService $paymentService,
        private readonly CreditService $creditService
    ) {
    }

    public function index(Request $request)
    {
        try {
            return $this->successResponse($this->financialOperationsService->records($request->query()));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function import(Request $request)
    {
        try {
            return $this->successResponse($this->financialOperationsService->importRecord($request->all()), 201, 'Registro financiero sincronizado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function customerSummary(string $type, string $uid)
    {
        try {
            return $this->successResponse($this->financialOperationsService->customerSummary($type, $uid));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function dashboard()
    {
        return $this->successResponse($this->financialOperationsService->dashboardSummary());
    }

    public function alerts()
    {
        return $this->successResponse($this->financialOperationsService->alerts());
    }

    public function invoices(Request $request)
    {
        try {
            return $this->successResponse($this->invoiceService->list($request->query()));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function createInvoice(Request $request)
    {
        try {
            return $this->successResponse($this->invoiceService->createFromQuotation($request->all()), 201, 'Factura generada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function payments(Request $request)
    {
        return $this->successResponse($this->paymentService->list($request->query('invoice_uid')));
    }

    public function paymentHistory(string $invoiceUid)
    {
        return $this->successResponse($this->paymentService->list($invoiceUid));
    }

    public function registerPayment(Request $request)
    {
        try {
            return $this->successResponse($this->paymentService->register($request->all()), 201, 'Pago registrado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function creditSummary(string $type, string $uid)
    {
        try {
            return $this->successResponse($this->creditService->summary($type, $uid));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function updateCreditProfile(Request $request, string $type, string $uid)
    {
        try {
            return $this->successResponse($this->creditService->updateProfile($type, $uid, $request->all()), 200, 'Perfil de credito actualizado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        }
    }

    public function syncOverdueInvoices()
    {
        return $this->successResponse($this->invoiceService->syncOverdue(), 200, 'Facturas vencidas sincronizadas');
    }
}
