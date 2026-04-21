<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductDependency;
use App\Repositories\ProductDependencyRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DependencyService
{
    public function __construct(private readonly ProductDependencyRepository $dependencies)
    {
    }

    public function listForProduct(string $productUid)
    {
        return $this->dependencies->forProduct($productUid);
    }

    public function createDependency(Product $product, Product $dependsOnProduct, array $data): ProductDependency
    {
        $validated = Validator::make($data, [
            'dependency_type' => 'required|string|in:required,optional,incompatible',
            'message' => 'nullable|string',
        ])->validate();

        if ($product->is($dependsOnProduct)) {
            throw ValidationException::withMessages([
                'depends_on_product_uid' => ['Un producto no puede depender de si mismo'],
            ]);
        }

        $exists = ProductDependency::query()
            ->where('product_id', $product->getKey())
            ->where('depends_on_product_id', $dependsOnProduct->getKey())
            ->where('dependency_type', $validated['dependency_type'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'depends_on_product_uid' => ['La dependencia ya existe para este producto'],
            ]);
        }

        if ($validated['dependency_type'] !== 'incompatible' && $this->createsCycle($product, $dependsOnProduct)) {
            throw ValidationException::withMessages([
                'depends_on_product_uid' => ['La dependencia crea un ciclo invalido'],
            ]);
        }

        return $this->dependencies->create([
            'product_id' => $product->getKey(),
            'depends_on_product_id' => $dependsOnProduct->getKey(),
            'dependency_type' => $validated['dependency_type'],
            'message' => $validated['message'] ?? null,
        ]);
    }

    public function deleteDependency(string $uid): void
    {
        $dependency = $this->dependencies->findByUid($uid);
        $this->dependencies->delete($dependency);
    }

    public function getRequiredDependencies(Product $product): Collection
    {
        return $product->dependencies()->with('dependsOnProduct')
            ->where('dependency_type', 'required')
            ->get()
            ->pluck('dependsOnProduct')
            ->filter();
    }

    public function getOptionalDependencies(Product $product): Collection
    {
        return $product->dependencies()->with('dependsOnProduct')
            ->where('dependency_type', 'optional')
            ->get()
            ->pluck('dependsOnProduct')
            ->filter();
    }

    public function validateDependencies(Collection $products): array
    {
        $products = $products->filter();
        $productIds = $products->pluck('id')->all();

        $requiredMissing = [];
        $optionalSuggestions = [];
        $incompatibilities = [];

        foreach ($products as $product) {
            $product->loadMissing('dependencies.dependsOnProduct');

            foreach ($product->dependencies as $dependency) {
                $dependsOn = $dependency->dependsOnProduct;

                if (!$dependsOn) {
                    continue;
                }

                $isPresent = in_array($dependsOn->getKey(), $productIds, true);

                if ($dependency->dependency_type === 'required' && !$isPresent) {
                    $requiredMissing[$dependsOn->uid] = $this->dependencyPayload($product, $dependsOn, $dependency);
                }

                if ($dependency->dependency_type === 'optional' && !$isPresent) {
                    $optionalSuggestions[$dependsOn->uid] = $this->dependencyPayload($product, $dependsOn, $dependency);
                }

                if ($dependency->dependency_type === 'incompatible' && $isPresent) {
                    $incompatibilities[] = $this->dependencyPayload($product, $dependsOn, $dependency);
                }
            }
        }

        return [
            'required_missing' => array_values($requiredMissing),
            'optional_suggestions' => array_values($optionalSuggestions),
            'incompatibilities' => $incompatibilities,
            'is_valid' => empty($requiredMissing) && empty($incompatibilities),
        ];
    }

    private function createsCycle(Product $product, Product $dependsOnProduct): bool
    {
        return $this->hasPath($dependsOnProduct, $product->getKey(), []);
    }

    private function hasPath(Product $product, int $targetProductId, array $visited): bool
    {
        if (in_array($product->getKey(), $visited, true)) {
            return false;
        }

        $visited[] = $product->getKey();

        foreach ($product->dependencies()->where('dependency_type', '!=', 'incompatible')->get() as $dependency) {
            if ((int) $dependency->depends_on_product_id === $targetProductId) {
                return true;
            }

            $next = Product::query()->find($dependency->depends_on_product_id);

            if ($next && $this->hasPath($next, $targetProductId, $visited)) {
                return true;
            }
        }

        return false;
    }

    private function dependencyPayload(Product $source, Product $dependsOn, ProductDependency $dependency): array
    {
        return [
            'source_product_uid' => $source->uid,
            'source_product_name' => $source->name,
            'dependency_product_uid' => $dependsOn->uid,
            'dependency_product_name' => $dependsOn->name,
            'dependency_type' => $dependency->dependency_type,
            'message' => $dependency->message,
        ];
    }
}
