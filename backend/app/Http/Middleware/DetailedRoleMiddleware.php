<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middlewares\RoleMiddleware as SpatieRoleMiddleware;

class DetailedRoleMiddleware extends SpatieRoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        // 取得當前認證用戶
        $user = auth($this->getGuard())->user();
        
        \Log::info('DetailedRoleMiddleware - 開始權限檢查', [
            'requested_roles' => $roles,
            'request_url' => $request->url(),
            'request_method' => $request->method(),
            'user_authenticated' => !!$user,
            'user_id' => $user ? $user->id : null,
            'user_username' => $user ? $user->username : null
        ]);

        if (!$user) {
            \Log::warning('DetailedRoleMiddleware - 用戶未認證');
            throw UnauthorizedException::notLoggedIn();
        }

        if (empty($roles)) {
            \Log::info('DetailedRoleMiddleware - 無需特定角色，允許訪問');
            return $next($request);
        }

        // 取得用戶角色和權限詳情
        $userRoles = $user->getRoleNames()->toArray();
        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();
        $guardName = $user->guard_name ?? 'api';

        \Log::info('DetailedRoleMiddleware - 用戶權限詳情', [
            'user_id' => $user->id,
            'username' => $user->username,
            'guard_name' => $guardName,
            'user_roles' => $userRoles,
            'user_permissions' => $userPermissions,
            'requested_roles' => $roles
        ]);

        // 檢查每個請求的角色
        $hasRequiredRole = false;
        $roleCheckDetails = [];
        
        foreach ($roles as $role) {
            $hasThisRole = $user->hasRole($role);
            $roleCheckDetails[$role] = $hasThisRole;
            if ($hasThisRole) {
                $hasRequiredRole = true;
            }
        }

        \Log::info('DetailedRoleMiddleware - 角色檢查結果', [
            'role_check_details' => $roleCheckDetails,
            'has_required_role' => $hasRequiredRole,
            'access_granted' => $hasRequiredRole
        ]);

        if ($hasRequiredRole) {
            \Log::info('DetailedRoleMiddleware - 權限檢查通過，允許訪問', [
                'granted_by_roles' => array_keys(array_filter($roleCheckDetails))
            ]);
            return $next($request);
        }

        \Log::warning('DetailedRoleMiddleware - 權限不足，拒絕訪問', [
            'user_id' => $user->id,
            'username' => $user->username,
            'user_roles' => $userRoles,
            'required_roles' => $roles,
            'role_check_details' => $roleCheckDetails
        ]);

        // 檢查是否是guard問題
        $apiRoles = \Spatie\Permission\Models\Role::where('guard_name', 'api')->pluck('name')->toArray();
        $webRoles = \Spatie\Permission\Models\Role::where('guard_name', 'web')->pluck('name')->toArray();
        
        \Log::info('DetailedRoleMiddleware - 系統角色檢查', [
            'api_guard_roles' => $apiRoles,
            'web_guard_roles' => $webRoles,
            'user_guard_name' => $guardName,
            'roles_in_correct_guard' => array_intersect($roles, $apiRoles)
        ]);

        throw UnauthorizedException::forRoles($roles);
    }

    /**
     * Get the guard to be used for authentication.
     *
     * @return string
     */
    protected function getGuard()
    {
        return config('auth.defaults.guard');
    }
}