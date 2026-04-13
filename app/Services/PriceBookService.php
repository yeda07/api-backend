<?php

namespace App\Services;

use App\Models\InventoryProduct;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PriceBookService
{
    public function getAll()
    {
        return PriceBook::query()->with(['items.product'])->orderBy('name')->get();
    }

    public function getByUid(string $uid): PriceBook
    {
        return PriceBook::query()->with(['items.product'])->where('uid', $uid)->firstOrFail();
    }

    public function create(array $data): PriceBook
    {
        $validated = $this->validatePriceBook($data);

        return DB::transaction(function () use ($validated) {
            $priceBook = PriceBook::query()->create([
                'name' => $validated['name'],
                'key' => $validated['key'],
                'channel' => $validated['channel'] ?? 'B2B',
                'is_active' => $validated['is_active'] ?? true,
                'valid_from' => $validated['valid_from'] ?? null,
                'valid_until' => $validated['valid_until'] ?? null,
            ]);

            $this->syncItems($priceBook, $validated['items'] ?? []);

            return $priceBook->fresh(['items.product']);
        });
    }

    public function update(string $uid, array $data): PriceBook
    {
        $priceBook = $this->getByUid($uid);
        $validated = $this->validatePriceBook($data, true);

        return DB::transaction(function () use ($priceBook, $validated) {
            $payload = [];

            foreach (['name', 'key', 'channel', 'is_active', 'valid_from', 'valid_until'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $payload[$field] = $validated[$field];
                }
            }

            if ($payload !== []) {
                $priceBook->update($payload);
            }

            if (array_key_exists('items', $validated)) {
                $this->syncItems($priceBook, $validated['items']);
            }

            return $priceBook->fresh(['items.product']);
        });
    }

    public function delete(string $uid): void
    {
        $this->getByUid($uid)->delete();
    }

    public function resolveActivePriceBook(string $uid): PriceBook
    {
        $priceBook = PriceBook::query()->where('uid', $uid)->firstOrFail();

        if (!$priceBook->is_active) {
            throw ValidationException::withMessages([
                'price_book_uid' => ['La lista de precios no esta activa'],
            ]);
        }

        if ($priceBook->valid_from && now()->lt($priceBook->valid_from)) {
            throw ValidationException::withMessages([
                'price_book_uid' => ['La lista de precios aun no esta vigente'],
            ]);
        }

        if ($priceBook->valid_until && now()->gt($priceBook->valid_until)) {
            throw ValidationException::withMessages([
                'price_book_uid' => ['La lista de precios ya vencio'],
            ]);
        }

        return $priceBook;
    }

    public function getItemForProduct(PriceBook $priceBook, InventoryProduct $product): ?PriceBookItem
    {
        return PriceBookItem::query()
            ->where('price_book_id', $priceBook->getKey())
            ->where('product_id', $product->getKey())
            ->first();
    }

    private function validatePriceBook(array $data, bool $partial = false): array
    {
        $validator = Validator::make($data, [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'key' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'channel' => 'sometimes|string|in:B2B,B2C,B2G',
            'is_active' => 'sometimes|boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'items' => 'sometimes|array',
            'items.*.product_uid' => 'required_with:items|uuid',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.currency' => 'nullable|string|max:10',
            'items.*.min_margin_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function syncItems(PriceBook $priceBook, array $items): void
    {
        $syncIds = [];

        foreach ($items as $item) {
            $product = InventoryProduct::query()->where('uid', $item['product_uid'])->first();

            if (!$product) {
                throw ValidationException::withMessages([
                    'items' => ['Uno de los productos de la lista de precios no existe'],
                ]);
            }

            $priceBookItem = PriceBookItem::query()->updateOrCreate(
                [
                    'price_book_id' => $priceBook->getKey(),
                    'product_id' => $product->getKey(),
                ],
                [
                    'unit_price' => $item['unit_price'],
                    'currency' => $item['currency'] ?? 'COP',
                    'min_margin_percent' => $item['min_margin_percent'] ?? 0,
                ]
            );

            $syncIds[] = $priceBookItem->getKey();
        }

        if ($items !== []) {
            PriceBookItem::query()
                ->where('price_book_id', $priceBook->getKey())
                ->whereNotIn('id', $syncIds)
                ->delete();
        }
    }
}
