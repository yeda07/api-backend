<?php

namespace App\Repositories;

use App\Models\AlertRule;

class AlertRuleRepository
{
    public function query()
    {
        return AlertRule::query()->with('documentType')->orderBy('days_before', 'desc');
    }

    public function activeForType(int $documentTypeId)
    {
        return $this->query()
            ->where('document_type_id', $documentTypeId)
            ->where('is_active', true)
            ->get();
    }

    public function maxDaysBeforeForType(?int $documentTypeId): int
    {
        if (!$documentTypeId) {
            return 0;
        }

        return (int) AlertRule::query()
            ->where('document_type_id', $documentTypeId)
            ->where('is_active', true)
            ->max('days_before');
    }

    public function upsertForType(int $documentTypeId, array $rules): void
    {
        AlertRule::query()
            ->where('document_type_id', $documentTypeId)
            ->delete();

        foreach ($rules as $rule) {
            AlertRule::query()->create([
                'tenant_id' => auth()->user()->tenant_id,
                'document_type_id' => $documentTypeId,
                'days_before' => $rule['days_before'],
                'notification_channel' => $rule['notification_channel'] ?? 'system',
                'is_active' => $rule['is_active'] ?? true,
            ]);
        }
    }
}
