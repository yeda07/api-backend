<?php

namespace App\Repositories;

use App\Models\DocumentAlert;

class DocumentAlertRepository
{
    public function query()
    {
        return DocumentAlert::query()->with(['document.account', 'document.documentType', 'alertRule'])->latest('alert_date');
    }

    public function pending(array $filters = [])
    {
        $query = $this->query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['account_uid'])) {
            $query->whereHas('document.account', fn ($builder) => $builder->where('uid', $filters['account_uid']));
        }

        if (!empty($filters['state'])) {
            $query->whereHas('document', fn ($builder) => $builder->where('status', $filters['state']));
        }

        return $query->get();
    }

    public function findOpen(int $documentId, ?int $ruleId, string $alertDate): ?DocumentAlert
    {
        return DocumentAlert::query()
            ->where('document_id', $documentId)
            ->where('alert_rule_id', $ruleId)
            ->whereDate('alert_date', $alertDate)
            ->whereIn('status', ['pending', 'sent'])
            ->first();
    }

    public function create(array $data): DocumentAlert
    {
        return DocumentAlert::query()->create($data)->fresh(['document.account', 'document.documentType', 'alertRule']);
    }
}
