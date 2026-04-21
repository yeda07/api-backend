<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentTypeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentTypeController extends Controller
{
    public function __construct(private readonly DocumentTypeService $documentTypeService)
    {
    }

    public function index()
    {
        return $this->successResponse($this->documentTypeService->getTypes());
    }

    public function store(Request $request)
    {
        try {
            return $this->successResponse($this->documentTypeService->createType($request->all()), 201, 'Tipo de documento creado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function update(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->documentTypeService->updateType($uid, $request->all()), 200, 'Tipo de documento actualizado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
