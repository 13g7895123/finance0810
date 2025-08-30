<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'user_id',
        'line_user_id',
        'platform',
        'message_type',
        'message_content',
        'message_timestamp',
        'is_from_customer',
        'reply_content',
        'replied_at',
        'replied_by',
        'status',
        'metadata',
        'version',
        'version_updated_at', // Point 24: Added required field
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'message_timestamp' => 'datetime',
        'replied_at' => 'datetime',
        'is_from_customer' => 'boolean',
        'metadata' => 'array',
        'version' => 'integer',
        'version_updated_at' => 'datetime', // Point 24: Added casting for required field
    ];
    
    /**
     * Boot the model and register event listeners.
     */
    protected static function booted()
    {
        // Point 22: 簡化事件監聽器，專注於基本寫入功能
        // 移除所有可能導致MySQL寫入失敗的外部依賴
        
        static::creating(function ($conversation) {
            // Point 22: 最簡單的版本設定 - 只使用時間戳
            if (!$conversation->version) {
                $conversation->version = time();
            }
            // Point 24: 自動設定 version_updated_at 字段
            if (!$conversation->version_updated_at) {
                $conversation->version_updated_at = now();
            }
        });
        
        static::updating(function ($conversation) {
            // Point 22: 更新時也使用簡單的版本設定
            if (!$conversation->isDirty('version')) {
                $conversation->version = time();
            }
            // Point 24: 更新時也設定 version_updated_at
            if (!$conversation->isDirty('version_updated_at')) {
                $conversation->version_updated_at = now();
            }
        });
    }

    /**
     * Get the customer this conversation belongs to
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user this conversation belongs to
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who replied to this message
     */
    public function replier()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    /**
     * Scope to get unread messages
     */
    public function scopeUnread($query)
    {
        return $query->where('status', 'unread');
    }

    /**
     * Scope to get messages from customers
     */
    public function scopeFromCustomers($query)
    {
        return $query->where('is_from_customer', true);
    }

    /**
     * 同步到 Firebase - 同步方式
     */
    public function syncToFirebase()
    {
        if (!$this->line_user_id) {
            return false;
        }

        try {
            $firebaseChatService = app(\App\Services\FirebaseChatService::class);
            return $firebaseChatService->syncConversationToFirebase($this);
        } catch (\Exception $e) {
            \Log::channel('firebase')->error('Failed to sync conversation to Firebase', [
                'conversation_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 從 Firebase 更新資料
     */
    public function updateFromFirebase($firebaseData)
    {
        try {
            // 這裡實現從 Firebase 資料更新 MySQL 記錄的邏輯
            // 目前混合架構主要是單向同步（MySQL -> Firebase）
            // 如果需要雙向同步，在這裡實現
            
            \Log::channel('firebase')->info('Update from Firebase not implemented yet', [
                'conversation_id' => $this->id,
                'firebase_data_keys' => array_keys($firebaseData ?? [])
            ]);
            
            return true;
        } catch (\Exception $e) {
            \Log::channel('firebase')->error('Failed to update from Firebase', [
                'conversation_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 檢查是否已同步到 Firebase
     */
    public function isFirebaseSynced()
    {
        if (!$this->line_user_id) {
            return false;
        }

        try {
            $firebaseChatService = app(\App\Services\FirebaseChatService::class);
            $messages = $firebaseChatService->getMessagesFromFirebase($this->line_user_id, 10);
            
            // 檢查是否存在對應的訊息
            foreach ($messages as $message) {
                if ($message['id'] === 'msg_' . $this->id) {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            \Log::channel('firebase')->error('Failed to check Firebase sync status', [
                'conversation_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 背景同步到 Firebase - 異步處理
     */
    protected static function syncToFirebaseAsync($conversation, $operation = 'sync')
    {
        // 檢查是否啟用 Firebase 功能
        if (!config('services.firebase.project_id') || !env('FIREBASE_ENABLED', true)) {
            return;
        }

        try {
            // 使用隊列進行背景處理
            $additionalData = [];
            
            if ($operation === 'delete' && $conversation->line_user_id) {
                $additionalData['line_user_id'] = $conversation->line_user_id;
            }

            \App\Jobs\SyncChatConversationJob::dispatch(
                $conversation->id,
                $operation,
                $additionalData
            )->delay(now()->addSeconds(5)); // 延遲 5 秒執行，避免資料庫事務問題

        } catch (\Exception $e) {
            \Log::channel('firebase')->error('Failed to dispatch Firebase sync job', [
                'conversation_id' => $conversation->id,
                'operation' => $operation,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 批次同步多個對話到 Firebase
     */
    public static function batchSyncToFirebase($conversationIds)
    {
        $results = [
            'synced' => 0,
            'failed' => 0,
            'total' => count($conversationIds)
        ];

        foreach ($conversationIds as $id) {
            $conversation = static::find($id);
            if ($conversation && $conversation->syncToFirebase()) {
                $results['synced']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * 獲取需要同步的對話
     */
    public static function getNeedsSyncConversations($limit = 100)
    {
        return static::whereNotNull('line_user_id')
            ->whereHas('customer', function($query) {
                $query->whereNotNull('assigned_to');
            })
            ->where('updated_at', '>=', now()->subHours(24))
            ->limit($limit)
            ->get();
    }

    /**
     * 異步更新員工統計資料
     */
    protected static function updateStaffStatsAsync($conversation)
    {
        // 檢查是否啟用 Firebase 功能
        if (!config('services.firebase.project_id') || !env('FIREBASE_ENABLED', true)) {
            return;
        }

        try {
            // 獲取負責該客戶的員工ID
            $staffId = null;
            if ($conversation->customer && $conversation->customer->assigned_to) {
                $staffId = $conversation->customer->assigned_to;
            }

            if ($staffId) {
                // 調度更新特定員工的統計資料
                \App\Jobs\UpdateStaffUnreadStatsJob::dispatch($staffId)
                    ->delay(now()->addSeconds(10)); // 延遲 10 秒執行，確保資料庫變更完成
                
                \Log::channel('firebase')->info('Staff stats update job dispatched', [
                    'staff_id' => $staffId,
                    'conversation_id' => $conversation->id,
                    'trigger' => 'conversation_change'
                ]);
            }
        } catch (\Exception $e) {
            \Log::channel('firebase')->error('Failed to dispatch staff stats update job', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}