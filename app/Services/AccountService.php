<?php

namespace App\Services;

use App\Models\Account;
use App\Repositories\AccountRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AccountService
{
    protected $repo;

    public function __construct(AccountRepository $repo)
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
        PlanLimitService::check('accounts');

        $this->validate($data);
        $this->validateDuplicates($data);

        return $this->repo->create($this->sanitize($data));
    }

    public function update(string $uid, array $data)
    {
        $account = $this->repo->findByUid($uid);

        $this->validate($data);
        $this->validateDuplicates($data, $account->id);

        return $this->repo->update($uid, $this->sanitize($data));
    }

    public function delete(string $uid)
    {
        return $this->repo->delete($uid);
    }

    private function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:200',
            'document' => 'required|string|max:50',
            'email' => 'nullable|email|max:150',
            'industry' => 'nullable|string|max:150',
            'website' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function validateDuplicates(array $data, ?int $ignoreId = null): void
    {
        $tenantId = auth()->user()->tenant_id;

        $query = Account::where('tenant_id', $tenantId)
            ->where('document', $data['document']);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'document' => ['Ya existe un cliente con este documento'],
            ]);
        }

        if (!empty($data['email'])) {
            $query = Account::where('tenant_id', $tenantId)
                ->where('email', $data['email']);

            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['Ya existe un cliente con este email'],
                ]);
            }
        }
    }

    private function sanitize(array $data): array
    {
        return [
            'name' => $data['name'] ?? null,
            'document' => $data['document'] ?? null,
            'email' => $data['email'] ?? null,
            'industry' => $data['industry'] ?? null,
            'website' => $data['website'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ];
    }
}
