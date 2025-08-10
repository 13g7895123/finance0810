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

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
            ],
            'permissions' => $permissions,
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
}