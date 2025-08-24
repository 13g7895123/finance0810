<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FirebaseSyncService
{
    protected $firebaseChatService;

    public function __construct(FirebaseChatService $firebaseChatService)
    {
        $this->firebaseChatService = $firebaseChatService;
    }

    /**
     * 雙向同步：MySQL 到 Firebase
     */
    public function syncMySQLToFirebase($conversationId = null)
    {
        try {
            DB::beginTransaction();

            $query = ChatConversation::with(['customer', 'user'])
                ->whereNotNull('line_user_id')
                ->whereHas('customer', function($q) {
                    $q->whereNotNull('assigned_to');
                });

            if ($conversationId) {
                $query->where('id', $conversationId);
            } else {
                $query->where('updated_at', '>=', now()->subHours(24)); // 只同步最近24小時的資料
            }

            $conversations = $query->get();
            
            $synced = 0;
            $failed = 0;

            foreach ($conversations as $conversation) {
                if ($this->firebaseChatService->syncConversationToFirebase($conversation)) {
                    $synced++;
                } else {
                    $failed++;
                }
            }

            DB::commit();

            Log::channel('firebase-sync')::info('MySQL to Firebase sync completed', [
                'synced' => $synced,
                'failed' => $failed,
                'total' => count($conversations),
                'conversation_id' => $conversationId
            ]);

            return [
                'success' => true,
                'synced' => $synced,
                'failed' => $failed,
                'total' => count($conversations)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('firebase-sync')::error('MySQL to Firebase sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 同步特定客戶的所有對話
     */
    public function syncCustomerToFirebase($customerId)
    {
        try {
            $customer = Customer::find($customerId);
            if (!$customer || !$customer->line_user_id) {
                return [
                    'success' => false,
                    'error' => 'Customer not found or no LINE user ID'
                ];
            }

            $conversations = ChatConversation::where('customer_id', $customerId)
                ->where('line_user_id', $customer->line_user_id)
                ->orderBy('message_timestamp', 'ASC')
                ->get();

            $synced = 0;
            $failed = 0;

            foreach ($conversations as $conversation) {
                if ($this->firebaseChatService->syncConversationToFirebase($conversation)) {
                    $synced++;
                } else {
                    $failed++;
                }
            }

            Log::channel('firebase-sync')::info('Customer sync to Firebase completed', [
                'customer_id' => $customerId,
                'line_user_id' => $customer->line_user_id,
                'synced' => $synced,
                'failed' => $failed
            ]);

            return [
                'success' => true,
                'synced' => $synced,
                'failed' => $failed,
                'customer_id' => $customerId
            ];

        } catch (\Exception $e) {
            Log::channel('firebase-sync')::error('Customer sync to Firebase failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 檢查資料一致性
     */
    public function validateDataConsistency($lineUserId = null)
    {
        try {
            $issues = [];

            // 查詢條件
            $query = ChatConversation::with('customer')
                ->whereNotNull('line_user_id');
            
            if ($lineUserId) {
                $query->where('line_user_id', $lineUserId);
            } else {
                $query->limit(100); // 限制檢查數量避免超時
            }

            $conversations = $query->get();

            foreach ($conversations as $conversation) {
                // 檢查 MySQL 資料完整性
                if (!$conversation->customer) {
                    $issues[] = [
                        'type' => 'missing_customer',
                        'conversation_id' => $conversation->id,
                        'line_user_id' => $conversation->line_user_id
                    ];
                    continue;
                }

                if (!$conversation->customer->assigned_to) {
                    $issues[] = [
                        'type' => 'missing_assignment',
                        'conversation_id' => $conversation->id,
                        'customer_id' => $conversation->customer_id
                    ];
                }

                // 檢查 Firebase 同步狀態
                $firebaseMessages = $this->firebaseChatService->getMessagesFromFirebase($conversation->line_user_id, 10);
                $mysqlMessageExists = false;

                foreach ($firebaseMessages as $fbMessage) {
                    if ($fbMessage['id'] === 'msg_' . $conversation->id) {
                        $mysqlMessageExists = true;
                        break;
                    }
                }

                if (!$mysqlMessageExists) {
                    $issues[] = [
                        'type' => 'missing_in_firebase',
                        'conversation_id' => $conversation->id,
                        'line_user_id' => $conversation->line_user_id
                    ];
                }
            }

            Log::channel('firebase-sync')::info('Data consistency validation completed', [
                'checked' => count($conversations),
                'issues' => count($issues),
                'line_user_id' => $lineUserId
            ]);

            return [
                'success' => true,
                'checked' => count($conversations),
                'issues' => $issues,
                'issues_count' => count($issues)
            ];

        } catch (\Exception $e) {
            Log::channel('firebase-sync')::error('Data consistency validation failed', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 修復資料不一致問題
     */
    public function fixDataInconsistency($issues)
    {
        $fixed = 0;
        $failed = 0;

        foreach ($issues as $issue) {
            try {
                switch ($issue['type']) {
                    case 'missing_in_firebase':
                        $conversation = ChatConversation::find($issue['conversation_id']);
                        if ($conversation && $this->firebaseChatService->syncConversationToFirebase($conversation)) {
                            $fixed++;
                        } else {
                            $failed++;
                        }
                        break;

                    case 'missing_assignment':
                        // 這個需要手動處理，記錄警告
                        Log::channel('firebase-sync')::warning('Customer missing assignment', [
                            'customer_id' => $issue['customer_id']
                        ]);
                        break;

                    case 'missing_customer':
                        // 這個是嚴重問題，需要手動修復
                        Log::channel('firebase-sync')::error('Conversation missing customer', [
                            'conversation_id' => $issue['conversation_id']
                        ]);
                        break;
                }
            } catch (\Exception $e) {
                $failed++;
                Log::channel('firebase-sync')::error('Failed to fix data inconsistency', [
                    'issue' => $issue,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::channel('firebase-sync')::info('Data inconsistency fix completed', [
            'fixed' => $fixed,
            'failed' => $failed,
            'total_issues' => count($issues)
        ]);

        return [
            'success' => true,
            'fixed' => $fixed,
            'failed' => $failed,
            'total_issues' => count($issues)
        ];
    }

    /**
     * 清理過期的 Firebase 資料
     */
    public function cleanupExpiredFirebaseData($daysOld = 30)
    {
        try {
            $cutoffDate = Carbon::now()->subDays($daysOld);
            
            // 查找需要清理的對話
            $expiredConversations = ChatConversation::where('updated_at', '<', $cutoffDate)
                ->whereNotNull('line_user_id')
                ->pluck('line_user_id')
                ->unique();

            $cleaned = 0;
            $failed = 0;

            foreach ($expiredConversations as $lineUserId) {
                // 檢查是否還有最近的活動
                $recentActivity = ChatConversation::where('line_user_id', $lineUserId)
                    ->where('updated_at', '>=', $cutoffDate)
                    ->exists();

                if (!$recentActivity) {
                    if ($this->firebaseChatService->deleteConversationFromFirebase($lineUserId)) {
                        $cleaned++;
                    } else {
                        $failed++;
                    }
                }
            }

            Log::channel('firebase-sync')::info('Firebase data cleanup completed', [
                'cleaned' => $cleaned,
                'failed' => $failed,
                'days_old' => $daysOld,
                'cutoff_date' => $cutoffDate->toISOString()
            ]);

            return [
                'success' => true,
                'cleaned' => $cleaned,
                'failed' => $failed,
                'cutoff_date' => $cutoffDate->toISOString()
            ];

        } catch (\Exception $e) {
            Log::channel('firebase-sync')::error('Firebase data cleanup failed', [
                'days_old' => $daysOld,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 獲取同步統計資料
     */
    public function getSyncStatistics()
    {
        try {
            $stats = [
                'mysql_conversations' => ChatConversation::whereNotNull('line_user_id')->count(),
                'mysql_customers_with_line' => Customer::whereNotNull('line_user_id')->count(),
                'recent_conversations' => ChatConversation::where('updated_at', '>=', now()->subHours(24))->count(),
                'unsynced_conversations' => 0, // 這個需要實際檢查 Firebase
                'firebase_connection' => $this->firebaseChatService->checkFirebaseConnection()
            ];

            Log::channel('firebase-sync')::info('Sync statistics retrieved', $stats);

            return [
                'success' => true,
                'statistics' => $stats
            ];

        } catch (\Exception $e) {
            Log::channel('firebase-sync')::error('Failed to get sync statistics', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 檢查同步健康狀態
     */
    public function checkSyncHealth()
    {
        try {
            $health = [
                'firebase_connection' => $this->firebaseChatService->checkFirebaseConnection(),
                'mysql_connection' => true, // Laravel 已連接才能執行這個方法
                'recent_sync_errors' => $this->getRecentSyncErrors(),
                'data_consistency_issues' => 0,
                'last_sync_time' => $this->getLastSyncTime(),
                'overall_status' => 'healthy'
            ];

            // 檢查整體健康狀態
            if (!$health['firebase_connection']) {
                $health['overall_status'] = 'firebase_disconnected';
            } elseif ($health['recent_sync_errors'] > 10) {
                $health['overall_status'] = 'sync_errors';
            } elseif (!$health['last_sync_time'] || Carbon::parse($health['last_sync_time'])->diffInHours(now()) > 24) {
                $health['overall_status'] = 'sync_delayed';
            }

            return [
                'success' => true,
                'health' => $health
            ];

        } catch (\Exception $e) {
            Log::channel('firebase-sync')::error('Sync health check failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'health' => [
                    'overall_status' => 'error'
                ]
            ];
        }
    }

    /**
     * 獲取最近的同步錯誤數量
     */
    protected function getRecentSyncErrors()
    {
        // 這裡可以從 Laravel 日誌或專門的錯誤表中獲取
        // 暫時回傳 0
        return 0;
    }

    /**
     * 獲取最後同步時間
     */
    protected function getLastSyncTime()
    {
        // 可以從緩存或資料庫中獲取最後同步時間
        // 暫時回傳當前時間
        return now()->toISOString();
    }
}