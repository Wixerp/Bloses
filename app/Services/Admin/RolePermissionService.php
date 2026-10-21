<?php

namespace App\Services\Admin;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RolePermissionService
{
    private string $guard = 'web';

    public function roles()
    {
        return Role::query()
            ->where('guard_name', $this->guard)
            ->with('permissions:id,name,guard_name')
            ->withCount('permissions')
            ->orderBy('id')
            ->get();
    }

    public function createRole(array $data): Role
    {
        return Role::create([
            'name' => $data['name'],
            'guard_name' => $this->guard,
        ]);
    }

    public function updateRole(Role $role, array $data): Role
    {
        $this->ensureGuard($role);

        $role->update(['name' => $data['name']]);

        return $role->fresh();
    }

    public function deleteRole(Role $role): void
    {
        $this->ensureGuard($role);

        if (in_array($role->name, ['admin', 'buyer', 'seller'], true)) {
            throw ValidationException::withMessages([
                'role' => ['System roles cannot be deleted.'],
            ]);
        }

        DB::transaction(function () use ($role) {
            $role->syncPermissions([]);
            $role->delete();
        });
    }

    public function permissions()
    {
        return Permission::query()
            ->where('guard_name', $this->guard)
            ->orderBy('id')
            ->get();
    }

    public function createPermission(array $data): Permission
    {
        return Permission::create([
            'name' => $data['name'],
            'guard_name' => $this->guard,
        ]);
    }

    public function updatePermission(
        Permission $permission,
        array $data
    ): Permission {
        $this->ensureGuard($permission);

        $permission->update(['name' => $data['name']]);

        return $permission->fresh();
    }

    public function deletePermission(Permission $permission): void
    {
        $this->ensureGuard($permission);

        DB::transaction(function () use ($permission) {
            DB::table('role_has_permissions')
                ->where('permission_id', $permission->id)
                ->delete();

            DB::table('model_has_permissions')
                ->where('permission_id', $permission->id)
                ->delete();

            $permission->delete();
        });
    }

    public function syncRolePermissions(
        Role $role,
        array $permissionIds
    ): Role {
        $this->ensureGuard($role);

        $permissions = Permission::query()
            ->where('guard_name', $this->guard)
            ->whereIn('id', $permissionIds)
            ->get();

        if ($permissions->count() !== count(array_unique($permissionIds))) {
            throw ValidationException::withMessages([
                'permission_ids' => [
                    'One or more permissions are invalid for the web guard.',
                ],
            ]);
        }

        DB::transaction(function () use ($role, $permissions) {
            $role->syncPermissions($permissions);
        });

        return $role->fresh()->load('permissions');
    }

    public function assignUserRoles(
        User $user,
        array $roleIds
    ): User {
        $roles = Role::query()
            ->where('guard_name', $this->guard)
            ->whereIn('id', $roleIds)
            ->get();

        if ($roles->count() !== count(array_unique($roleIds))) {
            throw ValidationException::withMessages([
                'role_ids' => [
                    'One or more roles are invalid for the web guard.',
                ],
            ]);
        }

        DB::transaction(function () use ($user, $roles) {
            $user->syncRoles($roles);
        });

        return $user->load('roles');
    }

    public function syncUserPermissions(
        User $user,
        array $permissionIds
    ): User {
        $permissions = Permission::query()
            ->where('guard_name', $this->guard)
            ->whereIn('id', $permissionIds)
            ->get();

        if ($permissions->count() !== count(array_unique($permissionIds))) {
            throw ValidationException::withMessages([
                'permission_ids' => [
                    'One or more permissions are invalid for the web guard.',
                ],
            ]);
        }

        DB::transaction(function () use ($user, $permissions) {
            $user->syncPermissions($permissions);
        });

        return $user->load('permissions');
    }

    public function userAccess(User $user): array
    {
        $user->load(['roles.permissions', 'permissions']);

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'roles' => $user->roles->pluck('name')->values(),
            'direct_permissions' => $user->permissions
                ->pluck('name')->values(),
            'effective_permissions' => $user->getAllPermissions()
                ->pluck('name')->unique()->values(),
        ];
    }

    private function ensureGuard($model): void
    {
        if ($model->guard_name !== $this->guard) {
            throw ValidationException::withMessages([
                'guard_name' => [
                    'This operation only supports the web guard.',
                ],
            ]);
        }
    }
}
