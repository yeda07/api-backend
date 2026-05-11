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
    private const STATUSES = [
        'pending' => 'Pendiente',
        'validated' => 'Validada',
        'closed' => 'Cerrada',
        'won' => 'Ganada',
        'lost' => 'Perdida',
        'cancelled' => 'Cancelada',
    ];

    public function __construct(
        private readonly PartnerOpportunityRepository $partnerOpportunityRepository,
        private readonly PartnerService $partnerService,
        private readonly ConflictService $conflictService
    ) {
    }

    public function statuses(): array
    {
        return $this->options(self::STATUSES);
    }

    public function opportunities(array $filters = [])
    {
        $filters = $this->normalizeOpportunityPayload($filters);
        $validated = Validator::make($filters, [
            'search' => 'nullable|string|max:255',
            'partner_uid' => 'nullable|uuid',
            'account_uid' => 'nullable|uuid',
            'status' => 'nullable|string|in:open,pending,validated,closed,won,lost,cancelled',
        ])->validate();

        if (($validated['status'] ?? null) === 'pending') {
            $validated['status'] = 'open';
        }

        if (($validated['status'] ?? null) === 'cancelled') {
            $validated['status'] = 'closed';
        }

        return $this->partnerOpportunityRepository->all(array_merge($filters, $validated));
    }

    public function createOpportunity(array $data): PartnerOpportunity
    {
        $data = $this->normalizeOpportunityPayload($data);
        $validated = $this->validate($data);
        $partner = $this->partnerService->getPartnerByUid($validated['partner_uid']);
        $this->ensurePartnerActive($partner);
        $account = $this->resolveOrCreateAccount($validated);

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
            'description' => json_encode([
                'product' => $validated['product'] ?? null,
                'notes' => $validated['notes'] ?? ($validated['description'] ?? null),
            ]),
            'closed_at' => in_array(($validated['status'] ?? 'open'), ['won', 'lost', 'closed'], true) ? now() : null,
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
            'status' => 'sometimes|string|in:closed,won,lost',
        ])->validate();

        return $this->partnerOpportunityRepository->update($opportunity, [
            'status' => $validated['status'] ?? 'closed',
            'closed_at' => now(),
        ]);
    }

    public function validateOpportunities(array $data): array
    {
        $validated = Validator::make($data, [
            'uids' => 'required|array|min:1',
            'uids.*' => 'uuid',
        ])->validate();

        $opportunities = collect($validated['uids'])
            ->map(fn (string $uid) => $this->partnerOpportunityRepository->findByUid($uid));

        foreach ($opportunities as $opportunity) {
            $this->partnerOpportunityRepository->update($opportunity, ['status' => 'validated']);
        }

        return [
            'validated_count' => $opportunities->count(),
            'opportunities' => $opportunities->map(fn (PartnerOpportunity $opportunity) => $opportunity->fresh(['partner', 'account', 'opportunity']))->values(),
        ];
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
            'account_uid' => 'nullable|uuid',
            'client_name' => 'required_without:account_uid|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'title' => 'required|string|max:255',
            'status' => 'sometimes|string|in:open,pending,validated,closed,won,lost',
            'conflict_scope' => 'sometimes|string|in:global,partner',
            'amount' => 'sometimes|numeric|min:0',
            'estimated_value' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'product' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'description' => 'nullable|string',
            'registered_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if (($validated['status'] ?? null) === 'pending') {
            $validated['status'] = 'open';
        }

        return $validated;
    }

    private function options(array $options): array
    {
        return collect($options)
            ->map(fn (string $label, string $value) => [
                'uid' => $this->stableUid($value),
                'key' => $value,
                'name' => $label,
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    private function stableUid(string $key): string
    {
        $hash = md5('partner-opportunity-status:' . $key);

        return substr($hash, 0, 8)
            . '-' . substr($hash, 8, 4)
            . '-' . substr($hash, 12, 4)
            . '-' . substr($hash, 16, 4)
            . '-' . substr($hash, 20, 12);
    }

    private function normalizeOpportunityPayload(array $data): array
    {
        if (array_key_exists('estimated_value', $data) && !array_key_exists('amount', $data)) {
            $data['amount'] = $data['estimated_value'];
        }

        if (array_key_exists('partner_name', $data)) {
            unset($data['partner_name']);
        }

        if (!array_key_exists('title', $data) && !empty($data['client_name'])) {
            $data['title'] = trim(($data['product'] ?? 'Oportunidad') . ' - ' . $data['client_name']);
        }

        return $data;
    }

    private function resolveOrCreateAccount(array $validated): Account
    {
        if (!empty($validated['account_uid'])) {
            return $this->resolveAccount($validated['account_uid']);
        }

        $document = 'PARTNER-' . substr(sha1(($validated['client_email'] ?? '') . $validated['client_name']), 0, 16);

        return Account::query()->firstOrCreate(
            ['tenant_id' => auth()->user()->tenant_id, 'document' => $document],
            [
                'name' => $validated['client_name'],
                'email' => $validated['client_email'] ?? null,
            ]
        );
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
