<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin|executive|manager');
    }

    /**
     * Get all permissions grouped by category
     */
    public function index()
    {
        $permissions = Permission::all()->groupBy('category')->map(function($categoryPermissions) {
            return $categoryPermissions->map(function($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                    'description' => $permission->description,
                    'category' => $permission->category,
                ];
            });
        });

        $categories = Permission::getCategories();

        return response()->json([
            'permissions' => $permissions,
            'categories' => $categories
        ]);
    }

    /**
     * Get user roles
     */
    public function getUserRoles(User $user)
    {
        $roles = $user->roles->map(function($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
            ];
        });

        return response()->json([
            'user_id' => $user->id,
            'roles' => $roles
        ]);
    }

    /**
     * Get role permissions
     */
    public function getRolePermissions(Role $role)
    {
        $permissions = $role->permissions->groupBy('category')->map(function($categoryPermissions) {
            return $categoryPermissions->map(function($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                    'description' => $permission->description,
                    'category' => $permission->category,
                ];
            });
        });

        // Also provide a flat array of permission names for easy frontend checking
        $permissionNames = $role->permissions->pluck('name')->toArray();

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
            ],
            'permissions' => $permissionNames, // Flat array of names for frontend compatibility
            'permissions_grouped' => $permissions, // Grouped permissions for display
            'permissions_count' => $role->permissions->count()
        ]);
    }

    /**
     * Get permissions by category
     */
    public function getByCategory($category)
    {
        $permissions = Permission::byCategory($category)->get()->map(function($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name,
                'description' => $permission->description,
                'category' => $permission->category,
            ];
        });

        return response()->json([
            'category' => $category,
            'permissions' => $permissions
        ]);
    }

    /**
     * Assign permission to role
     */
    public function assignPermissionToRole(Request $request, Role $role)
    {
        $request->validate([
            'permission_name' => 'required|string',
        ]);

        try {
            $permission = Permission::where('name', $request->permission_name)->first();
            
            if (!$permission) {
                return response()->json([
                    'message' => 'Permission not found',
                    'error' => 'The specified permission does not exist'
                ], 404);
            }

            // Check if role already has this permission
            if ($role->hasPermissionTo($permission->name)) {
                return response()->json([
                    'message' => 'Permission already assigned to role',
                    'role' => $role->name,
                    'permission' => $permission->name
                ], 200);
            }

            $role->givePermissionTo($permission->name);

            return response()->json([
                'message' => 'Permission assigned successfully',
                'role' => $role->name,
                'permission' => $permission->name
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to assign permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove permission from role
     */
    public function removePermissionFromRole(Role $role, $permissionName)
    {
        try {
            $permission = Permission::where('name', $permissionName)->first();
            
            if (!$permission) {
                return response()->json([
                    'message' => 'Permission not found',
                    'error' => 'The specified permission does not exist'
                ], 404);
            }

            // Check if role has this permission
            if (!$role->hasPermissionTo($permission->name)) {
                return response()->json([
                    'message' => 'Role does not have this permission',
                    'role' => $role->name,
                    'permission' => $permission->name
                ], 404);
            }

            $role->revokePermissionTo($permission->name);

            return response()->json([
                'message' => 'Permission removed successfully',
                'role' => $role->name,
                'permission' => $permission->name
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}