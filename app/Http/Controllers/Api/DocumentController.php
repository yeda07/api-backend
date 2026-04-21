<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentService;
use App\Services\DocumentValidationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documentService,
        private readonly DocumentValidationService $documentValidationService
    ) {
    }

    public function index(string $type, string $uid)
    {
        try {
            return $this->successResponse($this->documentService->getByEntity($type, $uid));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function upload(Request $request)
    {
        try {
            $validated = $request->validate([
                'entity_type' => 'required|string',
                'entity_uid' => 'required|uuid',
                'account_uid' => 'nullable|uuid',
                'document_type_uid' => 'nullable|uuid',
                'issue_date' => 'nullable|date',
                'expiration_date' => 'nullable|date|after_or_equal:issue_date',
                'file' => 'required|file',
            ]);

            return $this->successResponse(
                $this->documentService->upload($validated, $request->file('file')),
                201,
                'Documento subido'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function download(string $uid)
    {
        try {
            $download = $this->documentService->download($uid);
            $document = $download['document'];
            $content = $download['content'];

            return new StreamedResponse(function () use ($content) {
                echo $content;
            }, 200, [
                'Content-Type' => $document->mime_type,
                'Content-Disposition' => 'attachment; filename="' . $document->original_name . '"',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function show(string $uid)
    {
        try {
            return $this->successResponse($this->documentService->getByUid($uid));
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function update(Request $request, string $uid)
    {
        try {
            $validated = $request->validate([
                'entity_type' => 'sometimes|string',
                'entity_uid' => 'sometimes|uuid',
                'account_uid' => 'nullable|uuid',
                'document_type_uid' => 'nullable|uuid',
                'issue_date' => 'nullable|date',
                'expiration_date' => 'nullable|date|after_or_equal:issue_date',
                'file' => 'sometimes|file',
            ]);

            return $this->successResponse(
                $this->documentService->updateDocument($uid, $validated, $request->file('file')),
                200,
                'Documento actualizado'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function accountDocuments(string $accountUid)
    {
        try {
            return $this->successResponse($this->documentService->getByAccount($accountUid));
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function versions(string $uid)
    {
        try {
            return $this->successResponse($this->documentService->getByUid($uid)->versions);
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function missingDocuments(string $accountUid)
    {
        try {
            return $this->successResponse($this->documentValidationService->getMissingDocuments($accountUid));
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
