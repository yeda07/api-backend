<?php

namespace App\Repositories;

use App\Models\DocumentType;

class DocumentTypeRepository
{
    public function query()
    {
        return DocumentType::query()->with('alertRules')->orderBy('name');
    }

    public function all()
    {
        return $this->query()->get();
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
}
