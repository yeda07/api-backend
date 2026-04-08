<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CrmEntityService;
use App\Services\TeamHierarchyService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CrmEntityController extends Controller
{
    protected $service;

    public function __construct(CrmEntityService $service, protected TeamHierarchyService $teamHierarchyService)
    {
        $this->service = $service;
    }

    public function index()
    {
        return $this->successResponse($this->service->getAll());
    }

    public function store(Request $request)
    {
        try {
            return $this->successResponse($this->service->create($request->all()), 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function assignOwner(Request $request, string $uid)
    {
        try {
            $validated = $request->validate([
                'owner_user_uid' => 'required|uuid',
            ]);

            return $this->successResponse(
                $this->teamHierarchyService->assignCrmEntityOwner($uid, $validated['owner_user_uid']),
                200,
                'Responsable asignado'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Entidad CRM no encontrada', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
