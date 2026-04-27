<?php

namespace App\Repositories;

use App\Models\User;
use App\Support\ApiIndex;
use Illuminate\Support\Facades\Hash;

class UserRepository
{
    public function getAll(array $filters = [])
    {
        $query = User::query()->with(['roles', 'permissions'])->latest();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['role_uid'])) {
            $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('uid', $filters['role_uid']));
        }

        if (!empty($filters['estado'])) {
            if ($filters['estado'] === 'ACTIVO') {
                $query->where(function ($builder) {
                    $builder->whereNull('locked_until')->orWhere('locked_until', '<=', now());
                });
            }

            if ($filters['estado'] === 'INACTIVO') {
                $query->where('locked_until', '>', now());
            }
        }

        return ApiIndex::paginateOrGet($query, $filters, 'users_page');
    }

    public function findByUid(string $uid)
    {
        return User::query()->with(['roles', 'permissions'])->where('uid', $uid)->first();
    }

    public function create(array $data)
    {
        $data['password'] = Hash::make($data['password']);
        return User::create($data)->fresh(['roles', 'permissions']);
    }

    public function update(string $uid, array $data)
    {
        $user = User::where('uid', $uid)->first();

        if (!$user) {
            return null;
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $user->fresh(['roles', 'permissions']);
    }

    public function delete(string $uid)
    {
        $user = User::where('uid', $uid)->first();

        if (!$user) {
            return false;
        }

        return $user->delete();
    }
}
