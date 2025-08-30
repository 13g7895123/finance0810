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
    ];
    
    /**
     * Boot the model and register event listeners.
     */
    protected static function booted()
    {
        // 當創建新訊息時 - Point 20: 強化錯誤處理，防止阻擋創建流程
        static::creating(function ($conversation) {
            // Point 20: 安全的版本設定，絕不中斷創建
            try {
                try {
                    $versionService = app(\App\Services\ChatVersionService::class);
                    $newVersion = $versionService->incrementVersion();
                    $conversation->version = $newVersion;
                    
                    // 記錄版本設定成功
                    @file_put_contents(storage_path('logs/webhook-debug.log'), 
                        date('Y-m-d H:i:s') . " - Point20 - Version set to {$newVersion} for conversation\n", 
                        FILE_APPEND | LOCK_EX);
                        
                } catch (\Exception $e) {
                    // 版本服務失敗時使用時間戳作為版本
                    $conversation->version = time();
                    
                    @file_put_contents(storage_path('logs/webhook-debug.log'), 
                        date('Y-m-d H:i:s') . " - Point20 - ChatVersionService failed, using timestamp: " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                }
                
            } catch (\Exception $e) {
                // 如果連時間戳都設定失敗，使用當前時間戳
                $conversation->version = time();
                @file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Point20 - Critical: Version setting completely failed, using time(): " . $e->getMessage() . "\n", 
                    FILE_APPEND | LOCK_EX);
            }
            
            // Point 20: Firebase 同步 - 完全隔離，絕不影響創建
            try {
                static::syncToFirebaseAsync($conversation, 'sync');
                
                @file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Point20 - Firebase sync job dispatched for conversation\n", 
                    FILE_APPEND | LOCK_EX);
                    
            } catch (\Exception $e) {
                @file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Point20 - Firebase sync job dispatch failed (non-critical): " . $e->getMessage() . "\n", 
                    FILE_APPEND | LOCK_EX);
            }
        });
        
        // 當更新訊息時
        static::updating(function ($conversation) {
            // 如果 version 字段沒有被明確設置，則自動增加版本號
            if (!$conversation->isDirty('version')) {
                try {
                    $versionService = app(\App\Services\ChatVersionService::class);
                    $newVersion = $versionService->incrementVersion();
                    $conversation->version = $newVersion;
                } catch (\Exception $e) {
                    // 版本服務失敗時使用時間戳作為版本
                    $conversation->version = time();
                    
                    \Log::channel('firebase')->warning('ChatVersionService failed during conversation update', [
                        'error' => $e->getMessage(),
                        'conversation_id' => $conversation->id
                    ]);
                }
            }
            
            // Firebase 同步 - 背景處理
            try {
                static::syncToFirebaseAsync($conversation, 'sync');
            } catch (\Exception $e) {
                \Log::channel('firebase')->warning('Firebase sync job dispatch failed during conversation update', [
                    'error' => $e->getMessage(),
                    'conversation_id' => $conversation->id
                ]);
            }
        });

        // 當訊息創建完成後，更新員工統計 - Point 20: 非關鍵性功能，隔離錯誤
        static::created(function ($conversation) {
            try {
                static::updateStaffStatsAsync($conversation);
                
                @file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Point20 - Staff stats update job dispatched for conversation {$conversation->id}\n", 
                    FILE_APPEND | LOCK_EX);
                    
            } catch (\Exception $e) {
                @file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Point20 - Staff stats update failed (non-critical) for conversation {$conversation->id}: " . $e->getMessage() . "\n", 
                    FILE_APPEND | LOCK_EX);
                    
                // Point 20: 不拋出異常，確保不影響主要流程
            }
        });

        // 當訊息更新完成後，檢查是否需要更新員工統計
        static::updated(function ($conversation) {
            try {
                // 如果狀態或客戶分配有變化，更新員工統計
                if ($conversation->wasChanged(['status', 'customer_id']) || 
                    ($conversation->customer && $conversation->customer->wasChanged('assigned_to'))) {
                    static::updateStaffStatsAsync($conversation);
                }
            } catch (\Exception $e) {
                \Log::channel('firebase')->warning('Staff stats update failed during conversation updated event', [
                    'error' => $e->getMessage(),
                    'conversation_id' => $conversation->id
                ]);
            }
        });
        
        // 當刪除訊息時
        static::deleted(function ($conversation) {
            try {
                if ($conversation->line_user_id) {
                    static::syncToFirebaseAsync($conversation, 'delete');
                }
            } catch (\Exception $e) {
                \Log::channel('firebase')->warning('Firebase delete sync failed during conversation deleted event', [
                    'error' => $e->getMessage(),
                    'conversation_id' => $conversation->id
                ]);
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