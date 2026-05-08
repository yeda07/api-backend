<?php

namespace App\Repositories;

use App\Models\DocumentType;
use App\Support\ApiIndex;

class DocumentTypeRepository
{
    public function query()
    {
        return DocumentType::query()->with('alertRules')->orderBy('name');
    }

    public function all(array $filters = [])
    {
        return ApiIndex::paginateOrGet($this->query(), $filters, 'document_types_page');
    }

    public function activeRequired()
    {
        return DocumentType::query()
            ->where('is_active', true)
            ->where('is_required', true)
            ->orderBy('name')
            ->get();
    }

    public function findByUid(string $uid): DocumentType
    {
        return $this->query()->where('uid', $uid)->firstOrFail();
    }

    public function create(array $data): DocumentType
    {
        return DocumentType::query()->create($data)->fresh('alertRules');
    }

    public function update(DocumentType $documentType, array $data): DocumentType
    {
        $documentType->update($data);

        return $documentType->fresh('alertRules');
    }

    public function delete(DocumentType $documentType): void
    {
        $documentType->delete();
    }
}
