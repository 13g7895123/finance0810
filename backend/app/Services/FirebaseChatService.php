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
        return $this->database !== null;
    }

    /**
     * 同步對話到 Firebase Realtime Database
     */
    public function syncConversationToFirebase(ChatConversation $conversation)
    {
        // 檢查 Realtime Database 是否可用
        if (!$this->database) {
            Log::channel('firebase')->warning('Realtime Database not available, skipping sync', [
                'conversation_id' => $conversation->id
            ]);
            return false;
        }

        try {
            $customer = $conversation->customer;
            if (!$customer || !$conversation->line_user_id) {
                Log::channel('firebase')->warning('Cannot sync conversation without customer or LINE user ID', [
                    'conversation_id' => $conversation->id
                ]);
                return false;
            }

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
                    'timestamp' => $conversation->message_timestamp->toISOString(),
                    'senderId' => $conversation->is_from_customer ? 'customer' : 'staff'
                ],
                'unreadCount' => [
                    'staff' => $this->getUnreadCount($conversation->line_user_id, false),
                    'customer' => $this->getUnreadCount($conversation->line_user_id, true)
                ],
                'status' => 'active',
                'created' => $conversation->created_at->toISOString(),
                'updated' => $conversation->updated_at->toISOString()
            ];

            // 更新或建立對話節點
            $this->database->getReference('conversations/' . $conversation->line_user_id)
                ->update($conversationData);

            // 同步訊息到子節點
            $this->syncMessageToFirebase($conversation);

            Log::channel('firebase')->info('Synced conversation to Firebase', [
                'conversation_id' => $conversation->id,
                'line_user_id' => $conversation->line_user_id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to sync conversation to Firebase', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
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
            // 嘗試讀取根節點來測試連線
            $this->database->getReference('.info/connected')->getSnapshot();
            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Firebase connection check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}