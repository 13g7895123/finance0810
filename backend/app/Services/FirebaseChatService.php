<?php

namespace App\Services;

use Kreait\Firebase\Contract\Firestore;
use App\Models\ChatConversation;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FirebaseChatService
{
    protected $firestore;

    public function __construct(Firestore $firestore)
    {
        $this->firestore = $firestore;
    }

    /**
     * 同步對話到 Firebase
     */
    public function syncConversationToFirebase(ChatConversation $conversation)
    {
        try {
            $customer = $conversation->customer;
            if (!$customer || !$conversation->line_user_id) {
                Log::channel('firebase')::warning('Cannot sync conversation without customer or LINE user ID', [
                    'conversation_id' => $conversation->id
                ]);
                return false;
            }

            $conversationData = [
                'id' => $conversation->line_user_id,
                'mysqlCustomerId' => $customer->id,
                'assignedStaffId' => $customer->assigned_to,
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

            // 更新或建立對話文檔
            $this->firestore->collection('conversations')
                ->document($conversation->line_user_id)
                ->set($conversationData, ['merge' => true]);

            // 同步訊息到子集合
            $this->syncMessageToFirebase($conversation);

            Log::channel('firebase')::info('Synced conversation to Firebase', [
                'conversation_id' => $conversation->id,
                'line_user_id' => $conversation->line_user_id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')::error('Failed to sync conversation to Firebase', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 同步單一訊息到 Firebase
     */
    public function syncMessageToFirebase(ChatConversation $conversation)
    {
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

            $this->firestore->collection('conversations')
                ->document($conversation->line_user_id)
                ->collection('messages')
                ->document('msg_' . $conversation->id)
                ->set($messageData);

            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')::error('Failed to sync message to Firebase', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 從 Firebase 讀取對話列表
     */
    public function getConversationsFromFirebase($staffId = null)
    {
        try {
            $query = $this->firestore->collection('conversations');
            
            if ($staffId) {
                $query = $query->where('assignedStaffId', '=', $staffId);
            }
            
            $documents = $query->orderBy('updated', 'DESC')->documents();
            
            $conversations = [];
            foreach ($documents as $document) {
                if ($document->exists()) {
                    $data = $document->data();
                    $conversations[] = array_merge(['firebaseId' => $document->id()], $data);
                }
            }
            
            return $conversations;
        } catch (\Exception $e) {
            Log::channel('firebase')::error('Failed to get conversations from Firebase', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 從 Firebase 讀取訊息
     */
    public function getMessagesFromFirebase($lineUserId, $limit = 50)
    {
        try {
            $messages = $this->firestore->collection('conversations')
                ->document($lineUserId)
                ->collection('messages')
                ->orderBy('timestamp', 'ASC')
                ->limit($limit)
                ->documents();

            $result = [];
            foreach ($messages as $message) {
                if ($message->exists()) {
                    $data = $message->data();
                    $result[] = array_merge(['firebaseId' => $message->id()], $data);
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::channel('firebase')::error('Failed to get messages from Firebase', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 更新 Firebase 中的已讀狀態
     */
    public function markAsReadInFirebase($lineUserId, $isCustomer = true)
    {
        try {
            $field = $isCustomer ? 'unreadCount.customer' : 'unreadCount.staff';
            
            $this->firestore->collection('conversations')
                ->document($lineUserId)
                ->update([
                    [$field => 0],
                    ['updated' => new \Google\Cloud\Core\Timestamp(new \DateTime())]
                ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')::error('Failed to mark as read in Firebase', [
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
     * 刪除 Firebase 對話
     */
    public function deleteConversationFromFirebase($lineUserId)
    {
        try {
            // 刪除訊息子集合
            $messages = $this->firestore->collection('conversations')
                ->document($lineUserId)
                ->collection('messages')
                ->documents();

            foreach ($messages as $message) {
                $message->reference()->delete();
            }

            // 刪除對話文檔
            $this->firestore->collection('conversations')
                ->document($lineUserId)
                ->delete();

            Log::channel('firebase')::info('Deleted conversation from Firebase', [
                'line_user_id' => $lineUserId
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')::error('Failed to delete conversation from Firebase', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 檢查 Firebase 連線狀態
     */
    public function checkFirebaseConnection()
    {
        try {
            // 嘗試讀取一個簡單的測試文檔
            $this->firestore->collection('_health_check')->limit(1)->documents();
            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')::error('Firebase connection check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}