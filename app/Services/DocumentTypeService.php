<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Repositories\AlertRuleRepository;
use App\Repositories\DocumentTypeRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocumentTypeService
{
    public function __construct(
        private readonly DocumentTypeRepository $documentTypeRepository,
        private readonly AlertRuleRepository $alertRuleRepository
    ) {
    }

    public function getTypes()
    {
        return $this->documentTypeRepository->all();
    }

    public function createType(array $data): DocumentType
    {
        $validated = $this->validate($data);

        return DB::transaction(function () use ($validated) {
            $documentType = $this->documentTypeRepository->create([
                'tenant_id' => auth()->user()->tenant_id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'validity_days' => $validated['validity_days'] ?? null,
                'is_required' => $validated['is_required'] ?? false,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (array_key_exists('alert_rules', $validated)) {
                $this->alertRuleRepository->upsertForType($documentType->getKey(), $validated['alert_rules']);
            }

            return $documentType->fresh('alertRules');
        });
    }

    public function updateType(string $uid, array $data): DocumentType
    {
        $documentType = $this->documentTypeRepository->findByUid($uid);
        $validated = $this->validate($data, true);

        return DB::transaction(function () use ($documentType, $validated) {
            $updated = $this->documentTypeRepository->update($documentType, $validated);

            if (array_key_exists('alert_rules', $validated)) {
                $this->alertRuleRepository->upsertForType($documentType->getKey(), $validated['alert_rules']);
            }

            return $updated->fresh('alertRules');
        });
    }

    private function validate(array $data, bool $partial = false): array
    {
        return Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'validity_days' => ['nullable', 'integer', 'min:1'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'alert_rules' => ['sometimes', 'array'],
            'alert_rules.*.days_before' => ['required_with:alert_rules', 'integer', 'min:0'],
            'alert_rules.*.notification_channel' => ['nullable', 'string', 'max:50'],
            'alert_rules.*.is_active' => ['sometimes', 'boolean'],
        ])->validate();
    }
}
