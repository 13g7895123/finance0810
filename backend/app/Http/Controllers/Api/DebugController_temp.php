<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\ChatConversation;

class DebugControllerClean extends Controller
{
    /**
     * Point 20: 直接測試MySQL conversation創建功能
     * 不涉及webhook或簽名驗證，純粹測試資料庫創建
     */
    public function testMysqlConversationCreation(Request $request)
    {
        // Point 20: 完整測試MySQL資料創建功能
        $results = [];
        
        try {
            // 第一步：檢查並創建Customer
            $availableUser = \App\Models\User::first();
            if (!$availableUser) {
                return response()->json([
                    'success' => false,
                    'error' => '沒有找到可用的用戶進行分配'
                ]);
            }
            
            // 創建測試customer
            $customerData = [
                'name' => 'Point20測試客戶_' . time(),
                'phone' => '0900' . rand(100000, 999999),
                'line_user_id' => 'U_point20_test_' . time(),
                'region' => '台北市',
                'website_source' => 'Point20測試',
                'status' => 'new',
                'assigned_to' => $availableUser->id
            ];
            
            $customer = \App\Models\Customer::create($customerData);
            $results['customer_creation'] = [
                'success' => true,
                'customer_id' => $customer->id,
                'line_user_id' => $customer->line_user_id
            ];
            
            // 第二步：測試ChatConversation創建 - 這裡是Point 20的關鍵測試
            try {
                $conversationData = [
                    'customer_id' => $customer->id,
                    'line_user_id' => $customer->line_user_id,
                    'status' => 'unread',
                    'last_message' => 'Point20測試對話：' . date('Y-m-d H:i:s'),
                    'last_message_at' => now(),
                ];
                
                // 記錄創建前狀態
                file_put_contents(
                    storage_path('logs/webhook-debug.log'),
                    date('Y-m-d H:i:s') . " - Point20 - 準備創建ChatConversation，customer_id: {$customer->id}\n",
                    FILE_APPEND | LOCK_EX
                );
                
                // 嘗試創建conversation - 這會觸發model events
                $conversation = \App\Models\ChatConversation::create($conversationData);
                
                $results['conversation_creation'] = [
                    'success' => true,
                    'conversation_id' => $conversation->id,
                    'version' => $conversation->version,
                    'created_at' => $conversation->created_at->format('Y-m-d H:i:s'),
                    'model_events' => 'Point 20修復的model events成功執行'
                ];
                
                file_put_contents(
                    storage_path('logs/webhook-debug.log'),
                    date('Y-m-d H:i:s') . " - Point20 - ChatConversation創建成功，ID: {$conversation->id}, version: {$conversation->version}\n",
                    FILE_APPEND | LOCK_EX
                );
                
            } catch (\Exception $conversationError) {
                $results['conversation_creation'] = [
                    'success' => false,
                    'error' => $conversationError->getMessage(),
                    'file' => $conversationError->getFile(),
                    'line' => $conversationError->getLine(),
                    'diagnosis' => 'Point 20 model events可能仍有問題'
                ];
                
                file_put_contents(
                    storage_path('logs/webhook-debug.log'),
                    date('Y-m-d H:i:s') . " - Point20 - ChatConversation創建失敗: " . $conversationError->getMessage() . "\n",
                    FILE_APPEND | LOCK_EX
                );
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Point 20 MySQL測試完成',
                'results' => $results,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'point_20_status' => $results['conversation_creation']['success'] ? 'FIXED' : 'NEEDS_MORE_WORK'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Point 20測試失敗',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'results' => $results
            ]);
        }
    }
}