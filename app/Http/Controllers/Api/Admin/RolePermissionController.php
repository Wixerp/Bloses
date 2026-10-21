<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\RolePermissionService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class RolePermissionController extends Controller
{
    public function __construct(
        private RolePermissionService $service
    ) {}

    private function success(
        string $message,
        mixed $data = null,
        int $status = 200
    ) {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    private function failure(Throwable $e)
    {
        if ($e instanceof ValidationException) {
            throw $e;
        }

        report($e);

        return response()->json([
            'success' => false,
            'message' => 'An unexpected error occurred.',
        ], 500);
    }

    // GET /api/admin/roles
    public function indexRoles()
    {
        try {
            return $this->success(
                'Roles fetched successfully.',
                $this->service->roles()
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    // POST /api/admin/roles
    public function storeRole(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web'),
            ],
        ]);

        try {
            return $this->success(
                'Role created successfully.',
                $this->service->createRole($data),
                201
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    // PUT /api/admin/roles/{role}
    public function updateRole(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($role->id),
            ],
        ]);

        try {
            return $this->success(
                'Role updated successfully.',
                $this->service->updateRole($role, $data)
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    // DELETE /api/admin/roles/{role}
    public function destroyRole(Role $role)
    {
        try {
            $this->service->deleteRole($role);

            return $this->success('Role deleted successfully.');
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    // GET /api/admin/permissions
    public function indexPermissions()
    {
        try {
            return $this->success(
                'Permissions fetched successfully.',
                $this->service->permissions()
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    // POST /api/admin/permissions
    public function storePermission(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('permissions', 'name')
                    ->where('guard_name', 'web'),
            ],
        ]);

        try {
            return $this->success(
                'Permission created successfully.',
                $this->service->createPermission($data),
                201
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    // PUT /api/admin/permissions/{permission}
    public function updatePermission(
        Request $request,
        Permission $permission
    ) {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('permissions', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($permission->id),
            ],
        ]);

        try {
            return $this->success(
                'Permission updated successfully.',
                $this->service->updatePermission($permission, $data)
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    // DELETE /api/admin/permissions/{permission}
    public function destroyPermission(Permission $permission)
    {
        try {
            $this->service->deletePermission($permission);

            return $this->success(
                'Permission deleted successfully.'
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    // PUT /api/admin/roles/{role}/permissions
    public function syncRolePermissions(
        Request $request,
        Role $role
    ) {
        $data = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => [
                'required', 'integer', 'distinct',
            ],
        ]);

        try {
            return $this->success(
                'Role permissions updated successfully.',
                $this->service->syncRolePermissions(
                    $role,
                    $data['permission_ids']
                )
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    // PUT /api/admin/users/{user}/roles
    public function assignUserRoles(
        Request $request,
        User $user
    ) {
        $data = $request->validate([
            'role_ids' => ['required', 'array'],
            'role_ids.*' => [
                'required', 'integer', 'distinct',
            ],
        ]);

        try {
            return $this->success(
                'User roles updated successfully.',
                $this->service->assignUserRoles(
                    $user,
                    $data['role_ids']
                )
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    // PUT /api/admin/users/{user}/permissions
    public function syncUserPermissions(
        Request $request,
        User $user
    ) {
        $data = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => [
                'required', 'integer', 'distinct',
            ],
        ]);

        try {
            return $this->success(
                'User direct permissions updated successfully.',
                $this->service->syncUserPermissions(
                    $user,
                    $data['permission_ids']
                )
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    // GET /api/admin/users/{user}/access
    public function userAccess(User $user)
    {
        try {
            return $this->success(
                'User access fetched successfully.',
                $this->service->userAccess($user)
            );
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }
}
