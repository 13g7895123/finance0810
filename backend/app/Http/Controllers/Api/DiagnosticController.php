<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\ChatConversation;

class DiagnosticController extends Controller
{
    /**
     * 最基本的健康檢查
     */
    public function basicHealth(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'php_version' => phpversion(),
            'laravel_version' => app()->version()
        ]);
    }
    
    /**
     * 檢查資料庫連接和資料
     */
    public function databaseCheck(): JsonResponse
    {
        try {
            // 檢查資料庫連接
            DB::connection()->getPdo();
            
            $result = [
                'database_connected' => true,
                'customers_count' => 0,
                'conversations_count' => 0,
                'recent_conversations' => [],
                'timestamp' => now()->toISOString()
            ];
            
            // 檢查 customers 表
            if (\Schema::hasTable('customers')) {
                $result['customers_count'] = DB::table('customers')->count();
                $result['recent_customers'] = DB::table('customers')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->select('id', 'name', 'line_user_id', 'created_at')
                    ->get();
            }
            
            // 檢查 chat_conversations 表
            if (\Schema::hasTable('chat_conversations')) {
                $result['conversations_count'] = DB::table('chat_conversations')->count();
                $result['recent_conversations'] = DB::table('chat_conversations')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->select('id', 'line_user_id', 'message_content', 'is_from_customer', 'created_at')
                    ->get();
            }
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'database_connected' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }
    
    /**
     * 測試建立客戶和對話記錄
     */
    public function testDataCreation(): JsonResponse
    {
        try {
            $testUserId = 'diagnostic_test_' . time();
            $result = [
                'test_user_id' => $testUserId,
                'customer_created' => false,
                'conversation_created' => false,
                'cleanup_success' => false,
                'errors' => []
            ];
            
            // 建立測試客戶
            try {
                $customer = Customer::create([
                    'name' => 'Diagnostic Test Customer',
                    'phone' => '0900000000',
                    'line_user_id' => $testUserId,
                    'channel' => 'line',
                    'status' => 'new',
                    'tracking_status' => 'pending',
                    'version' => 1,
                    'version_updated_at' => now()
                ]);
                
                $result['customer_created'] = true;
                $result['customer_id'] = $customer->id;
                
            } catch (\Exception $e) {
                $result['errors']['customer_creation'] = $e->getMessage();
            }
            
            // 建立測試對話記錄
            if ($result['customer_created']) {
                try {
                    $conversation = ChatConversation::create([
                        'customer_id' => $customer->id,
                        'user_id' => null,
                        'line_user_id' => $testUserId,
                        'platform' => 'line',
                        'message_type' => 'text',
                        'message_content' => 'Diagnostic test message',
                        'message_timestamp' => now(),
                        'is_from_customer' => true,
                        'status' => 'unread'
                    ]);
                    
                    $result['conversation_created'] = true;
                    $result['conversation_id'] = $conversation->id;
                    
                    // 清理測試資料
                    $conversation->delete();
                    $customer->delete();
                    $result['cleanup_success'] = true;
                    
                } catch (\Exception $e) {
                    $result['errors']['conversation_creation'] = $e->getMessage();
                    
                    // 嘗試清理客戶記錄
                    if (isset($customer)) {
                        try {
                            $customer->delete();
                            $result['customer_cleanup'] = true;
                        } catch (\Exception $cleanupError) {
                            $result['errors']['customer_cleanup'] = $cleanupError->getMessage();
                        }
                    }
                }
            }
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Test data creation failed',
                'message' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }
}