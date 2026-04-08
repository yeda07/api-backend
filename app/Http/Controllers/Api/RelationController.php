<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LoggerService;
use App\Services\RelationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RelationController extends Controller
{
    protected $service;

    public function __construct(RelationService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return $this->successResponse($this->service->getAll());
    }

    public function indexWithEntities()
    {
        return $this->successResponse($this->service->getAllWithEntities());
    }

    public function store(Request $request)
    {
        try {
            $relation = $this->service->create($request->all());

            LoggerService::log('info', 'Relacion creada', [
                'data' => $request->all(),
            ]);

            return $this->successResponse($relation, 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            LoggerService::log('error', 'Error al crear relacion', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return $this->errorResponse('Error al crear relacion', 500, [
                'server' => [$e->getMessage()],
            ]);
        }
    }

    public function showByEntity(string $type, string $uid)
    {
        try {
            return $this->successResponse($this->service->getByEntity($type, $uid));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function hierarchy(string $type, string $uid)
    {
        try {
            return $this->successResponse($this->service->getHierarchy($type, $uid));
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroy(string $uid)
    {
        try {
            $this->service->delete($uid);

            LoggerService::log('info', 'Relacion eliminada', [
                'relation_uid' => $uid,
            ]);

            return $this->successResponse(null, 200, 'Relation deleted');
        } catch (\Throwable $e) {
            LoggerService::log('error', 'Error al eliminar relacion', [
                'relation_uid' => $uid,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Error al eliminar relacion', 500, [
                'server' => [$e->getMessage()],
            ]);
        }
    }
}
