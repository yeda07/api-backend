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
            'status' => 'nullable|string|in:active,inactive',
        ])->validate();

        return $this->partnerRepository->all($validated);
    }

    public function createPartner(array $data): Partner
    {
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
            'status' => 'sometimes|string|in:active,inactive',
            'account_uid' => 'nullable|uuid',
            'contact_info' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
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
