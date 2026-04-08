<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserRepository
{
    public function getAll()
    {
        return User::all();
    }

    public function findByUid(string $uid)
    {
        return User::where('uid', $uid)->first();
    }

    public function create(array $data)
    {
        $data['password'] = Hash::make($data['password']);
        return User::create($data);
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

        return $user;
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
