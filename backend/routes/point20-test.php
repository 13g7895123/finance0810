<?php

// Point 20: MySQL測試專用路由文件
// 使用方式: include 在 web.php 或獨立訪問

use Illuminate\Support\Facades\Route;

Route::get('/test-mysql-point20', function () {
    try {
        // 檢查用戶
        $user = \App\Models\User::first();
        if (!$user) {
            return response()->json(['error' => '沒有可用用戶', 'success' => false]);
        }
        
        // 創建測試客戶
        $customerData = [
            'name' => 'Point20測試_' . time(),
            'phone' => '0900' . rand(100000, 999999),
            'line_user_id' => 'U_point20_' . time(),
            'region' => '台北市',
            'website_source' => 'Point20測試',
            'status' => 'new',
            'assigned_to' => $user->id
        ];
        
        $customer = \App\Models\Customer::create($customerData);
        
        // 測試ChatConversation創建
        $conversationData = [
            'customer_id' => $customer->id,
            'line_user_id' => $customer->line_user_id,
            'status' => 'unread',
            'last_message' => 'Point20測試訊息: ' . now()->format('H:i:s'),
            'last_message_at' => now(),
        ];
        
        // 記錄測試開始
        file_put_contents(
            storage_path('logs/webhook-debug.log'),
            date('Y-m-d H:i:s') . " - Point20測試 - 開始創建ChatConversation，customer_id: {$customer->id}\n",
            FILE_APPEND | LOCK_EX
        );
        
        $conversation = \App\Models\ChatConversation::create($conversationData);
        
        file_put_contents(
            storage_path('logs/webhook-debug.log'),
            date('Y-m-d H:i:s') . " - Point20測試 - ChatConversation創建成功，ID: {$conversation->id}, version: {$conversation->version}\n",
            FILE_APPEND | LOCK_EX
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Point 20 MySQL測試完全成功！',
            'results' => [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'line_user_id' => $customer->line_user_id,
                    'status' => $customer->status
                ],
                'conversation' => [
                    'id' => $conversation->id,
                    'version' => $conversation->version,
                    'status' => $conversation->status,
                    'last_message' => $conversation->last_message
                ]
            ],
            'point_20_status' => 'MYSQL_CREATION_WORKING',
            'timestamp' => now()->format('Y-m-d H:i:s')
        ]);
        
    } catch (\Exception $e) {
        file_put_contents(
            storage_path('logs/webhook-debug.log'),
            date('Y-m-d H:i:s') . " - Point20測試失敗: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n",
            FILE_APPEND | LOCK_EX
        );
        
        return response()->json([
            'success' => false,
            'message' => 'Point 20 MySQL測試失敗',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'point_20_status' => 'NEEDS_MORE_WORK'
        ], 200); // 使用200避免被當作500錯誤
    }
});