<?php

namespace App\Services;

use App\Models\Account;
use App\Repositories\ContactRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ContactService
{
    protected $repo;

    public function __construct(ContactRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getAll(array $filters = [])
    {
        return $this->repo->all($filters);
    }

    public function getByUid(string $uid)
    {
        return $this->repo->findByUid($uid);
    }

    public function create(array $data)
    {
        PlanLimitService::check('contacts');

        $this->validate($data);
        $data = $this->normalizeAccountReference($data);

        try {
            return $this->repo->create($data);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'email' => [$e->getMessage()],
            ]);
        }
    }

    public function update(string $uid, array $data)
    {
        $this->validate($data);
        $data = $this->normalizeAccountReference($data);

        try {
            return $this->repo->update($uid, $data);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'email' => [$e->getMessage()],
            ]);
        }
    }

    public function delete(string $uid)
    {
        return $this->repo->delete($uid);
    }

    private function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'first_name' => 'required|string|max:150',
            'last_name' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:100',
            'account_uid' => 'nullable|exists:accounts,uid',
            'account_id' => 'prohibited',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function normalizeAccountReference(array $data): array
    {
        unset($data['account_id']);

        if (array_key_exists('account_uid', $data)) {
            $data['account_id'] = Account::where('uid', $data['account_uid'])->value('id');
            unset($data['account_uid']);
        }

        return $data;
    }
}
