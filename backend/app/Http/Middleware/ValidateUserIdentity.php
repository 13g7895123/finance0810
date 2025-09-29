<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Point 18: 驗證每次載入資料時用戶身分是否仍然存在
 * 檢查用戶帳號狀態、角色是否有效
 */
class ValidateUserIdentity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 跳過不需要認證的端點
        $excludePaths = [
            'auth/login',
            'auth/register',
            'public/',
            'health',
            'debug',
        ];

        foreach ($excludePaths as $path) {
            if (str_contains($request->getPathInfo(), $path)) {
                return $next($request);
            }
        }

        // 檢查是否為開發環境的模擬 token
        if ($request->bearerToken() && str_starts_with($request->bearerToken(), 'mock_jwt_token_for_development')) {
            Log::info('Point 18 - Skip auth mode detected, bypassing user identity validation');
            return $next($request);
        }

        // 檢查用戶是否已認證
        if (!Auth::check()) {
            Log::warning('Point 18 - User not authenticated', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => false,
                'message' => '認證失效，請重新登入',
                'error' => 'Authentication expired',
                'auth_status' => 'unauthenticated'
            ], 401);
        }

        $user = Auth::user();

        // 檢查用戶帳號狀態
        if ($user->status !== 'active') {
            Log::warning('Point 18 - User account is not active', [
                'user_id' => $user->id,
                'user_status' => $user->status,
                'url' => $request->fullUrl(),
                'method' => $request->method()
            ]);

            return response()->json([
                'success' => false,
                'message' => '帳號已被停用，請聯繫管理員',
                'error' => 'Account disabled',
                'auth_status' => 'disabled'
            ], 403);
        }

        // 檢查用戶是否仍然存在（軟刪除檢查）
        if ($user->deleted_at !== null) {
            Log::warning('Point 18 - User account has been deleted', [
                'user_id' => $user->id,
                'deleted_at' => $user->deleted_at,
                'url' => $request->fullUrl()
            ]);

            return response()->json([
                'success' => false,
                'message' => '帳號已不存在，請聯繫管理員',
                'error' => 'Account not found',
                'auth_status' => 'deleted'
            ], 403);
        }

        // 檢查用戶是否有任何角色
        $userRoles = $user->getRoleNames();
        if ($userRoles->isEmpty()) {
            Log::warning('Point 18 - User has no assigned roles', [
                'user_id' => $user->id,
                'username' => $user->username,
                'url' => $request->fullUrl()
            ]);

            return response()->json([
                'success' => false,
                'message' => '帳號權限設定異常，請聯繫管理員',
                'error' => 'No roles assigned',
                'auth_status' => 'no_roles'
            ], 403);
        }

        // 檢查角色是否仍然有效（檢查角色表中是否存在）
        $validRoles = ['admin', 'executive', 'manager', 'staff'];
        $hasValidRole = $userRoles->intersect($validRoles)->isNotEmpty();

        if (!$hasValidRole) {
            Log::warning('Point 18 - User has invalid roles', [
                'user_id' => $user->id,
                'username' => $user->username,
                'assigned_roles' => $userRoles->toArray(),
                'valid_roles' => $validRoles,
                'url' => $request->fullUrl()
            ]);

            return response()->json([
                'success' => false,
                'message' => '帳號權限設定異常，請聯繫管理員',
                'error' => 'Invalid roles',
                'auth_status' => 'invalid_roles'
            ], 403);
        }

        // 記錄成功的身分驗證
        Log::info('Point 18 - User identity validation passed', [
            'user_id' => $user->id,
            'username' => $user->username,
            'roles' => $userRoles->toArray(),
            'url' => $request->fullUrl(),
            'method' => $request->method()
        ]);

        // 將驗證資訊添加到請求中，供後續使用
        $request->merge([
            '_user_identity_validated' => true,
            '_user_validation_timestamp' => now()
        ]);

        return $next($request);
    }
}