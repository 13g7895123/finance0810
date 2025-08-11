<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class JWTCookieMiddleware
{
    /**
     * Handle an incoming request.
     * Extract JWT token from cookie and add it to Authorization header
     */
    public function handle(Request $request, Closure $next)
    {
        // Debug log for production troubleshooting
        if (app()->environment('production')) {
            \Log::info('JWT Cookie Middleware Debug', [
                'has_auth_header' => $request->hasHeader('Authorization'),
                'has_auth_cookie' => $request->hasCookie('auth-token'),
                'cookie_length' => $request->hasCookie('auth-token') ? strlen($request->cookie('auth-token')) : 0,
                'request_url' => $request->url(),
                'origin' => $request->header('Origin')
            ]);
        }

        // Check if Authorization header is already present
        if (!$request->hasHeader('Authorization')) {
            // Check if auth token exists in cookies
            if ($request->hasCookie('auth-token')) {
                $token = $request->cookie('auth-token');
                
                // Validate token is not empty
                if (!empty($token) && strlen($token) > 10) {
                    // Add Bearer token to Authorization header
                    $request->headers->set('Authorization', 'Bearer ' . $token);
                    
                    if (app()->environment('production')) {
                        \Log::info('JWT Token added from cookie', [
                            'token_length' => strlen($token),
                            'token_prefix' => substr($token, 0, 10) . '...'
                        ]);
                    }
                } else {
                    if (app()->environment('production')) {
                        \Log::warning('Invalid auth-token cookie found', [
                            'token_length' => strlen($token),
                            'token' => $token
                        ]);
                    }
                }
            }
        }

        return $next($request);
    }
}