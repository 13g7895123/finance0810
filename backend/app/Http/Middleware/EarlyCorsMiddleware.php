<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Point 76: Early CORS middleware to handle errors that occur before normal middleware
 */
class EarlyCorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigin = $this->getAllowedOrigin($request);

        // Handle preflight OPTIONS request early
        if ($request->getMethod() === "OPTIONS") {
            return response('')
                ->header('Access-Control-Allow-Origin', $allowedOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, X-XSRF-TOKEN')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400')
                ->setStatusCode(204);
        }

        // Wrap everything in a very early try-catch
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Log the very early error
            \Log::error('Early CORS Middleware caught error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->url(),
                'method' => $request->method(),
                'type' => get_class($e)
            ]);

            // Create basic error response with CORS
            $response = response()->json([
                'success' => false,
                'error' => 'Server Error',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred',
                'timestamp' => now()->format('c')
            ], 500);
        }

        // Always add CORS headers to any response
        if ($response instanceof Response) {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, X-XSRF-TOKEN');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Expose-Headers', 'Authorization, Content-Disposition');
        }

        return $response;
    }

    private function getAllowedOrigin(Request $request): string
    {
        $origin = $request->header('Origin');

        $allowedOrigins = [
            'http://localhost:3301',
            'http://127.0.0.1:3301',
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'http://localhost:9121',
            'http://127.0.0.1:9121',
            'http://finance.local',
            'https://dev-finance.mercylife.cc',
            'https://finance.mercylife.cc',
        ];

        if (in_array($origin, $allowedOrigins)) {
            return $origin;
        }

        // For development, allow localhost with any port
        if (preg_match('/^http:\/\/(localhost|127\.0\.0\.1)(:[0-9]+)?$/', $origin)) {
            return $origin;
        }

        return 'http://localhost:3301'; // Default fallback
    }
}