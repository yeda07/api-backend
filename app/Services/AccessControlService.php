<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class AccessControlService
{
    public function getRoles()
    {
        return Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get();
    }

    public function getPermissions()
    {
        return Permission::query()
            ->orderBy('module')
            ->orderBy('action')
            ->get();
    }

    public function getUserAccess(string $userUid): array
    {
        $user = $this->findUser($userUid);
        $user->load(['roles.permissions', 'permissions']);

        return [
            'user' => $user,
            'roles' => $user->roles,
            'direct_permissions' => $user->permissions,
            'effective_permissions' => $user->effectivePermissions(),
        ];
    }

    public function assignRoleToUser(string $userUid, string $roleUid): array
    {
        $user = $this->findUser($userUid);
        $role = $this->findRole($roleUid);

        $user->assignRole($role);

        return $this->getUserAccess($userUid);
    }

    public function createRole(array $data): Role
    {
        $this->ensureRoleKeyIsAvailable($data['key']);

        $role = Role::query()->create([
            'name' => $data['name'],
            'key' => $data['key'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        $role->permissions()->sync($this->permissionIdsFromUids($data['permission_uids'] ?? []));

        return $role->fresh('permissions');
    }

    public function updateRole(string $roleUid, array $data): Role
    {
        $role = $this->findRole($roleUid);

        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role_uid' => ['Los roles del sistema no se pueden editar'],
            ]);
        }

        if (isset($data['key']) && $data['key'] !== $role->key) {
            $this->ensureRoleKeyIsAvailable($data['key'], $role->getKey());
        }

        $role->update([
            'name' => $data['name'] ?? $role->name,
            'key' => $data['key'] ?? $role->key,
            'description' => array_key_exists('description', $data) ? $data['description'] : $role->description,
        ]);

        if (array_key_exists('permission_uids', $data)) {
            $role->permissions()->sync($this->permissionIdsFromUids($data['permission_uids']));
        }

        return $role->fresh('permissions');
    }

    public function deleteRole(string $roleUid): void
    {
        $role = $this->findRole($roleUid);

        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role_uid' => ['Los roles del sistema no se pueden eliminar'],
            ]);
        }

        $role->delete();
    }

    public function removeRoleFromUser(string $userUid, string $roleUid): array
    {
        $user = $this->findUser($userUid);
        $role = $this->findRole($roleUid);

        $user->removeRole($role);

        return $this->getUserAccess($userUid);
    }

    public function grantDirectPermissionToUser(string $userUid, string $permissionUid): array
    {
        $user = $this->findUser($userUid);
        $permission = $this->findPermission($permissionUid);

        $user->givePermissionTo($permission);

        return $this->getUserAccess($userUid);
    }

    public function revokeDirectPermissionFromUser(string $userUid, string $permissionUid): array
    {
        $user = $this->findUser($userUid);
        $permission = $this->findPermission($permissionUid);

        $user->revokePermissionTo($permission);

        return $this->getUserAccess($userUid);
    }

    private function findUser(string $userUid): User
    {
        $user = User::query()->where('uid', $userUid)->first();

        if (!$user) {
            throw new ModelNotFoundException('Usuario no encontrado');
        }

        return $user;
    }

    private function findRole(string $roleUid): Role
    {
        $role = Role::query()->where('uid', $roleUid)->first();

        if (!$role) {
            throw ValidationException::withMessages([
                'role_uid' => ['El rol no existe o no pertenece a este tenant'],
            ]);
        }

        return $role;
    }

    private function findPermission(string $permissionUid): Permission
    {
        $permission = Permission::query()->where('uid', $permissionUid)->first();

        if (!$permission) {
            throw ValidationException::withMessages([
                'permission_uid' => ['El permiso no existe'],
            ]);
        }

        return $permission;
    }

    private function permissionIdsFromUids(array $permissionUids): array
    {
        if (empty($permissionUids)) {
            return [];
        }

        $permissions = Permission::query()
            ->whereIn('uid', $permissionUids)
            ->get();

        if ($permissions->count() !== count(array_unique($permissionUids))) {
            throw ValidationException::withMessages([
                'permission_uids' => ['Uno o mas permisos no existen'],
            ]);
        }

        return $permissions->pluck('id')->all();
    }

    private function ensureRoleKeyIsAvailable(string $key, ?int $ignoreRoleId = null): void
    {
        $exists = Role::query()
            ->where('key', $key)
            ->when($ignoreRoleId, fn ($query) => $query->where('id', '!=', $ignoreRoleId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'key' => ['Ya existe un rol con esta clave en el tenant'],
            ]);
        }
    }
}
