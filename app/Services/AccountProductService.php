<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ProductVersion;
use App\Repositories\AccountProductRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AccountProductService
{
    public function __construct(
        private readonly AccountProductRepository $accountProducts,
        private readonly ProductService $productService
    ) {
    }

    public function getInstalledProducts(string $accountUid)
    {
        $this->findAccount($accountUid);

        return $this->accountProducts->forAccount($accountUid);
    }

    public function assignProductToAccount(string $accountUid, array $data)
    {
        $account = $this->findAccount($accountUid);
        $validated = Validator::make($data, [
            'product_uid' => 'required|uuid',
            'product_version_uid' => 'nullable|uuid',
            'installed_at' => 'nullable|date',
            'status' => 'sometimes|string|in:active,expired,maintenance',
            'notes' => 'nullable|string',
        ])->validate();

        $product = $this->productService->findByUid($validated['product_uid']);
        $version = !empty($validated['product_version_uid']) ? $this->findVersion($validated['product_version_uid'], $product->getKey()) : null;

        return $this->accountProducts->create([
            'account_id' => $account->getKey(),
            'product_id' => $product->getKey(),
            'product_version_id' => $version?->getKey(),
            'installed_at' => $validated['installed_at'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'notes' => $validated['notes'] ?? null,
        ]);
    }

    private function findAccount(string $uid): Account
    {
        return Account::query()->where('uid', $uid)->firstOr(function () {
            throw ValidationException::withMessages([
                'account_uid' => ['La cuenta no existe o no es visible'],
            ]);
        });
    }

    private function findVersion(string $uid, int $productId): ProductVersion
    {
        return ProductVersion::query()
            ->where('uid', $uid)
            ->where('product_id', $productId)
            ->firstOr(function () {
                throw ValidationException::withMessages([
                    'product_version_uid' => ['La version no existe o no pertenece al producto seleccionado'],
                ]);
            });
    }
}
