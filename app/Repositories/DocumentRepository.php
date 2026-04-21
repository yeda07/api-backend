<?php

namespace App\Repositories;

use App\Models\Document;

class DocumentRepository
{
    public function query()
    {
        return Document::query()->with([
            'owner',
            'uploadedBy',
            'documentable',
            'account',
            'documentType',
            'versions',
            'alerts.alertRule',
        ]);
    }

    public function findByUid(string $uid): Document
    {
        return $this->query()->where('uid', $uid)->firstOrFail();
    }

    public function forEntity(object $entity)
    {
        return $this->query()
            ->where('documentable_type', get_class($entity))
            ->where('documentable_id', $entity->getKey())
            ->latest()
            ->get();
    }

    public function forAccount(int $accountId)
    {
        return $this->query()
            ->where('account_id', $accountId)
            ->orderByDesc('expiration_date')
            ->orderBy('document_type_id')
            ->get();
    }

    public function findByAccountAndType(int $accountId, int $documentTypeId): ?Document
    {
        return Document::query()
            ->where('account_id', $accountId)
            ->where('document_type_id', $documentTypeId)
            ->first();
    }

    public function expirable()
    {
        return $this->query()
            ->whereNotNull('document_type_id')
            ->whereNotNull('account_id')
            ->whereNotNull('expiration_date')
            ->where('is_active', true)
            ->get();
    }

    public function create(array $data): Document
    {
        return Document::query()->create($data)->fresh([
            'owner',
            'uploadedBy',
            'documentable',
            'account',
            'documentType',
            'versions',
        ]);
    }

    public function update(Document $document, array $data): Document
    {
        $document->update($data);

        return $document->fresh([
            'owner',
            'uploadedBy',
            'documentable',
            'account',
            'documentType',
            'versions',
            'alerts.alertRule',
        ]);
    }
}
