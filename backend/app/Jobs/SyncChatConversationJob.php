<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\ChatConversation;
use App\Services\FirebaseChatService;

class SyncChatConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $conversationId;
    protected $operation;
    protected $additionalData;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct($conversationId, $operation = 'sync', $additionalData = [])
    {
        $this->conversationId = $conversationId;
        $this->operation = $operation;
        $this->additionalData = $additionalData;
        
        // 設定隊列名稱
        $this->onQueue('firebase-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(FirebaseChatService $firebaseChatService)
    {
        try {
            Log::channel('firebase-sync')::info('Firebase sync job started', [
                'conversation_id' => $this->conversationId,
                'operation' => $this->operation,
                'attempt' => $this->attempts()
            ]);

            switch ($this->operation) {
                case 'sync':
                    $this->handleSync($firebaseChatService);
                    break;
                
                case 'delete':
                    $this->handleDelete($firebaseChatService);
                    break;
                
                case 'mark_read':
                    $this->handleMarkRead($firebaseChatService);
                    break;
                
                case 'batch_sync':
                    $this->handleBatchSync($firebaseChatService);
                    break;
                
                default:
                    throw new \InvalidArgumentException("Unknown operation: {$this->operation}");
            }

            Log::channel('firebase-sync')::info('Firebase sync job completed successfully', [
                'conversation_id' => $this->conversationId,
                'operation' => $this->operation
            ]);

        } catch (\Exception $e) {
            Log::channel('firebase-sync')::error('Firebase sync job failed', [
                'conversation_id' => $this->conversationId,
                'operation' => $this->operation,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 如果是最後一次嘗試，記錄到失敗隊列
            if ($this->attempts() >= $this->tries) {
                Log::channel('firebase-sync')::error('Firebase sync job failed permanently', [
                    'conversation_id' => $this->conversationId,
                    'operation' => $this->operation,
                    'final_error' => $e->getMessage()
                ]);
            }

            throw $e;
        }
    }

    /**
     * 處理同步操作
     */
    protected function handleSync(FirebaseChatService $firebaseChatService)
    {
        $conversation = ChatConversation::with(['customer', 'user'])->find($this->conversationId);
        
        if (!$conversation) {
            Log::channel('firebase-sync')::warning('Conversation not found for sync', [
                'conversation_id' => $this->conversationId
            ]);
            return;
        }

        if (!$conversation->line_user_id) {
            Log::channel('firebase-sync')::warning('Conversation has no LINE user ID', [
                'conversation_id' => $this->conversationId
            ]);
            return;
        }

        $success = $firebaseChatService->syncConversationToFirebase($conversation);
        
        if (!$success) {
            throw new \Exception('Failed to sync conversation to Firebase');
        }
    }

    /**
     * 處理刪除操作
     */
    protected function handleDelete(FirebaseChatService $firebaseChatService)
    {
        $lineUserId = $this->additionalData['line_user_id'] ?? null;
        
        if (!$lineUserId) {
            throw new \InvalidArgumentException('LINE user ID required for delete operation');
        }

        $success = $firebaseChatService->deleteConversationFromFirebase($lineUserId);
        
        if (!$success) {
            throw new \Exception('Failed to delete conversation from Firebase');
        }
    }

    /**
     * 處理已讀標記操作
     */
    protected function handleMarkRead(FirebaseChatService $firebaseChatService)
    {
        $lineUserId = $this->additionalData['line_user_id'] ?? null;
        $isCustomer = $this->additionalData['is_customer'] ?? false;
        
        if (!$lineUserId) {
            throw new \InvalidArgumentException('LINE user ID required for mark read operation');
        }

        $success = $firebaseChatService->markAsReadInFirebase($lineUserId, $isCustomer);
        
        if (!$success) {
            throw new \Exception('Failed to mark as read in Firebase');
        }
    }

    /**
     * 處理批次同步操作
     */
    protected function handleBatchSync(FirebaseChatService $firebaseChatService)
    {
        $limit = $this->additionalData['limit'] ?? 50;
        $offset = $this->additionalData['offset'] ?? 0;

        $result = $firebaseChatService->batchSyncToFirebase($limit, $offset);
        
        if (!$result || $result['failed'] > 0) {
            throw new \Exception("Batch sync completed with {$result['failed']} failures");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::channel('firebase-sync')::error('Firebase sync job failed permanently', [
            'conversation_id' => $this->conversationId,
            'operation' => $this->operation,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // 這裡可以發送通知或執行其他失敗處理邏輯
        // 例如：發送 Slack 通知、記錄到監控系統等
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags()
    {
        return [
            'firebase-sync',
            $this->operation,
            "conversation:{$this->conversationId}"
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff()
    {
        // 指數退避：第一次重試等待 5 秒，第二次 10 秒，第三次 20 秒
        return [5, 10, 20];
    }

    /**
     * Determine if the job should be retried based on the exception.
     */
    public function retryUntil()
    {
        // 設定重試截止時間（從現在開始 5 分鐘）
        return now()->addMinutes(5);
    }
}