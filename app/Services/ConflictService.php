<?php

namespace App\Services;

use App\Models\Account;
use App\Models\OpportunityConflict;
use App\Models\Partner;
use App\Models\PartnerOpportunity;
use App\Repositories\PartnerOpportunityRepository;
use Illuminate\Validation\ValidationException;

class ConflictService
{
    public function __construct(private readonly PartnerOpportunityRepository $partnerOpportunityRepository)
    {
    }

    public function validateOpportunityConflict(Account $account, Partner $partner, string $scope = 'global', ?string $currentOpportunityUid = null): array
    {
        $active = $this->partnerOpportunityRepository->activeForAccount($account->getKey())
            ->filter(function (PartnerOpportunity $opportunity) use ($partner, $scope, $currentOpportunityUid) {
                if ($currentOpportunityUid && $opportunity->uid === $currentOpportunityUid) {
                    return false;
                }

                return match ($scope) {
                    'partner' => $opportunity->partner_id === $partner->getKey(),
                    default => true,
                };
            })
            ->values();

        return [
            'has_conflict' => $active->isNotEmpty(),
            'scope' => $scope,
            'conflicts' => $active,
        ];
    }

    public function blockDuplicateOpportunity(Account $account, Partner $partner, string $scope = 'global', ?string $currentOpportunityUid = null): void
    {
        $validation = $this->validateOpportunityConflict($account, $partner, $scope, $currentOpportunityUid);

        if (!$validation['has_conflict']) {
            return;
        }

        $conflicting = $validation['conflicts']->first();

        OpportunityConflict::query()->create([
            'tenant_id' => auth()->user()->tenant_id,
            'account_id' => $account->getKey(),
            'partner_opportunity_id' => $currentOpportunityUid
                ? PartnerOpportunity::query()->where('uid', $currentOpportunityUid)->value('id')
                : null,
            'conflicting_partner_opportunity_id' => $conflicting?->getKey(),
            'conflict_reason' => $scope === 'partner'
                ? 'Ya existe una oportunidad activa para este cliente y partner'
                : 'Ya existe una oportunidad activa para este cliente en el canal',
        ]);

        throw ValidationException::withMessages([
            'account_uid' => [$scope === 'partner'
                ? 'Ya existe una oportunidad activa de este partner para el cliente'
                : 'Ya existe una oportunidad activa para este cliente y no se permite duplicarla'],
        ]);
    }
}
