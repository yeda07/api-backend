<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CreditProfile;
use App\Models\CreditRule;
use App\Models\FinancialRecord;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreditService
{
    public function rules(): array
    {
        $rule = $this->getOrCreateRules();

        return $this->formatRules($rule);
    }

    public function updateRules(array $data): array
    {
        $validated = Validator::make($data, [
            'max_days' => 'required|integer|min:0',
            'max_amount' => 'required|numeric|min:0',
            'auto_block' => 'required|boolean',
        ])->validate();

        $rule = $this->getOrCreateRules();
        $rule->update($validated);

        return $this->formatRules($rule->fresh());
    }

    public function exceptions(): array
    {
        return CreditProfile::query()
            ->with('creditable')
            ->whereIn('creditable_type', [Account::class, Contact::class])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CreditProfile $profile) => $this->formatException($profile))
            ->values()
            ->all();
    }

    public function createException(array $data): CreditProfile
    {
        $validated = $this->validateException($data);
        $client = $this->resolveClient($validated['client_uid']);

        $profile = $this->getOrCreateProfile($client);
        $profile->update([
            'credit_limit' => $validated['credit_limit'],
            'max_days_overdue' => $validated['max_days'],
            'auto_block' => !($validated['is_active'] ?? true),
            'status' => ($validated['is_active'] ?? true) ? 'ok' : 'blocked',
            'meta' => array_merge($profile->meta ?? [], [
                'client_identifier' => $validated['client_identifier'] ?? null,
                'is_credit_exception' => true,
            ]),
        ]);

        return $profile->fresh('creditable');
    }

    public function updateException(string $uid, array $data): CreditProfile
    {
        $profile = CreditProfile::query()->where('uid', $uid)->firstOrFail();
        $validated = $this->validateException($data, true);

        if (!empty($validated['client_uid'])) {
            $client = $this->resolveClient($validated['client_uid']);
            $profile->creditable_type = get_class($client);
            $profile->creditable_id = $client->getKey();
        }

        if (array_key_exists('credit_limit', $validated)) {
            $profile->credit_limit = $validated['credit_limit'];
        }

        if (array_key_exists('max_days', $validated)) {
            $profile->max_days_overdue = $validated['max_days'];
        }

        if (array_key_exists('is_active', $validated)) {
            $profile->auto_block = !$validated['is_active'];
            $profile->status = $validated['is_active'] ? 'ok' : 'blocked';
        }

        $meta = $profile->meta ?? [];
        if (array_key_exists('client_identifier', $validated)) {
            $meta['client_identifier'] = $validated['client_identifier'];
        }
        $meta['is_credit_exception'] = true;
        $profile->meta = $meta;
        $profile->save();

        return $profile->fresh('creditable');
    }

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

    private function getOrCreateRules(): CreditRule
    {
        return CreditRule::query()->firstOrCreate([], [
            'max_days' => 30,
            'max_amount' => 50000,
            'auto_block' => true,
        ]);
    }

    private function formatRules(CreditRule $rule): array
    {
        return [
            'max_days' => (int) $rule->max_days,
            'max_amount' => (float) $rule->max_amount,
            'auto_block' => (bool) $rule->auto_block,
        ];
    }

    public function formatException(CreditProfile $profile): array
    {
        return [
            'uid' => $profile->uid,
            'client_uid' => $profile->creditable_uid,
            'client_name' => $profile->creditable?->display_name
                ?? $profile->creditable?->name
                ?? null,
            'client_identifier' => $profile->meta['client_identifier'] ?? $profile->creditable?->document ?? $profile->creditable?->email,
            'credit_limit' => (float) $profile->credit_limit,
            'max_days' => (int) $profile->max_days_overdue,
            'is_active' => $profile->status !== 'blocked',
        ];
    }

    private function validateException(array $data, bool $partial = false): array
    {
        return Validator::make($data, [
            'client_uid' => [$partial ? 'sometimes' : 'required', 'uuid'],
            'client_identifier' => 'nullable|string|max:255',
            'credit_limit' => [$partial ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'max_days' => [$partial ? 'sometimes' : 'required', 'integer', 'min:0'],
            'is_active' => [$partial ? 'sometimes' : 'required', 'boolean'],
        ])->validate();
    }

    private function resolveClient(string $uid)
    {
        $client = Account::query()->where('uid', $uid)->first()
            ?? Contact::query()->where('uid', $uid)->first();

        if (!$client) {
            throw ValidationException::withMessages([
                'client_uid' => ['El cliente no existe o no pertenece a este tenant'],
            ]);
        }

        return $client;
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
