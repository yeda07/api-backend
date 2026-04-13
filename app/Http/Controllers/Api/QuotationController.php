<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuotationDeliveryService;
use App\Services\QuotationPdfService;
use App\Services\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
        private readonly QuotationPdfService $quotationPdfService,
        private readonly QuotationDeliveryService $quotationDeliveryService
    ) {
    }

    public function index()
    {
        return $this->successResponse($this->quotationService->getAll());
    }

    public function show(string $uid)
    {
        return $this->successResponse($this->quotationService->getByUid($uid));
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

    public function update(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->quotationService->update($uid, $request->all()), 200, 'Cotizacion actualizada');
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

    public function updateItem(Request $request, string $itemUid)
    {
        try {
            return $this->successResponse($this->quotationService->updateItem($itemUid, $request->all()), 200, 'Item actualizado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroyItem(string $itemUid)
    {
        try {
            $this->quotationService->deleteItem($itemUid);

            return $this->successResponse(null, 200, 'Item eliminado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function reserveItemStock(Request $request, string $itemUid)
    {
        try {
            return $this->successResponse($this->quotationService->reserveItemStock($itemUid, $request->all()), 201, 'Stock reservado desde cotizacion');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function downloadPdf(string $uid)
    {
        try {
            $quotation = $this->quotationService->getByUid($uid);
            $pdfContent = $this->quotationPdfService->render($quotation);
            $filename = $this->quotationPdfService->filename($quotation);

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function sendPdf(Request $request, string $uid)
    {
        try {
            $quotation = $this->quotationService->getByUid($uid);

            return $this->successResponse(
                $this->quotationDeliveryService->send($quotation, $request->all()),
                200,
                'Cotizacion enviada'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function releaseItemReservation(string $itemUid, string $reservationUid)
    {
        try {
            return $this->successResponse($this->quotationService->releaseItemReservation($itemUid, $reservationUid), 200, 'Reserva liberada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
