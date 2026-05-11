<?php

namespace App\Repositories;

use App\Support\ApiIndex;
use App\Models\Account;
use Exception;

class AccountRepository
{
    public function all(array $filters = [])
    {
        $query = Account::query()->orderBy('name');

        if (!empty($filters['search'])) {
            $search = '%' . mb_strtolower($filters['search']) . '%';
            $query->where(function ($builder) use ($search) {
                $builder
                    ->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(document) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$search]);
            });
        }

        return ApiIndex::paginateOrGet(
            $query,
            $filters,
            'accounts_page'
        );
    }

    public function findByUid(string $uid)
    {
        return Account::where('uid', $uid)->firstOrFail();
    }

    public function create(array $data)
    {
        try {
            $data['tenant_id'] = auth()->user()->tenant_id;

            return Account::create($data);
        } catch (Exception $e) {
            throw new Exception('Error creating account: ' . $e->getMessage());
        }
    }

    public function update(string $uid, array $data)
    {
        $account = $this->findByUid($uid);

        try {
            $account->update($data);
            return $account;
        } catch (Exception $e) {
            throw new Exception('Error updating account: ' . $e->getMessage());
        }
    }

    public function delete(string $uid)
    {
        $account = $this->findByUid($uid);

        try {
            $account->delete();
            return true;
        } catch (Exception $e) {
            throw new Exception('Error deleting account: ' . $e->getMessage());
        }
    }
}
