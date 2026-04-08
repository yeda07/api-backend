<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    public function getByEntity(string $entityType, string $entityUid)
    {
        $entity = $this->resolveEntity($entityType, $entityUid);

        return Document::query()
            ->where('documentable_type', get_class($entity))
            ->where('documentable_id', $entity->getKey())
            ->latest()
            ->get();
    }

    public function upload(string $entityType, string $entityUid, UploadedFile $file): Document
    {
        $entity = $this->resolveEntity($entityType, $entityUid);
        $this->validateFile($file);

        $disk = config('filesystems.default', 'local');
        $path = $file->store('documents/' . auth()->user()->tenant_id, $disk);

        return Document::query()->create([
            'owner_user_id' => $entity->owner_user_id ?? auth()->id(),
            'uploaded_by_user_id' => auth()->id(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/pdf',
            'size' => $file->getSize(),
            'documentable_type' => get_class($entity),
            'documentable_id' => $entity->getKey(),
        ])->fresh(['owner', 'uploadedBy', 'documentable']);
    }

    public function download(string $uid): array
    {
        $document = Document::query()->where('uid', $uid)->firstOrFail();

        if (!Storage::disk($document->disk)->exists($document->path)) {
            throw ValidationException::withMessages([
                'document' => ['El archivo no existe en el almacenamiento'],
            ]);
        }

        return [
            'document' => $document,
            'content' => Storage::disk($document->disk)->get($document->path),
        ];
    }

    private function validateFile(UploadedFile $file): void
    {
        $mime = $file->getMimeType();

        if ($mime !== 'application/pdf') {
            throw ValidationException::withMessages([
                'file' => ['Solo se permiten archivos PDF'],
            ]);
        }
    }

    private function resolveEntity(string $entityType, string $entityUid)
    {
        $entity = find_entity_by_uid($entityType, $entityUid);

        if (!$entity) {
            throw ValidationException::withMessages([
                'entity_uid' => ['La entidad no existe o no es visible para este usuario'],
            ]);
        }

        return $entity;
    }
}
