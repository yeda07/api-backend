<?php

namespace App\Repositories;

use App\Models\Document;
use App\Models\DocumentVersion;

class DocumentVersionRepository
{
    public function createFromDocument(Document $document, bool $isActive = true): DocumentVersion
    {
        return DocumentVersion::query()->create([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->getKey(),
            'created_by_user_id' => auth()->id(),
            'version_number' => $document->version_number,
            'disk' => $document->disk,
            'file_path' => $document->path,
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'issue_date' => $document->issue_date,
            'expiration_date' => $document->expiration_date,
            'status' => $document->status,
            'is_active' => $isActive,
        ])->fresh(['document', 'createdBy']);
    }

    public function markCurrentAsReplaced(Document $document): void
    {
        DocumentVersion::query()
            ->where('document_id', $document->getKey())
            ->where('version_number', $document->version_number)
            ->where('is_active', true)
            ->update([
                'status' => 'replaced',
                'is_active' => false,
            ]);
    }

    public function syncCurrent(Document $document): void
    {
        DocumentVersion::query()
            ->where('document_id', $document->getKey())
            ->where('version_number', $document->version_number)
            ->update([
                'disk' => $document->disk,
                'file_path' => $document->path,
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'issue_date' => $document->issue_date,
                'expiration_date' => $document->expiration_date,
                'status' => $document->status,
                'is_active' => $document->is_active,
            ]);
    }
}
