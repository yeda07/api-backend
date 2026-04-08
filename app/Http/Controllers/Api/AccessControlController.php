<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccessControlService;
use App\Services\TeamHierarchyService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccessControlController extends Controller
{
    public function __construct(
        private readonly AccessControlService $accessControlService,
        private readonly TeamHierarchyService $teamHierarchyService
    )
    {
    }

    public function roles()
    {
        return $this->successResponse($this->accessControlService->getRoles());
    }

    public function storeRole(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'key' => 'required|string|max:100',
                'description' => 'nullable|string',
                'permission_uids' => 'nullable|array',
                'permission_uids.*' => 'uuid',
            ]);

            return $this->successResponse(
                $this->accessControlService->createRole($validated),
                201,
                'Rol creado'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function updateRole(Request $request, string $roleUid)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'key' => 'sometimes|string|max:100',
                'description' => 'nullable|string',
                'permission_uids' => 'sometimes|array',
                'permission_uids.*' => 'uuid',
            ]);

            return $this->successResponse(
                $this->accessControlService->updateRole($roleUid, $validated),
                200,
                'Rol actualizado'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function destroyRole(string $roleUid)
    {
        try {
            $this->accessControlService->deleteRole($roleUid);

            return $this->successResponse(null, 200, 'Rol eliminado');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function permissions()
    {
        return $this->successResponse($this->accessControlService->getPermissions());
    }

    public function userAccess(string $uid)
    {
        try {
            return $this->successResponse($this->accessControlService->getUserAccess($uid));
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Usuario no encontrado', 404);
        }
    }

    public function assignRole(Request $request, string $uid)
    {
        try {
            $validated = $request->validate([
                'role_uid' => 'required|uuid',
            ]);

            return $this->successResponse(
                $this->accessControlService->assignRoleToUser($uid, $validated['role_uid']),
                200,
                'Rol asignado'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Usuario no encontrado', 404);
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function removeRole(string $uid, string $roleUid)
    {
        try {
            return $this->successResponse(
                $this->accessControlService->removeRoleFromUser($uid, $roleUid),
                200,
                'Rol retirado'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Usuario no encontrado', 404);
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function grantPermission(Request $request, string $uid)
    {
        try {
            $validated = $request->validate([
                'permission_uid' => 'required|uuid',
            ]);

            return $this->successResponse(
                $this->accessControlService->grantDirectPermissionToUser($uid, $validated['permission_uid']),
                200,
                'Permiso directo asignado'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Usuario no encontrado', 404);
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function revokePermission(string $uid, string $permissionUid)
    {
        try {
            return $this->successResponse(
                $this->accessControlService->revokeDirectPermissionFromUser($uid, $permissionUid),
                200,
                'Permiso directo retirado'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Usuario no encontrado', 404);
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }

    public function assignManager(Request $request, string $uid)
    {
        try {
            $validated = $request->validate([
                'manager_uid' => 'nullable|uuid',
            ]);

            return $this->successResponse(
                $this->teamHierarchyService->assignManager($uid, $validated['manager_uid'] ?? null),
                200,
                'Manager asignado'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Usuario no encontrado', 404);
        } catch (\Throwable $e) {
            return $this->errorResponse('Server error', 500, ['server' => [$e->getMessage()]]);
        }
    }
}
