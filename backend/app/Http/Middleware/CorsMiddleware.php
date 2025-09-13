<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigin = $this->getAllowedOrigin($request);

        // Handle preflight OPTIONS request
        if ($request->getMethod() === "OPTIONS") {
            return response('')
                ->header('Access-Control-Allow-Origin', $allowedOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, X-XSRF-TOKEN')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400') // 24 hours
                ->setStatusCode(204); // Use 204 No Content for OPTIONS
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Determine appropriate error status code and message
            $statusCode = 500;
            $errorType = 'Internal Server Error';

            // Handle specific exception types
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                $statusCode = 401;
                $errorType = 'Authentication Required';
            } elseif ($e instanceof \Illuminate\Validation\ValidationException) {
                $statusCode = 422;
                $errorType = 'Validation Failed';
            } elseif ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                $statusCode = 404;
                $errorType = 'Resource Not Found';
            }

            // Create error response with CORS headers for all types of errors
            $response = response()->json([
                'success' => false,
                'error' => $errorType,
                'message' => app()->environment(['local', 'development']) ? $e->getMessage() : 'An error occurred',
                'debug' => app()->environment(['local', 'development']) ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString())
                ] : null
            ], $statusCode);

            // Add CORS headers to error response
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, X-XSRF-TOKEN');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Expose-Headers', 'Authorization, Content-Disposition');

            // Log the error for debugging
            \Log::error('CORS Middleware caught throwable', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->url(),
                'method' => $request->method(),
                'type' => get_class($e),
                'status_code' => $statusCode
            ]);

            return $response;
        }

        // Check if response is actually a Response object
        if (!$response instanceof \Symfony\Component\HttpFoundation\Response) {
            // If we get here somehow without a proper response, create one with CORS
            $response = response()->json([
                'success' => false,
                'error' => 'Invalid Response',
                'message' => 'Server returned invalid response'
            ], 500);
        }

        // Add CORS headers to all responses
        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, X-XSRF-TOKEN');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Expose-Headers', 'Authorization, Content-Disposition');

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
