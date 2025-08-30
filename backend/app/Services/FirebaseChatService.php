<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;
use App\Models\ChatConversation;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FirebaseChatService
{
    protected $database;

    public function __construct(?Database $database = null)
    {
        $this->database = $database;
    }

    /**
     * 檢查 Realtime Database 是否可用
     */
    protected function isDatabaseAvailable(): bool
    {
        if ($this->database === null) {
            return false;
        }
        
        // 檢查是否為 Mock Database 實例
        $className = get_class($this->database);
        if (str_contains($className, 'class@anonymous') || str_contains($className, 'Mock')) {
            Log::warning('Firebase Database is a mock instance, not available for real operations');
            return false;
        }
        
        return true;
    }

    /**
     * 同步對話到 Firebase Realtime Database
     */
    public function syncConversationToFirebase(ChatConversation $conversation)
    {
        // Point 19: 改進日誌記錄，處理臨時conversation物件
        $conversationId = $conversation->id ?? 'temp_' . $conversation->line_user_id;
        $isTemporary = !$conversation->exists;
        
        file_put_contents(storage_path('logs/webhook-debug.log'), 
            date('Y-m-d H:i:s') . " - Point19 - FirebaseChatService::syncConversationToFirebase called for {$conversationId}" . 
            ($isTemporary ? " (temporary object)" : " (saved object)") . 
            ", LINE ID: {$conversation->line_user_id}\n", 
            FILE_APPEND | LOCK_EX);
            
        // 檢查 Realtime Database 是否可用
        if (!$this->isDatabaseAvailable()) {
            $errorMsg = 'Realtime Database not available, skipping sync';
            $logFile = storage_path('logs/webhook-debug.log');
            if (!file_exists(dirname($logFile))) {
                @mkdir(dirname($logFile), 0755, true);
            }
            @file_put_contents($logFile, 
                date('Y-m-d H:i:s') . " - ERROR: $errorMsg\n", 
                FILE_APPEND | LOCK_EX);
                
            Log::channel('firebase')->warning($errorMsg, [
                'conversation_id' => $conversation->id,
                'database_class' => $this->database ? get_class($this->database) : 'null'
            ]);
            return false;
        }

        try {
            // Point 19: 改進customer檢查，處理臨時物件
            $customer = $conversation->customer;
            
            // 如果是臨時物件且沒有loaded customer，嘗試從customer_id載入
            if (!$customer && $conversation->customer_id) {
                $customer = \App\Models\Customer::find($conversation->customer_id);
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Point19 - Loaded customer {$customer->id} for temporary conversation\n", 
                    FILE_APPEND | LOCK_EX);
            }
            
            if (!$customer || !$conversation->line_user_id) {
                $errorMsg = "Cannot sync conversation without customer or LINE user ID: customer=" . 
                    ($customer ? $customer->id : 'null') . ", line_user_id=" . ($conversation->line_user_id ?? 'null');
                
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Point19 - SYNC FAILED: $errorMsg\n", 
                    FILE_APPEND | LOCK_EX);
                    
                Log::channel('firebase')->warning($errorMsg, [
                    'conversation_id' => $conversationId,
                    'is_temporary' => $isTemporary
                ]);
                return false;
            }

            // Point 19: 處理臨時物件的時間戳問題
            $now = \Carbon\Carbon::now();
            $messageTimestamp = $conversation->message_timestamp ?? $now;
            $createdAt = $conversation->created_at ?? $now;
            $updatedAt = $conversation->updated_at ?? $now;
            
            $conversationData = [
                'id' => $conversation->line_user_id,
                'mysqlCustomerId' => $customer->id,
                'assignedStaffId' => $customer->assigned_to,
                'customerName' => $customer->name ?: '客戶',
                'customerPhone' => $customer->phone ?: '',
                'customerRegion' => $customer->region ?: '',
                'customerSource' => $customer->website_source ?: '',
                'lastMessage' => [
                    'content' => $conversation->message_content,
                    'timestamp' => $messageTimestamp->toISOString(),
                    'senderId' => $conversation->is_from_customer ? 'customer' : 'staff'
                ],
                'unreadCount' => [
                    'staff' => $this->getUnreadCount($conversation->line_user_id, false),
                    'customer' => $this->getUnreadCount($conversation->line_user_id, true)
                ],
                'status' => 'active',
                'created' => $createdAt->toISOString(),
                'updated' => $updatedAt->toISOString(),
                'isTemporary' => $isTemporary
            ];

            // Debug log conversation data
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Point19 - About to update Firebase with data: " . json_encode($conversationData) . "\n", 
                FILE_APPEND | LOCK_EX);

            // 更新或建立對話節點
            $this->database->getReference('conversations/' . $conversation->line_user_id)
                ->update($conversationData);
                
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Point19 - Firebase conversation node updated successfully for LINE ID: {$conversation->line_user_id}\n", 
                FILE_APPEND | LOCK_EX);

            // 只有非臨時物件才同步訊息到子節點（因為臨時物件沒有真實ID）
            if (!$isTemporary) {
                $this->syncMessageToFirebase($conversation);
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Point19 - Firebase message sync completed\n", 
                    FILE_APPEND | LOCK_EX);
            } else {
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Point19 - Skipped message sync for temporary object\n", 
                    FILE_APPEND | LOCK_EX);
            }

            Log::channel('firebase')->info('Synced conversation to Firebase', [
                'conversation_id' => $conversationId,
                'line_user_id' => $conversation->line_user_id,
                'is_temporary' => $isTemporary
            ]);

            return true;
        } catch (\Exception $e) {
            $errorMsg = "Failed to sync conversation {$conversationId} to Firebase: " . $e->getMessage();
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Point19 - FIREBASE SYNC ERROR: $errorMsg\n", 
                FILE_APPEND | LOCK_EX);
                
            Log::channel('firebase')->error('Failed to sync conversation to Firebase', [
                'conversation_id' => $conversationId,
                'line_user_id' => $conversation->line_user_id,
                'is_temporary' => $isTemporary,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return false;
        }
    }

    /**
     * 同步單一訊息到 Firebase Realtime Database
     */
    public function syncMessageToFirebase(ChatConversation $conversation)
    {
        // 檢查 Realtime Database 是否可用
        if (!$this->isDatabaseAvailable()) {
            Log::channel('firebase')->warning('Realtime Database not available, skipping message sync', [
                'conversation_id' => $conversation->id
            ]);
            return false;
        }

        try {
            if (!$conversation->line_user_id) {
                return false;
            }

            $messageData = [
                'id' => 'msg_' . $conversation->id,
                'senderId' => $conversation->is_from_customer ? 'customer' : 'staff_' . $conversation->user_id,
                'content' => $conversation->message_content,
                'type' => $conversation->message_type ?? 'text',
                'timestamp' => $conversation->message_timestamp->toISOString(),
                'status' => $conversation->status,
                'lineMessageId' => $conversation->metadata['message_id'] ?? null
            ];

            $this->database->getReference('conversations/' . $conversation->line_user_id . '/messages/msg_' . $conversation->id)
                ->set($messageData);

            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to sync message to Firebase', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 從 Firebase Realtime Database 讀取對話列表
     */
    public function getConversationsFromFirebase($staffId = null)
    {
        // 檢查 Realtime Database 是否可用
        if (!$this->isDatabaseAvailable()) {
            Log::channel('firebase')->warning('Realtime Database not available, returning empty conversations list');
            return [];
        }

        try {
            $conversationsRef = $this->database->getReference('conversations');
            $snapshot = $conversationsRef->getSnapshot();
            
            $conversations = [];
            if ($snapshot->exists()) {
                foreach ($snapshot->getValue() as $lineUserId => $conversationData) {
                    // 如果指定 staffId，則過濾對話
                    if ($staffId && isset($conversationData['assignedStaffId']) && $conversationData['assignedStaffId'] != $staffId) {
                        continue;
                    }
                    
                    $conversations[] = array_merge(['firebaseId' => $lineUserId], $conversationData);
                }
                
                // 按更新時間排序
                usort($conversations, function($a, $b) {
                    return strtotime($b['updated'] ?? 0) - strtotime($a['updated'] ?? 0);
                });
            }
            
            return $conversations;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to get conversations from Firebase', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 從 Firebase Realtime Database 讀取訊息
     */
    public function getMessagesFromFirebase($lineUserId, $limit = 50)
    {
        // 檢查 Realtime Database 是否可用
        if (!$this->isDatabaseAvailable()) {
            Log::channel('firebase')->warning('Realtime Database not available, returning empty messages list');
            return [];
        }

        try {
            $messagesRef = $this->database->getReference('conversations/' . $lineUserId . '/messages');
            $snapshot = $messagesRef->orderByChild('timestamp')->limitToLast($limit)->getSnapshot();
            
            $result = [];
            if ($snapshot->exists()) {
                foreach ($snapshot->getValue() as $messageId => $messageData) {
                    $result[] = array_merge(['firebaseId' => $messageId], $messageData);
                }
                
                // 按時間戳記排序（升序）
                usort($result, function($a, $b) {
                    return strtotime($a['timestamp'] ?? 0) - strtotime($b['timestamp'] ?? 0);
                });
            }

            return $result;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to get messages from Firebase', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 更新 Firebase Realtime Database 中的已讀狀態
     */
    public function markAsReadInFirebase($lineUserId, $isCustomer = true)
    {
        // 檢查 Realtime Database 是否可用
        if (!$this->isDatabaseAvailable()) {
            Log::channel('firebase')->warning('Realtime Database not available, skipping mark as read');
            return false;
        }

        try {
            $field = $isCustomer ? 'unreadCount/customer' : 'unreadCount/staff';
            
            $updates = [
                $field => 0,
                'updated' => (new \DateTime())->format('c')
            ];
            
            $this->database->getReference('conversations/' . $lineUserId)
                ->update($updates);

            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to mark as read in Firebase', [
                'line_user_id' => $lineUserId,
                'is_customer' => $isCustomer,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 批次同步現有對話到 Firebase
     */
    public function batchSyncToFirebase($limit = 100, $offset = 0)
    {
        try {
            $conversations = ChatConversation::with('customer')
                ->whereNotNull('line_user_id')
                ->whereHas('customer', function($query) {
                    $query->whereNotNull('assigned_to');
                })
                ->orderBy('id', 'DESC')
                ->limit($limit)
                ->offset($offset)
                ->get();

            $synced = 0;
            $failed = 0;

            foreach ($conversations as $conversation) {
                if ($this->syncConversationToFirebase($conversation)) {
                    $synced++;
                } else {
                    $failed++;
                }
            }

            Log::channel('firebase')::info('Batch sync to Firebase completed', [
                'synced' => $synced,
                'failed' => $failed,
                'limit' => $limit,
                'offset' => $offset
            ]);

            return [
                'synced' => $synced,
                'failed' => $failed,
                'total_processed' => count($conversations)
            ];
        } catch (\Exception $e) {
            Log::channel('firebase')::error('Batch sync to Firebase failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 獲取未讀訊息數量
     */
    protected function getUnreadCount($lineUserId, $isFromCustomer)
    {
        return ChatConversation::where('line_user_id', $lineUserId)
            ->where('is_from_customer', $isFromCustomer)
            ->where('status', 'unread')
            ->count();
    }

    /**
     * 刪除 Firebase Realtime Database 對話
     */
    public function deleteConversationFromFirebase($lineUserId)
    {
        // 檢查 Realtime Database 是否可用
        if (!$this->isDatabaseAvailable()) {
            Log::channel('firebase')->warning('Realtime Database not available, skipping deletion');
            return false;
        }

        try {
            // 刪除整個對話節點（包含所有訊息）
            $this->database->getReference('conversations/' . $lineUserId)
                ->remove();

            Log::channel('firebase')->info('Deleted conversation from Firebase', [
                'line_user_id' => $lineUserId
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to delete conversation from Firebase', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 檢查 Firebase Realtime Database 連線狀態
     */
    public function checkFirebaseConnection()
    {
        // 檢查 Realtime Database 是否可用
        if (!$this->isDatabaseAvailable()) {
            return false;
        }

        try {
            // 使用簡單的測試路徑來檢查連線，避免使用特殊字符
            // 嘗試讀取根節點或創建一個測試節點
            $testPath = 'system/connection_test';
            $testData = ['test' => true, 'timestamp' => now()->timestamp];
            
            // 測試寫入操作
            $this->database->getReference($testPath)->set($testData);
            
            // 測試讀取操作
            $snapshot = $this->database->getReference($testPath)->getSnapshot();
            
            // 清理測試數據
            $this->database->getReference($testPath)->remove();
            
            // 如果能成功讀取到剛才寫入的數據，說明連接正常
            return $snapshot->exists() && $snapshot->getValue()['test'] === true;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Firebase connection check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 獲取所有Firebase對話記錄
     */
    public function getAllFirebaseConversations()
    {
        if (!$this->isDatabaseAvailable()) {
            return null;
        }

        try {
            $snapshot = $this->database->getReference('conversations')->getSnapshot();
            return $snapshot->exists() ? $snapshot->getValue() : [];
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to fetch all Firebase conversations', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 獲取特定LINE用戶的Firebase對話記錄
     */
    public function getFirebaseConversation($lineUserId)
    {
        if (!$this->isDatabaseAvailable()) {
            return null;
        }

        try {
            $snapshot = $this->database->getReference('conversations/' . $lineUserId)->getSnapshot();
            return $snapshot->exists() ? $snapshot->getValue() : null;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to fetch Firebase conversation', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 清理Firebase中的孤立對話記錄
     */
    public function cleanupOrphanedConversations()
    {
        if (!$this->isDatabaseAvailable()) {
            return false;
        }

        try {
            $firebaseConversations = $this->getAllFirebaseConversations();
            if (!$firebaseConversations) {
                return true;
            }

            $cleanupCount = 0;
            foreach ($firebaseConversations as $lineUserId => $conversationData) {
                // 檢查MySQL中是否存在對應的對話記錄
                $mysqlExists = ChatConversation::where('line_user_id', $lineUserId)->exists();
                
                if (!$mysqlExists) {
                    // MySQL中不存在，刪除Firebase中的記錄
                    $this->database->getReference('conversations/' . $lineUserId)->remove();
                    $cleanupCount++;
                    
                    Log::channel('firebase')->info('Cleaned up orphaned Firebase conversation', [
                        'line_user_id' => $lineUserId
                    ]);
                }
            }

            Log::channel('firebase')->info('Firebase cleanup completed', [
                'cleaned_up_count' => $cleanupCount
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Firebase cleanup failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 寫入測試資料到Firebase（用於診斷）
     */
    public function writeTestData(string $key, array $data): bool
    {
        if (!$this->isDatabaseAvailable()) {
            return false;
        }

        try {
            $this->database->getReference('diagnostic_tests/' . $key)->set($data);
            
            Log::channel('firebase')->info('Test data written successfully', [
                'key' => $key,
                'data' => $data
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to write test data', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}