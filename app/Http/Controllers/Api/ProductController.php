<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountProductService;
use App\Services\DependencyService;
use App\Services\ProductService;
use App\Services\ProductVersionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductVersionService $productVersionService,
        private readonly AccountProductService $accountProductService,
        private readonly DependencyService $dependencyService
    ) {
    }

    public function index(Request $request)
    {
        return $this->successResponse($this->productService->index($request->query()));
    }

    public function show(string $uid)
    {
        return $this->successResponse($this->productService->show($uid));
    }

    public function store(Request $request)
    {
        try {
            return $this->successResponse($this->productService->create($request->all()), 201, 'Producto creado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function update(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->productService->update($uid, $request->all()), 200, 'Producto actualizado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroy(string $uid)
    {
        try {
            $this->productService->delete($uid);

            return $this->successResponse(null, 200, 'Producto eliminado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function versions(string $uid)
    {
        return $this->successResponse($this->productService->versions($uid));
    }

    public function storeVersion(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->productService->createVersion($uid, $request->all()), 201, 'Version creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function updateVersion(Request $request, string $versionUid)
    {
        try {
            return $this->successResponse($this->productVersionService->updateVersion($versionUid, $request->all()), 200, 'Version actualizada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function dependencies(string $uid)
    {
        return $this->successResponse($this->productService->dependencies($uid));
    }

    public function storeDependency(Request $request, string $uid)
    {
        try {
            return $this->successResponse($this->productService->createDependency($uid, $request->all()), 201, 'Dependencia creada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroyDependency(string $dependencyUid)
    {
        try {
            $this->dependencyService->deleteDependency($dependencyUid);

            return $this->successResponse(null, 200, 'Dependencia eliminada');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function accountProducts(string $accountUid)
    {
        return $this->successResponse($this->accountProductService->getInstalledProducts($accountUid));
    }

    public function storeAccountProduct(Request $request, string $accountUid)
    {
        try {
            return $this->successResponse($this->accountProductService->assignProductToAccount($accountUid, $request->all()), 201, 'Producto asignado a la cuenta');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
