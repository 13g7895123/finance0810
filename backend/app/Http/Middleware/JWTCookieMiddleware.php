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
        // Check if Authorization header is already present
        if (!$request->hasHeader('Authorization')) {
            // Check if auth token exists in cookies
            if ($request->hasCookie('auth-token')) {
                $token = $request->cookie('auth-token');
                // Add Bearer token to Authorization header
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }
        }

        return $next($request);
    }
}