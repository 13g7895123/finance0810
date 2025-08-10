<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        // Admin and executive roles have all permissions
        if ($user->hasRole(['admin', 'executive'])) {
            return $next($request);
        }

        // Check if user has any of the required permissions
        if (empty($permissions) || $user->hasAnyPermission($permissions)) {
            return $next($request);
        }

        return response()->json([
            'error' => 'Forbidden',
            'message' => '您沒有權限執行此操作',
            'required_permissions' => $permissions
        ], 403);
    }
}