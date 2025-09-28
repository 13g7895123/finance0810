<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LogAccess
{
    /**
     * Handle an incoming request for log access validation
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Ensure user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '需要登入才能存取日誌功能',
                'error' => 'Unauthenticated'
            ], 401);
        }

        // Check if user has required permissions for log access
        $hasLogPermission = $user->isAdmin ||
                           $user->isManager ||
                           $user->isExecutive ||
                           $user->hasPermission('settings') ||
                           $user->hasPermission('all_access');

        if (!$hasLogPermission) {
            \Log::warning('Unauthorized log access attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'requested_path' => $request->path(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '您沒有權限存取系統日誌',
                'error' => 'Insufficient permissions for log access'
            ], 403);
        }

        // Log successful access for audit trail
        \Log::info('Log access granted', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'ip_address' => $request->ip(),
            'requested_path' => $request->path(),
            'timestamp' => now()->toISOString()
        ]);

        return $next($request);
    }
}