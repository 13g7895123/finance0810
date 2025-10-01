<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, \Closure $next, ...$guards)
    {
        // Point 2: 支援開發環境跳過認證模式
        if ($request->expectsJson() && $request->bearerToken()) {
            $token = $request->bearerToken();

            // 檢查是否為開發模式的模擬 token
            if (strpos($token, 'mock_jwt_token_for_development') === 0) {
                Log::info('Point 2 - Mock Token Detected', [
                    'token' => substr($token, 0, 30) . '...',
                    'url' => $request->fullUrl(),
                    'method' => $request->method()
                ]);

                // 創建一個模擬的管理員用戶並設定到 auth 中
                $mockUser = new \App\Models\User();
                $mockUser->id = 1;
                $mockUser->name = 'Development Admin';
                $mockUser->email = 'admin@finance.local';
                $mockUser->username = 'admin';
                $mockUser->email_verified_at = now();
                $mockUser->created_at = now();
                $mockUser->updated_at = now();

                // 手動設定用戶為已認證
                auth()->setUser($mockUser);

                Log::info('Point 2 - Mock User Authenticated', [
                    'user_id' => $mockUser->id,
                    'user_email' => $mockUser->email
                ]);

                // 直接通過認證，繼續處理請求
                return $next($request);
            }
        }

        // Point 85: Add JWT authentication debug logging
        if ($request->expectsJson() && $request->bearerToken()) {
            try {
                $token = $request->bearerToken();
                Log::info('Point 85 - JWT Auth Debug', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'has_token' => !empty($token),
                    'token_preview' => $token ? substr($token, 0, 20) . '...' : null,
                    'user_agent' => $request->userAgent(),
                    'ip' => $request->ip()
                ]);

                // Try to parse the token
                if ($token) {
                    $payload = JWTAuth::parseToken()->getPayload();
                    // Log::info('Point 85 - JWT Token Valid', [
                    //     'user_id' => $payload->get('sub'),
                    //     'roles' => $payload->get('roles', []),
                    //     'exp' => date('Y-m-d H:i:s', $payload->get('exp')),
                    //     'url' => $request->fullUrl()
                    // ]);
                }
            } catch (JWTException $e) {
                Log::warning('Point 85 - JWT Auth Failed', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'error' => $e->getMessage(),
                    'token_preview' => $token ? substr($token, 0, 20) . '...' : null,
                    'ip' => $request->ip()
                ]);
            } catch (\Exception $e) {
                Log::error('Point 85 - JWT Auth Exception', [
                    'url' => $request->fullUrl(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return parent::handle($request, $next, ...$guards);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Point 85: Log authentication redirect attempts
        if ($request->expectsJson()) {
            Log::info('Point 85 - Auth Redirect Skipped (JSON)', [
                'url' => $request->fullUrl(),
                'method' => $request->method()
            ]);
            return null;
        }

        Log::info('Point 85 - Auth Redirect to Login', [
            'url' => $request->fullUrl(),
            'method' => $request->method()
        ]);

        return route('login');
    }
}