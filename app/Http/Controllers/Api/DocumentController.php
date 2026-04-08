<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documentService)
    {
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
                'file' => 'required|file',
            ]);

            return $this->successResponse(
                $this->documentService->upload($validated['entity_type'], $validated['entity_uid'], $request->file('file')),
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
}
