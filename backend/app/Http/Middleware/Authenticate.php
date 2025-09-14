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
                    Log::info('Point 85 - JWT Token Valid', [
                        'user_id' => $payload->get('sub'),
                        'roles' => $payload->get('roles', []),
                        'exp' => date('Y-m-d H:i:s', $payload->get('exp')),
                        'url' => $request->fullUrl()
                    ]);
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