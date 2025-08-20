<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // For API routes, provide detailed error information
        if ($request->is('api/*')) {
            // Handle authentication exceptions
            if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'Authentication required'
                ], 401);
            }
            
            // Handle validation exceptions
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'error' => 'Invalid input data',
                    'errors' => $exception->errors()
                ], 422);
            }
            
            // Handle model not found exceptions
            if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                    'error' => 'The requested resource could not be found',
                    'debug_info' => config('app.debug') ? [
                        'model' => $exception->getModel(),
                        'ids' => $exception->getIds()
                    ] : null
                ], 404);
            }
            
            // Handle database exceptions
            if ($exception instanceof \Illuminate\Database\QueryException) {
                \Log::error('Database Query Error', [
                    'message' => $exception->getMessage(),
                    'sql' => $exception->getSql(),
                    'bindings' => $exception->getBindings(),
                    'code' => $exception->getCode()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => '資料庫操作失敗',
                    'error' => 'Database operation failed',
                    'debug_info' => config('app.debug') ? [
                        'message' => $exception->getMessage(),
                        'sql' => $exception->getSql(),
                        'code' => $exception->getCode()
                    ] : null
                ], 500);
            }
            
            // Handle general exceptions for API routes
            \Log::error('API Exception', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'request_url' => $request->url(),
                'request_method' => $request->method(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $exception->getMessage() : 'An unexpected error occurred',
                'debug_info' => config('app.debug') ? [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => explode("\n", $exception->getTraceAsString())
                ] : null
            ], 500);
        }

        return parent::render($request, $exception);
    }
}