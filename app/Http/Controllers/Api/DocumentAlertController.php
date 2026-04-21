<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentAlertService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentAlertController extends Controller
{
    public function __construct(private readonly DocumentAlertService $documentAlertService)
    {
    }

    public function index(Request $request)
    {
        try {
            return $this->successResponse($this->documentAlertService->getPendingAlerts($request->all()));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function generate()
    {
        try {
            return $this->successResponse($this->documentAlertService->generateAlerts(), 200, 'Alertas generadas');
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function markAsRead(string $uid)
    {
        try {
            return $this->successResponse($this->documentAlertService->markAsRead($uid), 200, 'Alerta marcada como leida');
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
