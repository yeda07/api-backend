<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Partner;
use App\Models\PartnerOpportunity;
use App\Repositories\PartnerOpportunityRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PartnerOpportunityService
{
    public function __construct(
        private readonly PartnerOpportunityRepository $partnerOpportunityRepository,
        private readonly PartnerService $partnerService,
        private readonly ConflictService $conflictService
    ) {
    }

    public function opportunities(array $filters = [])
    {
        $validated = Validator::make($filters, [
            'partner_uid' => 'nullable|uuid',
            'account_uid' => 'nullable|uuid',
            'status' => 'nullable|string|in:open,won,lost',
        ])->validate();

        return $this->partnerOpportunityRepository->all($validated);
    }

    public function createOpportunity(array $data): PartnerOpportunity
    {
        $validated = $this->validate($data);
        $partner = $this->partnerService->getPartnerByUid($validated['partner_uid']);
        $this->ensurePartnerActive($partner);
        $account = $this->resolveAccount($validated['account_uid']);

        $scope = $validated['conflict_scope'] ?? 'global';
        $this->conflictService->blockDuplicateOpportunity($account, $partner, $scope);

        return $this->partnerOpportunityRepository->create([
            'tenant_id' => auth()->user()->tenant_id,
            'partner_id' => $partner->getKey(),
            'account_id' => $account->getKey(),
            'title' => $validated['title'],
            'status' => $validated['status'] ?? 'open',
            'conflict_scope' => $scope,
            'amount' => $validated['amount'] ?? 0,
            'currency' => $validated['currency'] ?? null,
            'description' => $validated['description'] ?? null,
            'closed_at' => in_array(($validated['status'] ?? 'open'), ['won', 'lost'], true) ? now() : null,
        ]);
    }

    public function getOpportunity(string $uid): PartnerOpportunity
    {
        return $this->partnerOpportunityRepository->findByUid($uid);
    }

    public function closeOpportunity(string $uid, array $data): PartnerOpportunity
    {
        $opportunity = $this->partnerOpportunityRepository->findByUid($uid);
        $validated = Validator::make($data, [
            'status' => 'required|string|in:won,lost',
        ])->validate();

        return $this->partnerOpportunityRepository->update($opportunity, [
            'status' => $validated['status'],
            'closed_at' => now(),
        ]);
    }

    public function checkConflict(array $data): array
    {
        $validated = Validator::make($data, [
            'partner_uid' => 'required|uuid',
            'account_uid' => 'required|uuid',
            'conflict_scope' => 'sometimes|string|in:global,partner',
            'current_opportunity_uid' => 'nullable|uuid',
        ])->validate();

        $partner = $this->partnerService->getPartnerByUid($validated['partner_uid']);
        $account = $this->resolveAccount($validated['account_uid']);

        return $this->conflictService->validateOpportunityConflict(
            $account,
            $partner,
            $validated['conflict_scope'] ?? 'global',
            $validated['current_opportunity_uid'] ?? null
        );
    }

    private function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'partner_uid' => 'required|uuid',
            'account_uid' => 'required|uuid',
            'title' => 'required|string|max:255',
            'status' => 'sometimes|string|in:open,won,lost',
            'conflict_scope' => 'sometimes|string|in:global,partner',
            'amount' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function resolveAccount(string $uid): Account
    {
        $account = Account::query()->where('uid', $uid)->first();

        if (!$account) {
            throw ValidationException::withMessages([
                'account_uid' => ['La cuenta no existe o no es visible para este tenant'],
            ]);
        }

        return $account;
    }

    private function ensurePartnerActive(Partner $partner): void
    {
        if ($partner->status !== 'active') {
            throw ValidationException::withMessages([
                'partner_uid' => ['El partner está inactivo'],
            ]);
        }
    }
}
