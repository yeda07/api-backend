<?php

namespace App\Services;

use App\Repositories\UserRepository;

class UserService
{
    protected $repo;

    public function __construct(UserRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getAll()
    {
        return $this->repo->getAll();
    }

    public function findByUid(string $uid)
    {
        return $this->repo->findByUid($uid);
    }

    public function create(array $data)
    {
        return $this->repo->create($data);
    }

    public function update(string $uid, array $data)
    {
        return $this->repo->update($uid, $data);
    }

    public function delete(string $uid)
    {
        return $this->repo->delete($uid);
    }
}
