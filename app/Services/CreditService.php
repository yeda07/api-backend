<?php

namespace App\Services;

use App\Models\CreditProfile;
use App\Models\FinancialRecord;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreditService
{
    public function summary(string $entityType, string $entityUid): array
    {
        $entity = $this->resolveEntity($entityType, $entityUid);
        $profile = $this->getOrCreateProfile($entity);
        $summary = $this->summaryForEntity($entity);

        return array_merge([
            'entity_uid' => $entity->uid,
            'status' => $profile->status,
            'credit_limit' => (float) $profile->credit_limit,
            'max_days_overdue' => (int) $profile->max_days_overdue,
            'auto_block' => (bool) $profile->auto_block,
        ], $summary);
    }

    public function updateProfile(string $entityType, string $entityUid, array $data): CreditProfile
    {
        $entity = $this->resolveEntity($entityType, $entityUid);
        $validated = Validator::make($data, [
            'credit_limit' => 'sometimes|numeric|min:0',
            'max_days_overdue' => 'sometimes|integer|min:0',
            'auto_block' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:ok,blocked',
            'meta' => 'nullable|array',
        ])->validate();

        $profile = $this->getOrCreateProfile($entity);
        $profile->update($validated);

        return $profile->fresh();
    }

    public function ensureCanOperate($entity): void
    {
        $profile = $this->getOrCreateProfile($entity);
        $summary = $this->summaryForEntity($entity);
        $isBlockedByOverdue = $profile->auto_block
            && (
                ($profile->max_days_overdue > 0 && $summary['max_days_overdue'] > $profile->max_days_overdue)
                || ($profile->max_days_overdue === 0 && $summary['overdue_total'] > 0)
            );

        $isBlocked = $profile->status === 'blocked'
            || $isBlockedByOverdue
            || ((float) $profile->credit_limit > 0 && $summary['outstanding_total'] > (float) $profile->credit_limit);

        if ($isBlocked) {
            throw ValidationException::withMessages([
                'credit' => ['El cliente esta bloqueado por riesgo de credito'],
            ]);
        }
    }

    private function getOrCreateProfile($entity): CreditProfile
    {
        return CreditProfile::query()->firstOrCreate(
            [
                'creditable_type' => get_class($entity),
                'creditable_id' => $entity->getKey(),
            ],
            [
                'credit_limit' => 0,
                'max_days_overdue' => 0,
                'auto_block' => true,
                'status' => 'ok',
            ]
        );
    }

    private function summaryForEntity($entity): array
    {
        $records = FinancialRecord::query()
            ->where('financeable_type', get_class($entity))
            ->where('financeable_id', $entity->getKey())
            ->get();

        $maxDaysOverdue = (int) $records
            ->where('status', 'overdue')
            ->filter(fn (FinancialRecord $record) => $record->due_at !== null)
            ->map(fn (FinancialRecord $record) => now()->diffInDays($record->due_at, false) * -1)
            ->max();

        return [
            'outstanding_total' => round((float) $records->sum('outstanding_amount'), 2),
            'overdue_total' => round((float) $records->where('status', 'overdue')->sum('outstanding_amount'), 2),
            'has_overdue' => $records->where('status', 'overdue')->isNotEmpty(),
            'max_days_overdue' => max(0, $maxDaysOverdue),
        ];
    }

    private function resolveEntity(string $type, string $uid)
    {
        $entity = find_entity_by_uid($type, $uid);

        if (!$entity) {
            throw ValidationException::withMessages([
                'entity_uid' => ['La entidad no existe o no es visible'],
            ]);
        }

        return $entity;
    }
}
