<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Throwable;

class BaseApiController extends Controller
{
    /**
     * 標準化成功回應格式
     */
    protected function successResponse($data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return response()->json($response, $statusCode);
    }
    
    /**
     * 標準化錯誤回應格式
     */
    protected function errorResponse(string $message, Throwable $exception = null, int $statusCode = 500): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'error' => $exception ? $exception->getMessage() : $message
        ];
        
        // 在開發模式下提供詳細錯誤資訊
        if (config('app.debug') && $exception) {
            $response['debug_info'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString())
            ];
        }
        
        // 記錄錯誤
        if ($exception) {
            \Log::error('API Error: ' . $message, [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'request_url' => request()->url(),
                'request_method' => request()->method(),
                'request_data' => request()->all()
            ]);
        }
        
        return response()->json($response, $statusCode);
    }
    
    /**
     * 處理驗證錯誤
     */
    protected function validationErrorResponse(\Illuminate\Validation\ValidationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => '輸入資料驗證失敗',
            'error' => 'Validation failed',
            'errors' => $exception->errors()
        ], 422);
    }
    
    /**
     * 處理資源未找到錯誤
     */
    protected function notFoundResponse(string $resource = 'Resource'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "{$resource}未找到",
            'error' => 'Resource not found'
        ], 404);
    }
    
    /**
     * 處理未授權錯誤
     */
    protected function unauthorizedResponse(string $message = '權限不足'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => 'Unauthorized'
        ], 403);
    }
    
    /**
     * 處理認證失敗錯誤
     */
    protected function unauthenticatedResponse(string $message = '請先登入'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => 'Authentication required'
        ], 401);
    }
}