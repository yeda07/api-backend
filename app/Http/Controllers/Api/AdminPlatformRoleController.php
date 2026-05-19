<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\Permission;
use App\Support\ApiIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminPlatformRoleController extends Controller
{
    public function index(Request $request)
    {
        $query = AdminRole::query()->withCount('users');

        $search = $request->get('search');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('key', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        return $this->successResponse(
            ApiIndex::paginateOrGet($query->latest(), $request->query(), 'admin_roles_page')
        );
    }

    public function show(string $uid)
    {
        $role = AdminRole::query()->with('permissions')->where('uid', $uid)->firstOrFail();

        return $this->successResponse($role);
    }

    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:100|unique:admin_roles,key',
            'description' => 'nullable|string',
            'permission_uids' => 'nullable|array',
            'permission_uids.*' => 'uuid|exists:permissions,uid',
        ])->validate();

        $role = AdminRole::query()->create([
            'name' => $validated['name'],
            'key' => $validated['key'],
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['permission_uids'])) {
            $permissionIds = Permission::query()->whereIn('uid', $validated['permission_uids'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }

        return $this->successResponse($role->load('permissions'), 201, 'Rol de plataforma creado');
    }

    public function update(Request $request, string $uid)
    {
        $role = AdminRole::query()->where('uid', $uid)->firstOrFail();

        if ($role->is_system) {
            return $this->errorResponse('No puedes modificar un rol del sistema', 422);
        }

        $validated = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'key' => 'sometimes|string|max:100|unique:admin_roles,key,' . $role->id,
            'description' => 'nullable|string',
            'permission_uids' => 'nullable|array',
            'permission_uids.*' => 'uuid|exists:permissions,uid',
        ])->validate();

        $role->update([
            'name' => $validated['name'] ?? $role->name,
            'key' => $validated['key'] ?? $role->key,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $role->description,
        ]);

        if (array_key_exists('permission_uids', $validated)) {
            $permissionIds = Permission::query()->whereIn('uid', $validated['permission_uids'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }

        return $this->successResponse($role->load('permissions'), 200, 'Rol de plataforma actualizado');
    }

    public function destroy(string $uid)
    {
        $role = AdminRole::query()->where('uid', $uid)->firstOrFail();

        if ($role->is_system) {
            return $this->errorResponse('No puedes eliminar un rol del sistema', 422);
        }

        $role->delete();

        return $this->successResponse(null, 200, 'Rol de plataforma eliminado');
    }

    public function permissions()
    {
        return $this->successResponse(Permission::query()->orderBy('module')->orderBy('action')->get());
    }
}
