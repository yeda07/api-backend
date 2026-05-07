<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Partner;
use App\Repositories\PartnerRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PartnerService
{
    public function __construct(private readonly PartnerRepository $partnerRepository)
    {
    }

    public function getPartners(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'type' => 'nullable|string|in:distributor,reseller,ally',
            'partner_type' => 'nullable|string|in:distributor,reseller,ally',
            'status' => 'nullable|string|in:active,inactive,prospect',
        ])->validate();

        if (!empty($validated['partner_type']) && empty($validated['type'])) {
            $validated['type'] = $validated['partner_type'];
            unset($validated['partner_type']);
        }

        return $this->partnerRepository->all($validated);
    }

    public function createPartner(array $data): Partner
    {
        $data = $this->normalizeFrontendPayload($data);
        $validated = $this->validate($data);

        return $this->partnerRepository->create([
            'tenant_id' => auth()->user()->tenant_id,
            'account_id' => $this->resolveAccountId($validated['account_uid'] ?? null),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'status' => $validated['status'] ?? 'active',
            'contact_info' => $validated['contact_info'] ?? null,
        ]);
    }

    public function updatePartner(string $uid, array $data): Partner
    {
        $partner = $this->partnerRepository->findByUid($uid);
        $data = $this->normalizeFrontendPayload($data);
        $validated = $this->validate($data, true);

        $payload = [];
        foreach (['name', 'type', 'status', 'contact_info'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (array_key_exists('account_uid', $validated)) {
            $payload['account_id'] = $this->resolveAccountId($validated['account_uid']);
        }

        return $this->partnerRepository->update($partner, $payload);
    }

    public function getPartnerByUid(string $uid): Partner
    {
        return $this->partnerRepository->findByUid($uid);
    }

    private function validate(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'type' => [$partial ? 'sometimes' : 'required', 'string', 'in:distributor,reseller,ally'],
            'status' => 'sometimes|string|in:active,inactive,prospect',
            'account_uid' => 'nullable|uuid',
            'contact_info' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function normalizeFrontendPayload(array $data): array
    {
        if (array_key_exists('partner_type', $data) && !array_key_exists('type', $data)) {
            $data['type'] = $data['partner_type'];
        }

        $contactInfo = $data['contact_info'] ?? [];

        foreach (['contact_name', 'contact_email', 'phone', 'region', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $contactInfo[$field] = $data[$field];
            }
        }

        if (!empty($contactInfo)) {
            $data['contact_info'] = $contactInfo;
        }

        unset($data['partner_type'], $data['contact_name'], $data['contact_email'], $data['phone'], $data['region'], $data['notes'], $data['registered_opportunities'], $data['converted_deals'], $data['joined_date']);

        return $data;
    }

    private function resolveAccountId(?string $uid): ?int
    {
        if (!$uid) {
            return null;
        }

        $account = Account::query()->where('uid', $uid)->first();

        if (!$account) {
            throw ValidationException::withMessages([
                'account_uid' => ['La cuenta no existe o no pertenece a este tenant'],
            ]);
        }

        return $account->getKey();
    }
}
