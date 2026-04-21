<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductVersionRepository;
use Illuminate\Support\Facades\Validator;

class ProductVersionService
{
    public function __construct(private readonly ProductVersionRepository $versions)
    {
    }

    public function createVersion(Product $product, array $data)
    {
        $validated = Validator::make($data, [
            'version' => 'required|string|max:50',
            'release_date' => 'nullable|date',
            'status' => 'sometimes|string|in:active,deprecated',
            'notes' => 'nullable|string',
        ])->validate();

        return $this->versions->create([
            'product_id' => $product->getKey(),
            'version' => $validated['version'],
            'release_date' => $validated['release_date'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'notes' => $validated['notes'] ?? null,
        ]);
    }

    public function updateVersion(string $uid, array $data)
    {
        $version = $this->versions->findByUid($uid);
        $validated = Validator::make($data, [
            'version' => 'sometimes|string|max:50',
            'release_date' => 'nullable|date',
            'status' => 'sometimes|string|in:active,deprecated',
            'notes' => 'nullable|string',
        ])->validate();

        return $this->versions->update($version, $validated);
    }
}
