<?php

namespace App\Repositories;

use App\Models\PartnerOpportunity;

class PartnerOpportunityRepository
{
    public function query()
    {
        return PartnerOpportunity::query()->with(['partner', 'account', 'opportunity'])->latest();
    }

    public function all(array $filters = [])
    {
        return $this->query()
            ->when(!empty($filters['partner_uid']), function ($query) use ($filters) {
                $query->whereHas('partner', fn ($builder) => $builder->where('uid', $filters['partner_uid']));
            })
            ->when(!empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(!empty($filters['account_uid']), function ($query) use ($filters) {
                $query->whereHas('account', fn ($builder) => $builder->where('uid', $filters['account_uid']));
            })
            ->get();
    }

    public function findByUid(string $uid): PartnerOpportunity
    {
        return $this->query()->where('uid', $uid)->firstOrFail();
    }

    public function activeForAccount(int $accountId)
    {
        return $this->query()
            ->where('account_id', $accountId)
            ->where('status', 'open')
            ->get();
    }

    public function create(array $data): PartnerOpportunity
    {
        return PartnerOpportunity::query()->create($data)->fresh(['partner', 'account', 'opportunity']);
    }

    public function update(PartnerOpportunity $opportunity, array $data): PartnerOpportunity
    {
        $opportunity->update($data);

        return $opportunity->fresh(['partner', 'account', 'opportunity']);
    }
}
