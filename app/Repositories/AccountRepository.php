<?php

namespace App\Repositories;

use App\Support\ApiIndex;
use App\Models\Account;
use Exception;

class AccountRepository
{
    public function all(array $filters = [])
    {
        return ApiIndex::paginateOrGet(
            Account::query()->orderBy('name'),
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
