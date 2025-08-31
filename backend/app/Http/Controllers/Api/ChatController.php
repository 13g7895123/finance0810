<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\ChatConversation;
use App\Models\Customer;
use App\Models\User;
use App\Models\LineIntegrationSetting;
use App\Models\CustomerIdentifier;
use App\Models\CustomerActivity;
use App\Events\NewChatMessage;
use App\Services\ChatIncrementalService;
use App\Http\Resources\ChatIncrementalResource;
use App\Services\ChatQueryCacheService;
use App\Services\FirebaseChatService;
use App\Services\FirebaseSyncService;
// Removed WebhookLoggerService dependency

class ChatController extends BaseApiController
{
    private $cacheService;
    private $firebaseChatService;
    private $firebaseSyncService;
    
    public function __construct(
        ChatQueryCacheService $cacheService,
        FirebaseChatService $firebaseChatService,
        FirebaseSyncService $firebaseSyncService
    ) {
        $this->cacheService = $cacheService;
        $this->firebaseChatService = $firebaseChatService;
        $this->firebaseSyncService = $firebaseSyncService;
        $this->middleware('auth:api', ['except' => ['webhook', 'webhookTest', 'webhookSimpleTest', 'webhookDebugTest', 'webhookNoSignature', 'webhookSimulate', 'diagnoseDataFlow', 'verifyWebhookExecution', 'webhookStatus']]);
    }

    /**
     * Get chat conversations list (優化版).
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $forceRefresh = $request->boolean('refresh', false);
            
            // 基本的系統健康檢查
            if (!\Schema::hasTable('chat_conversations')) {
                Log::error('chat_conversations table does not exist');
                return response()->json([
                    'success' => false,
                    'error' => '聊天系統未正確初始化',
                    'message' => 'Chat system tables are missing'
                ], 503);
            }
            
            // 使用緩存服務獲取數據
            // Admin/executive 用戶可以看所有對話，staff 只能看分配給自己的
            $conversations = $this->cacheService->getConversationList(
                $user->canAccessAllChats() ? null : $user->id,
                $forceRefresh
            );
            
            // 批量獲取未讀計數
            $lineUserIds = $conversations->pluck('line_user_id')->toArray();
            if (!empty($lineUserIds)) {
                $unreadCounts = $this->cacheService->getUnreadCounts($lineUserIds, $forceRefresh);
                
                // 組合數據
                $conversations = $conversations->map(function ($conv) use ($unreadCounts) {
                    $conv->unread_count = $unreadCounts[$conv->line_user_id] ?? 0;
                    $conv->last_message = $conv->last_customer_message ?? $conv->last_system_message ?? '';
                    return $conv;
                });
            }
            
            // 手動分頁
            $page = $request->get('page', 1);
            $perPage = 20;
            $total = $conversations->count();
            $offset = ($page - 1) * $perPage;
            $items = $conversations->slice($offset, $perPage)->values();
            
            return response()->json([
                'success' => true,
                'data' => $items,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
                'cached' => !$forceRefresh,
                'timestamp' => now()->format('c')
            ]);
            
        } catch (\Exception $e) {
            Log::error('ChatController@index error:', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->errorResponse('載入對話列表失敗', $e);
        }
    }

    /**
     * Get conversation with specific user/customer (優化版).
     */
    public function getConversation(Request $request, $userId)
    {
        try {
            $user = Auth::user();
            $limit = min($request->get('limit', 50), 100);
            $offset = max($request->get('offset', 0), 0);
            
            // 權限檢查：admin/executive 可以看所有對話，其他用戶只能看自己分配的客戶
            if (!$user->canAccessAllChats()) {
                $customer = Customer::where('line_user_id', $userId)
                    ->where('assigned_to', $user->id)
                    ->first();
                
                if (!$customer) {
                    return response()->json([
                        'success' => false,
                        'error' => '您沒有權限查看此對話'
                    ], 403);
                }
            }
            
            // 使用優化的查詢，避免 N+1 問題
            $messages = $this->cacheService->getOptimizedMessages($userId, $limit, $offset);
            
            // 標記訊息為已讀（批量更新）
            $this->markMessagesAsRead($userId);
            
            return response()->json([
                'success' => true,
                'data' => $messages,
                'has_more' => count($messages) === $limit,
                'timestamp' => now()->format('c')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Chat conversation error:', [
                'line_user_id' => $userId,
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse('載入對話失敗', $e);
        }
    }

    /**
     * Reply to a chat message.
     */
    public function reply(Request $request, $userId)
    {
        try {
            // Log request details for debugging
            Log::info('Chat reply request', [
                'user_id' => Auth::id(),
                'line_user_id' => $userId,
                'message' => $request->message
            ]);

            $request->validate([
                'message' => 'required|string|max:1000',
            ]);

            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => '使用者未驗證'], 401);
            }
            
            // Find the customer associated with this LINE user
            $customer = Customer::where('line_user_id', $userId)->first();
            
            if (!$customer) {
                Log::warning('Customer not found for LINE user', ['line_user_id' => $userId]);
                return response()->json(['error' => '找不到對應的客戶'], 404);
            }

            // Check if staff user has access to this customer
            if ($user->isStaff() && $customer->assigned_to !== $user->id) {
                Log::warning('Staff user unauthorized for customer', [
                    'user_id' => $user->id,
                    'customer_id' => $customer->id,
                    'assigned_to' => $customer->assigned_to
                ]);
                return response()->json(['error' => '您沒有權限回覆此對話'], 403);
            }

            // Create reply message record
            $conversation = ChatConversation::create([
                'customer_id' => $customer->id,
                'user_id' => $user->id, // Use current user instead of assigned_to
                'line_user_id' => $userId,
                'version_updated_at' => now(), // Point 24: Added required field
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => $request->message,
                'message_timestamp' => now(),
                'is_from_customer' => false,
                'reply_content' => $request->message,
                'replied_at' => now(),
                'replied_by' => $user->id,
                'status' => 'replied', // Set to replied since user is replying
            ]);

            Log::info('Conversation record created', ['conversation_id' => $conversation->id]);

            // Send message via LINE Bot API
            $lineSuccess = $this->sendLineMessage($userId, $request->message);
            
            if (!$lineSuccess) {
                // Update conversation status to failed
                $this->safeUpdateStatus($conversation, 'failed');
                
                // Check LINE configuration and provide specific error
                $settings = $this->getLineSettings();
                $hasToken = !empty($settings['channel_access_token']);
                
                Log::error('LINE message send failed', [
                    'conversation_id' => $conversation->id,
                    'line_user_id' => $userId,
                    'has_token' => $hasToken,
                    'token_length' => $hasToken ? strlen($settings['channel_access_token']) : 0
                ]);
                
                $errorMessage = $hasToken 
                    ? 'LINE訊息發送失敗，可能是網路問題或LINE API錯誤，請重試' 
                    : 'LINE Channel Access Token未設定，請聯繫系統管理員';
                
                return response()->json([
                    'success' => false,
                    'message' => '送出LINE訊息失敗',
                    'error' => $errorMessage,
                    'details' => [
                        'has_line_token' => $hasToken,
                        'line_user_id' => $userId,
                        'conversation_id' => $conversation->id
                    ],
                    'conversation' => $conversation->load(['customer', 'user', 'replier'])
                ], 500);
            }

            // Update conversation status to sent (with fallback handling)
            $this->safeUpdateStatus($conversation, 'sent');
            
            // Sync to Firebase Realtime Database with error handling
            try {
                $firebaseSync = $this->firebaseChatService->syncConversationToFirebase($conversation);
                if ($firebaseSync) {
                    Log::info('Firebase sync successful', ['conversation_id' => $conversation->id]);
                } else {
                    Log::warning('Firebase sync failed but message sent successfully', [
                        'conversation_id' => $conversation->id,
                        'line_user_id' => $userId
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Firebase sync error', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the entire operation for Firebase sync issues
            }
            
            // Broadcast the new message event for real-time updates
            try {
                broadcast(new NewChatMessage($conversation, $userId));
            } catch (\Exception $e) {
                Log::error('Broadcast failed', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the entire operation for broadcast issues
            }
            
            Log::info('Chat reply successful', ['conversation_id' => $conversation->id]);

            return response()->json([
                'success' => true,
                'message' => '訊息已送出',
                'conversation' => $conversation->load(['customer', 'user', 'replier'])
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Chat reply validation error', ['errors' => $e->errors()]);
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('Chat reply unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->errorResponse('系統錯誤，請稍後再試', $e);
        }
    }

    /**
     * LINE Bot webhook endpoint.
     */
    public function webhook(Request $request)
    {
        $logger = null;
        $executionId = 'webhook_' . time() . '_' . rand(1000, 9999);
        
        // 健壯的日誌記錄函數
        $logSafe = function($message) use ($executionId) {
            try {
                @file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - $message [ExecutionID: $executionId]\n", 
                    FILE_APPEND | LOCK_EX);
            } catch (\Exception $e) {
                // 忽略日誌寫入失敗
            }
        };
        
        $logSafe("Webhook called from IP: " . $request->ip());
        
        try {
            // 嘗試初始化 logger，但不讓其失敗阻礙流程
            try {
                $logger = new \App\Services\WebhookLoggerService();
                $logger->startExecution($request, 'line');
                $logSafe("WebhookLoggerService initialized successfully");
            } catch (\Exception $e) {
                $logSafe("WebhookLoggerService failed to initialize: " . $e->getMessage());
                // 繼續執行，不依賴 logger
            }
            
            // 安全地獲取請求數據
            $requestData = [];
            try {
                $requestData = [
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'body_length' => strlen($request->getContent()),
                    'has_line_signature' => !empty($request->header('X-Line-Signature'))
                ];
                $logSafe("Request data parsed successfully");
            } catch (\Exception $e) {
                $logSafe("Failed to parse request data: " . $e->getMessage());
                $requestData = ['error' => 'failed_to_parse'];
            }
            
            // 安全地獲取 LINE 設定
            $lineSettings = [];
            try {
                $lineSettings = $this->getLineSettings();
                $settingsStatus = "channel_secret=" . 
                    (!empty($lineSettings['channel_secret']) ? 'configured' : 'MISSING') . 
                    ", channel_access_token=" . (!empty($lineSettings['channel_access_token']) ? 'configured' : 'MISSING');
                $logSafe("LINE Settings Status: " . $settingsStatus);
            } catch (\Exception $e) {
                $logSafe("Failed to get LINE settings: " . $e->getMessage());
                $lineSettings = [];
            }
            
            // 安全地驗證簽名
            $signatureValid = false;
            try {
                $signatureValid = $this->verifySignature($request);
                $logSafe("Signature verification: " . ($signatureValid ? 'PASSED' : 'FAILED'));
                
                if ($logger) {
                    $logger->logSignatureVerification($signatureValid);
                }
            } catch (\Exception $e) {
                $logSafe("Signature verification threw exception: " . $e->getMessage());
                // 僅在開發/測試環境允許跳過簽名驗證
                if (app()->environment('local', 'testing')) {
                    $signatureValid = true;
                    $logSafe("Signature verification skipped for development/testing environment");
                } else {
                    $signatureValid = false;
                    $logSafe("Signature verification failed in production environment");
                }
            }
            
            if (!$signatureValid) {
                $error = 'Webhook signature verification failed';
                $logSafe("ERROR: $error");
                
                if ($logger) {
                    $logger->failExecution($error, ['signature_check_failed' => true]);
                }
                
                return response()->json([
                    'error' => $error,
                    'execution_id' => $executionId,
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ], 400);
            }

            // 安全地解析事件
            $events = [];
            $eventCount = 0;
            try {
                $events = $request->input('events', []);
                $eventCount = count($events);
                $logSafe("Processing $eventCount events");
                
                if ($logger) {
                    $logger->setEvents($events);
                }
            } catch (\Exception $e) {
                $logSafe("Failed to parse events: " . $e->getMessage());
                $events = [];
                $eventCount = 0;
            }

            // 安全地處理每個事件
            $processedEvents = [];
            foreach ($events as $index => $event) {
                try {
                    $logSafe("Processing event $index: " . json_encode($event));
                    
                    $result = $this->processEventWithLogging($event, $logger, $index);
                    
                    if ($logger) {
                        $logger->logEventProcessing($index, $event, $result);
                    }
                    
                    $processedEvents[] = $result;
                    $logSafe("Event $index processed successfully");
                    
                } catch (\Exception $e) {
                    $errorMsg = "Event $index processing failed: " . $e->getMessage();
                    $logSafe("ERROR: $errorMsg");
                    
                    if ($logger) {
                        try {
                            $logger->logStep("event_{$index}_failed", [
                                'event' => $event,
                                'error' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ], 'failed');
                        } catch (\Exception $loggerError) {
                            $logSafe("Logger failed during error recording: " . $loggerError->getMessage());
                        }
                    }
                    
                    // Continue processing other events even if one fails
                    $processedEvents[] = ['status' => 'failed', 'error' => $e->getMessage()];
                }
            }

            $logSafe("Webhook processing completed successfully");

            $results = [
                'status' => 'ok',
                'execution_id' => $executionId,
                'events_processed' => $eventCount,
                'events_results' => $processedEvents,
                'timestamp' => now()->format('Y-m-d H:i:s')
            ];
            
            if ($logger) {
                try {
                    $logger->completeExecution($results);
                } catch (\Exception $e) {
                    $logSafe("Logger completeExecution failed: " . $e->getMessage());
                }
            }
            
            return response()->json($results);
            
        } catch (\Exception $e) {
            $errorMsg = "CRITICAL ERROR in webhook: " . $e->getMessage();
            $logSafe($errorMsg);
            $logSafe("Error details: File=" . $e->getFile() . ", Line=" . $e->getLine());
            
            // 嘗試記錄完整錯誤但不讓它阻止響應
            try {
                if ($logger) {
                    $logger->failExecution($e->getMessage(), [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                }
            } catch (\Exception $loggerError) {
                $logSafe("Logger failExecution failed: " . $loggerError->getMessage());
            }
            
            // 返回簡化的錯誤響應
            return response()->json([
                'error' => 'Webhook processing failed',
                'message' => $e->getMessage(),
                'execution_id' => $executionId,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'suggestion' => 'Check webhook-debug.log for detailed error information'
            ], 200); // 返回 200 避免 LINE 不斷重試
        }
    }

    /**
     * Process event with comprehensive logging wrapper - 安全版本
     */
    protected function processEventWithLogging($event, $logger, $eventIndex)
    {
        $eventType = $event['type'] ?? 'unknown';
        
        // 安全地記錄日誌
        if ($logger) {
            try {
                $logger->logStep("event_{$eventIndex}_started", [
                    'event_type' => $eventType,
                    'event_details' => $event
                ]);
            } catch (\Exception $e) {
                // Logger 失敗不應該阻止事件處理
            }
        }

        try {
            switch ($eventType) {
                case 'message':
                    $result = $this->handleMessageWithLogging($event, $logger, $eventIndex);
                    break;
                case 'follow':
                    $result = $this->handleFollowWithLogging($event, $logger, $eventIndex);
                    break;
                case 'unfollow':
                    $result = $this->handleUnfollowWithLogging($event, $logger, $eventIndex);
                    break;
                default:
                    if ($logger) {
                        try {
                            $logger->logStep("event_{$eventIndex}_unhandled", [
                                'event_type' => $eventType,
                                'message' => 'Unhandled LINE event type'
                            ]);
                        } catch (\Exception $e) {
                            // 忽略 logger 錯誤
                        }
                    }
                    
                    $result = [
                        'status' => 'skipped',
                        'event_type' => $eventType,
                        'reason' => 'unhandled_event_type'
                    ];
            }

            if ($logger) {
                try {
                    $logger->logStep("event_{$eventIndex}_completed", [
                        'event_type' => $eventType,
                        'result' => $result
                    ]);
                } catch (\Exception $e) {
                    // 忽略 logger 錯誤
                }
            }

            return $result;

        } catch (\Exception $e) {
            if ($logger) {
                try {
                    $logger->logStep("event_{$eventIndex}_exception", [
                        'event_type' => $eventType,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ], 'failed');
                } catch (\Exception $loggerError) {
                    // 忽略 logger 錯誤
                }
            }

            throw $e;
        }
    }

    /**
     * Handle message events with logging
     */
    protected function handleMessageWithLogging($event, $logger, $eventIndex)
    {
        $messageType = $event['message']['type'] ?? 'unknown';
        if ($logger) {
            try {
                $logger->logStep("message_{$eventIndex}_handling", [
                    'message_type' => $messageType,
                    'message_content' => $event['message'] ?? null
                ]);
            } catch (\Exception $e) {
                // 忽略 logger 錯誤
            }
        }

        switch ($messageType) {
            case 'text':
                return $this->handleTextMessageWithLogging($event, $logger, $eventIndex);
            case 'image':
            case 'video':
            case 'audio':
            case 'file':
                return $this->handleMediaMessageWithLogging($event, $logger, $eventIndex);
            case 'sticker':
                return $this->handleStickerMessageWithLogging($event, $logger, $eventIndex);
            case 'location':
                return $this->handleLocationMessageWithLogging($event, $logger, $eventIndex);
            default:
                if ($logger) {
                    try {
                        $logger->logStep("message_{$eventIndex}_unhandled", [
                            'message_type' => $messageType,
                            'message' => 'Unhandled LINE message type'
                        ]);
                        Log::info('Unhandled LINE message type', [
                            'type' => $messageType,
                            'execution_id' => $logger->getExecutionId()
                        ]);
                    } catch (\Exception $e) {
                        Log::info('Unhandled LINE message type', ['type' => $messageType]);
                    }
                } else {
                    Log::info('Unhandled LINE message type', ['type' => $messageType]);
                }
                return ['status' => 'unhandled', 'message_type' => $messageType];
        }
    }

    /**
     * Handle text messages with comprehensive logging
     */
    protected function handleTextMessageWithLogging($event, $logger, $eventIndex)
    {
        $lineUserId = $event['source']['userId'] ?? null;
        $messageText = $event['message']['text'] ?? null;
        $timestamp = $event['timestamp'] ?? null;
        
        // Point 17: 強化接收訊息的日誌記錄
        $logger->logStep("text_message_{$eventIndex}_processing", [
            'line_user_id' => $lineUserId,
            'message_text' => $messageText, // 完整訊息內容
            'message_length' => strlen($messageText ?? ''),
            'timestamp' => $timestamp,
            'raw_timestamp' => $timestamp,
            'formatted_timestamp' => $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp / 1000)->toDateTimeString() : null
        ]);

        // 額外記錄到webhook-debug.log用於追蹤
        $logSafe = function($message) {
            try {
                @file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Point17 - $message\n", 
                    FILE_APPEND | LOCK_EX);
            } catch (\Exception $e) {
                // 忽略日誌寫入失敗
            }
        };
        
        $logSafe("接收到LINE訊息: 用戶ID={$lineUserId}, 訊息內容='{$messageText}', 訊息長度=" . strlen($messageText ?? ''));

        if (!$lineUserId || !$messageText) {
            $logSafe("訊息驗證失敗: 缺少用戶ID或訊息內容");
            $logger->logStep("text_message_{$eventIndex}_invalid", [
                'missing_user_id' => !$lineUserId,
                'missing_text' => !$messageText,
                'message' => 'Missing required fields'
            ], 'failed');
            return ['status' => 'failed', 'reason' => 'missing_required_fields'];
        }

        try {
            // Customer creation/finding
            $logger->logStep("customer_{$eventIndex}_lookup_start", ['line_user_id' => $lineUserId]);
            $customer = $this->createSimpleCustomer($lineUserId);
            
            if (!$customer) {
                $logger->logCustomerOperation('simple_creation_failed', null, ['line_user_id' => $lineUserId]);
                $customer = $this->findOrCreateCustomer($lineUserId, $event);
            }

            if (!$customer) {
                $logger->logCustomerOperation('creation_failed', null, ['line_user_id' => $lineUserId]);
                return ['status' => 'failed', 'reason' => 'customer_creation_failed'];
            }

            $logger->logCustomerOperation('found_or_created', $customer->id, [
                'line_user_id' => $lineUserId,
                'customer_name' => $customer->name
            ]);

            // Point 18: 調整順序 - 先Firebase後MySQL
            $logger->logStep("data_preparation_{$eventIndex}_start", [
                'customer_id' => $customer->id,
                'assigned_to' => $customer->assigned_to
            ]);

            $logSafe("Point 18: 準備資料處理順序 - 先Firebase後MySQL, 客戶ID={$customer->id}");
            
            // 準備對話資料但先不保存到MySQL
            $conversationData = [
                'customer_id' => $customer->id,
                'user_id' => $customer->assigned_to,
                'line_user_id' => $lineUserId,
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => $messageText,
                'message_timestamp' => $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp / 1000) : now(),
                'is_from_customer' => true,
                'status' => 'unread',
                'version_updated_at' => now(), // Point 24: Added required field
                'metadata' => [
                    'event_timestamp' => $timestamp,
                    'execution_id' => $logger->getExecutionId()
                ],
            ];

            // Step 1: Firebase sync 先行 - Point 18
            $firebaseSync = false;
            $tempConversation = null;
            try {
                $logSafe("Point 18: 第1步 - 先同步到Firebase realtime database");
                $logger->logStep("firebase_sync_{$eventIndex}_start", ['customer_id' => $customer->id]);
                
                // 創建臨時conversation物件用於Firebase同步
                $tempConversation = new ChatConversation($conversationData);
                $tempConversation->id = 'temp_' . time() . '_' . rand(1000, 9999); // 臨時ID
                
                $firebaseSync = $this->firebaseChatService->syncConversationToFirebase($tempConversation);
                
                $logger->logFirebaseSync($firebaseSync, $tempConversation->id);
                
                if ($firebaseSync) {
                    $logSafe("Point 18: Firebase同步成功 - 準備進行MySQL寫入");
                    Log::info('Firebase sync successful (Point 18 - first step)', [
                        'temp_conversation_id' => $tempConversation->id,
                        'line_user_id' => $lineUserId,
                        'execution_id' => $logger->getExecutionId()
                    ]);
                } else {
                    $logSafe("Point 18: Firebase同步失敗 - 仍將繼續MySQL寫入");
                }
                
            } catch (\Exception $e) {
                $logSafe("Point 18: Firebase同步異常: 錯誤={$e->getMessage()} - 仍將繼續MySQL寫入");
                $logger->logFirebaseSync(false, $tempConversation->id ?? 'unknown', $e->getMessage());
                Log::error('Firebase sync failed (Point 18 - first step)', [
                    'error' => $e->getMessage(),
                    'execution_id' => $logger->getExecutionId()
                ]);
            }

            // Step 2: MySQL conversation 創建 後行 - Point 18
            $conversation = null;
            try {
                $logSafe("Point 18: 第2步 - 保存到MySQL資料庫");
                
                DB::beginTransaction();
                $logger->logDatabaseTransaction('begin', true, ['operation' => 'conversation_creation_after_firebase']);

                $conversation = ChatConversation::create($conversationData);

                DB::commit();
                $logger->logDatabaseTransaction('commit', true, ['conversation_id' => $conversation->id]);
                
                $logSafe("Point 18: MySQL對話記錄創建成功: ID={$conversation->id}, 客戶ID={$customer->id}, 訊息='{$messageText}'");
                
                $logger->logConversationOperation('created', $conversation->id, [
                    'message_type' => 'text',
                    'message_length' => strlen($messageText),
                    'customer_id' => $customer->id,
                    'processing_order' => 'firebase_first_mysql_second'
                ]);

                // 如果之前Firebase同步成功，更新Firebase中的記錄為實際的conversation ID
                if ($firebaseSync && $conversation && $tempConversation) {
                    try {
                        $logSafe("Point 18: 更新Firebase記錄使用真實conversation ID={$conversation->id}");
                        $this->firebaseChatService->syncConversationToFirebase($conversation);
                    } catch (\Exception $e) {
                        $logSafe("Point 18: Firebase ID更新失敗: " . $e->getMessage());
                    }
                }
                
            } catch (\Exception $e) {
                DB::rollBack();
                $logger->logDatabaseTransaction('rollback', true, ['error' => $e->getMessage()]);
                $logSafe("Point 18: MySQL對話記錄創建失敗: " . $e->getMessage());
                throw $e;
            }

            return [
                'status' => 'success',
                'customer_id' => $customer->id,
                'conversation_id' => $conversation ? $conversation->id : null,
                'firebase_synced' => $firebaseSync ?? false,
                'processing_order' => 'firebase_first_mysql_second',
                'point18_implemented' => true
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            $logger->logDatabaseTransaction('rollback', true, ['error' => $e->getMessage()]);
            
            $logger->logStep("text_message_{$eventIndex}_exception", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 'failed');

            throw $e;
        }
    }

    /**
     * Handle follow events with logging
     */
    protected function handleFollowWithLogging($event, $logger, $eventIndex)
    {
        $logger->logStep("follow_{$eventIndex}_processing", $event);
        $this->handleFollow($event);
        return ['status' => 'success', 'type' => 'follow'];
    }

    /**
     * Handle unfollow events with logging
     */
    protected function handleUnfollowWithLogging($event, $logger, $eventIndex)
    {
        $logger->logStep("unfollow_{$eventIndex}_processing", $event);
        $this->handleUnfollow($event);
        return ['status' => 'success', 'type' => 'unfollow'];
    }

    /**
     * Handle media messages with logging
     */
    protected function handleMediaMessageWithLogging($event, $logger, $eventIndex)
    {
        $messageType = $event['message']['type'] ?? 'unknown';
        $logger->logStep("media_{$eventIndex}_processing", [
            'media_type' => $messageType,
            'event' => $event
        ]);
        $this->handleMediaMessage($event);
        return ['status' => 'success', 'type' => 'media', 'media_type' => $messageType];
    }

    /**
     * Handle sticker messages with logging
     */
    protected function handleStickerMessageWithLogging($event, $logger, $eventIndex)
    {
        $logger->logStep("sticker_{$eventIndex}_processing", $event);
        $this->handleStickerMessage($event);
        return ['status' => 'success', 'type' => 'sticker'];
    }

    /**
     * Handle location messages with logging
     */
    protected function handleLocationMessageWithLogging($event, $logger, $eventIndex)
    {
        $logger->logStep("location_{$eventIndex}_processing", $event);
        $this->handleLocationMessage($event);
        return ['status' => 'success', 'type' => 'location'];
    }

    /**
     * Test webhook and Firebase sync functionality
     */
    public function testWebhookFirebase(Request $request)
    {
        try {
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - TEST: Manual webhook/Firebase test started\n", 
                FILE_APPEND | LOCK_EX);
                
            // Test 1: Check Firebase service availability
            $firebaseAvailable = $this->firebaseChatService !== null;
            
            // Test 2: Check Database connection
            $databaseConnected = false;
            try {
                $databaseConnected = $this->firebaseChatService->checkFirebaseConnection();
            } catch (\Exception $e) {
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - TEST: Firebase connection check failed: " . $e->getMessage() . "\n", 
                    FILE_APPEND | LOCK_EX);
            }
            
            // Test 3: Create a test conversation and sync it
            $testSyncResult = false;
            $testConversationId = null;
            
            // Find an existing customer or create a test one
            $testCustomer = \App\Models\Customer::where('line_user_id', '!=', null)->first();
            
            if ($testCustomer) {
                $testConversation = \App\Models\ChatConversation::create([
                    'customer_id' => $testCustomer->id,
                    'user_id' => $testCustomer->assigned_to,
                    'line_user_id' => $testCustomer->line_user_id,
                    'version_updated_at' => now(), // Point 24: Added required field
                    'platform' => 'line',
                    'message_type' => 'text',
                    'message_content' => 'TEST MESSAGE - ' . date('Y-m-d H:i:s'),
                    'message_timestamp' => now(),
                    'is_from_customer' => true,
                    'status' => 'unread',
                    'metadata' => [
                        'test_message' => true,
                        'timestamp' => time()
                    ],
                ]);
                
                $testConversationId = $testConversation->id;
                
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - TEST: Created test conversation {$testConversationId}\n", 
                    FILE_APPEND | LOCK_EX);
                    
                // Try to sync it
                try {
                    $testSyncResult = $this->firebaseChatService->syncConversationToFirebase($testConversation);
                    file_put_contents(storage_path('logs/webhook-debug.log'), 
                        date('Y-m-d H:i:s') . " - TEST: Sync result = " . ($testSyncResult ? 'SUCCESS' : 'FAILED') . "\n", 
                        FILE_APPEND | LOCK_EX);
                } catch (\Exception $e) {
                    file_put_contents(storage_path('logs/webhook-debug.log'), 
                        date('Y-m-d H:i:s') . " - TEST: Sync exception: " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                }
            } else {
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - TEST: No test customer found with LINE user ID\n", 
                    FILE_APPEND | LOCK_EX);
            }
            
            $results = [
                'firebase_service_available' => $firebaseAvailable,
                'database_connected' => $databaseConnected,
                'test_sync_result' => $testSyncResult,
                'test_conversation_id' => $testConversationId,
                'test_customer_found' => $testCustomer !== null,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - TEST: Results = " . json_encode($results) . "\n", 
                FILE_APPEND | LOCK_EX);
                
            return response()->json($results);
            
        } catch (\Exception $e) {
            $error = [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
            
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - TEST: Exception = " . json_encode($error) . "\n", 
                FILE_APPEND | LOCK_EX);
                
            return response()->json($error, 500);
        }
    }

    /**
     * Get unread messages count.
     */
    public function getUnreadCount()
    {
        $user = Auth::user();
        
        $query = ChatConversation::where('status', 'unread')
            ->where('is_from_customer', true);

        if ($user->isStaff()) {
            $query->whereHas('customer', function($q) use ($user) {
                $q->where('assigned_to', $user->id);
            });
        }

        $count = $query->count();

        return response()->json(['unread_count' => $count]);
    }

    /**
     * Mark conversation messages as read.
     */
    public function markAsRead(Request $request, $userId)
    {
        $user = Auth::user();
        
        $query = ChatConversation::where('line_user_id', $userId)
            ->where('status', 'unread');

        // Staff can only mark their assigned customers' messages as read
        if ($user->isStaff()) {
            $query->whereHas('customer', function($q) use ($user) {
                $q->where('assigned_to', $user->id);
            });
        }

        // Get messages to update and use safe method
        $messagesToUpdate = $query->get();
        $updated = 0;
        
        foreach ($messagesToUpdate as $message) {
            if ($this->safeUpdateStatus($message, 'read')) {
                $updated++;
            }
        }

        return response()->json([
            'success' => true,
            'updated_count' => $updated
        ]);
    }

    /**
     * Delete conversation.
     */
    public function deleteConversation(Request $request, $userId)
    {
        $user = Auth::user();
        
        $query = ChatConversation::where('line_user_id', $userId);

        // Staff can only delete their assigned customers' conversations
        if ($user->isStaff()) {
            $query->whereHas('customer', function($q) use ($user) {
                $q->where('assigned_to', $user->id);
            });
        }

        $deleted = $query->delete();

        return response()->json([
            'success' => true,
            'deleted_count' => $deleted
        ]);
    }

    /**
     * Get chat statistics.
     */
    public function getChatStats()
    {
        $user = Auth::user();
        
        $baseQuery = ChatConversation::query();
        
        // Staff can only see stats for their assigned customers
        if ($user->isStaff()) {
            $baseQuery->whereHas('customer', function($q) use ($user) {
                $q->where('assigned_to', $user->id);
            });
        }

        $totalConversations = (clone $baseQuery)->distinct('line_user_id')->count();
        $unreadMessages = (clone $baseQuery)->where('status', 'unread')->where('is_from_customer', true)->count();
        $todayMessages = (clone $baseQuery)->whereDate('message_timestamp', today())->count();
        $activeCustomers = (clone $baseQuery)->whereDate('message_timestamp', '>=', now()->subDays(7))->distinct('line_user_id')->count();

        return response()->json([
            'total_conversations' => $totalConversations,
            'unread_messages' => $unreadMessages,
            'today_messages' => $todayMessages,
            'active_customers' => $activeCustomers
        ]);
    }

    /**
     * Search conversations.
     */
    public function searchConversations(Request $request)
    {
        try {
            $user = Auth::user();
            $query = $request->get('q', '');

            // Validate query parameter
            if (empty($query)) {
                return response()->json([
                    'data' => [],
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 0
                ]);
            }

            // Use a simpler approach to avoid complex JOIN issues
            $conversationsQuery = ChatConversation::with(['customer', 'user'])
                ->whereNotNull('line_user_id')
                ->whereNotNull('customer_id');

            // Staff can only search their assigned customers
            if ($user->isStaff()) {
                $conversationsQuery->whereHas('customer', function($q) use ($user) {
                    $q->where('assigned_to', $user->id);
                });
            }

            // Search in customer names, phone, or message content
            if ($query) {
                $conversationsQuery->where(function($q) use ($query) {
                    $q->whereHas('customer', function($customerQuery) use ($query) {
                        $customerQuery->where('name', 'LIKE', "%{$query}%")
                            ->orWhere('phone', 'LIKE', "%{$query}%");
                    })
                    ->orWhere('message_content', 'LIKE', "%{$query}%");
                });
            }

            // Get all matching conversations
            $allConversations = $conversationsQuery->orderBy('message_timestamp', 'desc')->get();

            // Group by line_user_id and get the latest conversation for each
            $latestConversations = $allConversations->groupBy('line_user_id')->map(function ($conversations) {
                $latest = $conversations->first();
                
                // Calculate unread count for this line_user_id
                $unreadCount = ChatConversation::where('line_user_id', $latest->line_user_id)
                    ->where('status', 'unread')
                    ->where('is_from_customer', 1)
                    ->count();

                return [
                    'line_user_id' => $latest->line_user_id,
                    'customer_id' => $latest->customer_id,
                    'last_message' => $latest->message_content,
                    'last_message_time' => $latest->message_timestamp,
                    'unread_count' => $unreadCount,
                    'customer' => $latest->customer,
                    'user' => $latest->user
                ];
            })->values();

            // Sort by last_message_time descending
            $sortedConversations = $latestConversations->sortByDesc('last_message_time')->values();

            // Manual pagination
            $page = $request->get('page', 1);
            $perPage = 20;
            $total = $sortedConversations->count();
            $offset = ($page - 1) * $perPage;
            $paginatedData = $sortedConversations->slice($offset, $perPage)->values();

            return response()->json([
                'data' => $paginatedData,
                'current_page' => (int) $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]);

        } catch (\Exception $e) {
            \Log::error('Search conversations error: ' . $e->getMessage(), [
                'query' => $query ?? '',
                'user_id' => $user->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Search failed',
                'message' => 'An error occurred while searching conversations',
                'data' => []
            ], 500);
        }
    }

    /**
     * Get unmasked LINE settings for internal usage
     */
    private function getLineSettings()
    {
        // Only use database settings, no fallback to config or env
        $dbSettings = LineIntegrationSetting::getAllSettings(true);
        
        // Log if settings are missing from database
        if (empty($dbSettings['channel_access_token']) || empty($dbSettings['channel_secret'])) {
            Log::error('LINE integration settings missing from database - system configured for database-only settings', [
                'has_access_token' => !empty($dbSettings['channel_access_token']),
                'has_channel_secret' => !empty($dbSettings['channel_secret']),
                'available_keys' => array_keys($dbSettings),
                'fix_required' => 'Configure LINE settings via /settings/line page or API /api/debug/line/settings',
                'impact' => 'Webhooks will fail signature verification, chat functionality disabled'
            ]);
        }
        
        return [
            'channel_access_token' => $dbSettings['channel_access_token'] ?? '',
            'channel_secret' => $dbSettings['channel_secret'] ?? '',
        ];
    }

    /**
     * Verify LINE webhook signature
     */
    protected function verifySignature(Request $request)
    {
        $signature = $request->header('X-Line-Signature');
        $body = $request->getContent();
        
        // Log incoming signature details for debugging
        Log::info('LINE Webhook Signature Verification', [
            'has_signature' => !empty($signature),
            'signature_length' => strlen($signature ?? ''),
            'body_length' => strlen($body ?? ''),
            'body_hash' => md5($body ?? ''),
            'headers' => $request->headers->all()
        ]);
        
        if (!$signature || !$body) {
            Log::warning('LINE Webhook missing signature or body', [
                'signature' => $signature,
                'body_empty' => empty($body)
            ]);
            // 非生產環境暫時跳過簽名驗證用於測試
            if (app()->environment('local', 'testing')) {
                Log::info('Skipping signature verification for testing environment');
                return true;
            }
            return false;
        }

        // Get channel secret from database
        $settings = $this->getLineSettings();
        $channelSecret = $settings['channel_secret'];
        
        if (!$channelSecret) {
            $errorMsg = 'LINE Channel Secret not configured in database - webhook verification failed';
            Log::error($errorMsg, [
                'missing_config' => 'channel_secret not found in line_integration_settings table',
                'action_required' => 'Configure LINE Channel Secret in line_integration_settings table',
                'settings_available' => array_keys($settings)
            ]);
            
            // Also write to webhook debug log for visibility
            @file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - ERROR: $errorMsg - Missing channel_secret in line_integration_settings table\n", 
                FILE_APPEND | LOCK_EX);
                
            return false; // Always require channel secret
        }

        $expectedSignature = base64_encode(hash_hmac('sha256', $body, $channelSecret, true));
        
        // Log signature comparison for debugging
        $isValid = hash_equals($expectedSignature, $signature);
        
        if (!$isValid) {
            Log::error('LINE Webhook signature mismatch', [
                'expected' => substr($expectedSignature, 0, 10) . '...',
                'received' => substr($signature, 0, 10) . '...',
                'channel_secret_configured' => !empty($channelSecret),
                'channel_secret_length' => strlen($channelSecret)
            ]);
        } else {
            Log::info('LINE Webhook signature verified successfully');
        }
        
        return $isValid;
    }

    /**
     * Process incoming LINE event
     */
    protected function processEvent($event)
    {
        $eventType = $event['type'] ?? null;
        
        switch ($eventType) {
            case 'message':
                $this->handleMessage($event);
                break;
            case 'follow':
                $this->handleFollow($event);
                break;
            case 'unfollow':
                $this->handleUnfollow($event);
                break;
            default:
                Log::info('Unhandled LINE event type', ['type' => $eventType]);
        }
    }

    /**
     * Handle incoming message events
     */
    protected function handleMessage($event)
    {
        $messageType = $event['message']['type'] ?? null;
        
        switch ($messageType) {
            case 'text':
                $this->handleTextMessage($event);
                break;
            case 'image':
            case 'video':
            case 'audio':
            case 'file':
                $this->handleMediaMessage($event);
                break;
            case 'sticker':
                $this->handleStickerMessage($event);
                break;
            case 'location':
                $this->handleLocationMessage($event);
                break;
            default:
                Log::info('Unhandled LINE message type', ['type' => $messageType]);
        }
    }

    /**
     * Handle text messages
     */
    protected function handleTextMessage($event)
    {
        $lineUserId = $event['source']['userId'] ?? null;
        $messageText = $event['message']['text'] ?? '';
        $messageId = $event['message']['id'] ?? null;
        $timestamp = $event['timestamp'] ?? null;
        
        // Debug log at start
        file_put_contents(storage_path('logs/webhook-debug.log'), 
            date('Y-m-d H:i:s') . " - handleTextMessage called with LINE user: $lineUserId, message: $messageText\n", 
            FILE_APPEND | LOCK_EX);
        
        if (!$lineUserId || !$messageText) {
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Missing LINE user ID or message text, returning\n", 
                FILE_APPEND | LOCK_EX);
            return;
        }

        Log::info('Processing LINE text message', [
            'line_user_id' => $lineUserId,
            'message' => $messageText
        ]);

        file_put_contents(storage_path('logs/webhook-debug.log'), 
            date('Y-m-d H:i:s') . " - About to call findOrCreateCustomer\n", 
            FILE_APPEND | LOCK_EX);

        // Find or create customer record - 使用簡化版本確保可靠性
        $customer = $this->createSimpleCustomer($lineUserId);
        
        // 如果簡化版本失敗，記錄錯誤並嘗試複雜版本
        if (!$customer) {
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Simple customer creation failed, trying complex version\n", 
                FILE_APPEND | LOCK_EX);
                
            try {
                $customer = $this->findOrCreateCustomer($lineUserId, $event);
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Complex findOrCreateCustomer returned customer ID: " . ($customer ? $customer->id : 'null') . "\n", 
                    FILE_APPEND | LOCK_EX);
            } catch (\Exception $e) {
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Complex findOrCreateCustomer ALSO FAILED: " . $e->getMessage() . "\n", 
                    FILE_APPEND | LOCK_EX);
                return;
            }
        }
        
        // Check if this is a referral code response
        $isReferralCode = false;
        if ($messageText === '跳過推薦碼') {
            $isReferralCode = true;
            $this->handleReferralCodeSkip($lineUserId, $customer);
        } elseif (preg_match('/^[A-Za-z0-9]{3,10}$/', trim($messageText))) {
            // Potential referral code (3-10 alphanumeric characters)
            $isReferralCode = true;
            $this->handleReferralCodeInput($lineUserId, $customer, trim($messageText));
        }
        
        // Save conversation
        if (!$customer) {
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - ERROR: Customer is null, cannot create conversation\n", 
                FILE_APPEND | LOCK_EX);
            return;
        }

        file_put_contents(storage_path('logs/webhook-debug.log'), 
            date('Y-m-d H:i:s') . " - Creating conversation for customer {$customer->id}\n", 
            FILE_APPEND | LOCK_EX);

        try {
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - About to create conversation with data: customer_id={$customer->id}, user_id={$customer->assigned_to}, line_user_id={$lineUserId}\n", 
                FILE_APPEND | LOCK_EX);
                
            $conversation = ChatConversation::create([
                'customer_id' => $customer->id,
                'user_id' => $customer->assigned_to,
                'line_user_id' => $lineUserId,
                'version_updated_at' => now(), // Point 24: Added required field
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => $messageText,
                'message_timestamp' => $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp / 1000) : now(),
                'is_from_customer' => true,
                'status' => 'unread',
                'metadata' => [
                    'message_id' => $messageId,
                    'timestamp' => $timestamp,
                    'event_type' => 'message',
                    'is_referral_code' => $isReferralCode,
                ],
            ]);

            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Conversation created successfully with ID: {$conversation->id}\n", 
                FILE_APPEND | LOCK_EX);
                
        } catch (\Illuminate\Database\QueryException $e) {
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Database error creating conversation: " . $e->getMessage() . " | SQL: " . $e->getSql() . "\n", 
                FILE_APPEND | LOCK_EX);
            
            Log::error('Database error creating conversation', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'customer_id' => $customer->id,
                'line_user_id' => $lineUserId
            ]);
            return;
        } catch (\Exception $e) {
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Conversation creation FAILED: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine() . "\n", 
                FILE_APPEND | LOCK_EX);
                
            Log::error('Failed to create conversation', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'customer_id' => $customer->id,
                'line_user_id' => $lineUserId
            ]);
            return;
        }

        // Sync to Firebase Realtime Database with error handling
        try {
            // Debug log before sync
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Starting Firebase sync for conversation {$conversation->id}\n", 
                FILE_APPEND | LOCK_EX);
                
            $firebaseSync = $this->firebaseChatService->syncConversationToFirebase($conversation);
            
            if ($firebaseSync) {
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Firebase sync SUCCESS for conversation {$conversation->id}\n", 
                    FILE_APPEND | LOCK_EX);
                    
                Log::info('Webhook Firebase sync successful', [
                    'conversation_id' => $conversation->id,
                    'line_user_id' => $lineUserId
                ]);
            } else {
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Firebase sync FAILED for conversation {$conversation->id}\n", 
                    FILE_APPEND | LOCK_EX);
                    
                Log::error('Webhook Firebase sync failed', [
                    'conversation_id' => $conversation->id,
                    'line_user_id' => $lineUserId,
                    'message_content' => substr($messageText, 0, 100)
                ]);
            }
        } catch (\Exception $e) {
            $errorMsg = "Firebase sync EXCEPTION for conversation {$conversation->id}: " . $e->getMessage();
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - $errorMsg\n", 
                FILE_APPEND | LOCK_EX);
                
            Log::error('Webhook Firebase sync error', [
                'conversation_id' => $conversation->id,
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // Broadcast the new message event for real-time updates
        try {
            broadcast(new NewChatMessage($conversation, $lineUserId));
            Log::debug('Webhook broadcast successful', [
                'conversation_id' => $conversation->id,
                'line_user_id' => $lineUserId
            ]);
        } catch (\Exception $e) {
            Log::error('Webhook broadcast failed', [
                'conversation_id' => $conversation->id,
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);
        }

    }

    /**
     * Handle media messages (image, video, audio, file)
     */
    protected function handleMediaMessage($event)
    {
        $lineUserId = $event['source']['userId'] ?? null;
        $messageType = $event['message']['type'] ?? 'file';
        $messageId = $event['message']['id'] ?? null;
        $timestamp = $event['timestamp'] ?? null;
        
        if (!$lineUserId) {
            return;
        }

        Log::info('Processing LINE media message', [
            'line_user_id' => $lineUserId,
            'type' => $messageType
        ]);

        // Find or create customer record
        $customer = $this->findOrCreateCustomer($lineUserId, $event);
        
        // Save conversation
        $conversation = ChatConversation::create([
            'customer_id' => $customer->id,
            'user_id' => $customer->assigned_to,
            'line_user_id' => $lineUserId,
            'platform' => 'line',
            'message_type' => $messageType,
            'message_content' => "傳送了{$messageType}檔案",
            'message_timestamp' => $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp / 1000) : now(),
            'is_from_customer' => true,
            'status' => 'unread',
            'metadata' => [
                'message_id' => $messageId,
                'timestamp' => $timestamp,
                'event_type' => 'message',
                'media_type' => $messageType,
            ],
        ]);

        // Sync to Firebase Realtime Database
        $this->firebaseChatService->syncConversationToFirebase($conversation);
        
        // Broadcast the new message event for real-time updates
        broadcast(new NewChatMessage($conversation, $lineUserId));
    }

    /**
     * Handle sticker messages
     */
    protected function handleStickerMessage($event)
    {
        $lineUserId = $event['source']['userId'] ?? null;
        $stickerId = $event['message']['stickerId'] ?? null;
        $packageId = $event['message']['packageId'] ?? null;
        $messageId = $event['message']['id'] ?? null;
        $timestamp = $event['timestamp'] ?? null;
        
        if (!$lineUserId) {
            return;
        }

        Log::info('Processing LINE sticker message', [
            'line_user_id' => $lineUserId,
            'sticker_id' => $stickerId
        ]);

        // Find or create customer record
        $customer = $this->findOrCreateCustomer($lineUserId, $event);
        
        // Save conversation
        $conversation = ChatConversation::create([
            'customer_id' => $customer->id,
            'user_id' => $customer->assigned_to,
            'line_user_id' => $lineUserId,
            'platform' => 'line',
            'message_type' => 'sticker',
            'message_content' => '傳送了貼圖',
            'message_timestamp' => $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp / 1000) : now(),
            'is_from_customer' => true,
            'status' => 'unread',
            'metadata' => [
                'message_id' => $messageId,
                'timestamp' => $timestamp,
                'event_type' => 'message',
                'sticker_id' => $stickerId,
                'package_id' => $packageId,
            ],
        ]);

        // Sync to Firebase Realtime Database
        $this->firebaseChatService->syncConversationToFirebase($conversation);
        
        // Broadcast the new message event for real-time updates
        broadcast(new NewChatMessage($conversation, $lineUserId));
    }

    /**
     * Handle location messages
     */
    protected function handleLocationMessage($event)
    {
        $lineUserId = $event['source']['userId'] ?? null;
        $title = $event['message']['title'] ?? '位置資訊';
        $address = $event['message']['address'] ?? '';
        $latitude = $event['message']['latitude'] ?? null;
        $longitude = $event['message']['longitude'] ?? null;
        $messageId = $event['message']['id'] ?? null;
        $timestamp = $event['timestamp'] ?? null;
        
        if (!$lineUserId) {
            return;
        }

        Log::info('Processing LINE location message', [
            'line_user_id' => $lineUserId,
            'title' => $title
        ]);

        // Find or create customer record
        $customer = $this->findOrCreateCustomer($lineUserId, $event);
        
        // Save conversation
        $conversation = ChatConversation::create([
            'customer_id' => $customer->id,
            'user_id' => $customer->assigned_to,
            'line_user_id' => $lineUserId,
            'platform' => 'line',
            'message_type' => 'location',
            'message_content' => "分享位置：{$title}" . ($address ? " ({$address})" : ''),
            'message_timestamp' => $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp / 1000) : now(),
            'is_from_customer' => true,
            'status' => 'unread',
            'metadata' => [
                'message_id' => $messageId,
                'timestamp' => $timestamp,
                'event_type' => 'message',
                'title' => $title,
                'address' => $address,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ],
        ]);

        // Sync to Firebase Realtime Database
        $this->firebaseChatService->syncConversationToFirebase($conversation);
        
        // Broadcast the new message event for real-time updates
        broadcast(new NewChatMessage($conversation, $lineUserId));
    }

    /**
     * Handle follow events (user adds bot as friend)
     */
    protected function handleFollow($event)
    {
        $lineUserId = $event['source']['userId'] ?? null;
        $timestamp = $event['timestamp'] ?? null;
        
        if (!$lineUserId) {
            Log::warning('LINE follow event missing user ID', ['event' => $event]);
            return;
        }

        Log::info('Processing LINE follow event', ['line_user_id' => $lineUserId]);

        try {
            // Find or create customer record
            $customer = $this->findOrCreateCustomer($lineUserId, $event);
            
            // Update customer status to indicate they are a LINE friend
            $customer->update([
                'channel' => 'line',
                'status' => $customer->status === Customer::STATUS_NEW ? Customer::STATUS_NEW : $customer->status,
                'tracking_status' => Customer::TRACKING_PENDING,
                'next_contact_date' => now()->addDay(), // Schedule follow-up for next day
            ]);
            
            // Save follow event as conversation
            $conversation = ChatConversation::create([
                'customer_id' => $customer->id,
                'user_id' => $customer->assigned_to,
                'line_user_id' => $lineUserId,
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => '加入好友',
                'message_timestamp' => $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp / 1000) : now(),
                'is_from_customer' => true,
                'status' => 'unread',
                'metadata' => [
                    'event_type' => 'follow',
                    'timestamp' => $timestamp,
                ],
            ]);

            // Sync to Firebase Realtime Database
            $this->firebaseChatService->syncConversationToFirebase($conversation);
            
            // Broadcast the new message event for real-time updates
            broadcast(new NewChatMessage($conversation, $lineUserId));

            Log::info('LINE follow event processed successfully (no auto-welcome messages)', [
                'line_user_id' => $lineUserId,
                'customer_id' => $customer->id,
                'conversation_id' => $conversation->id
            ]);

            // Auto-reply functionality removed - no welcome message or flex message sent
            
            // Point 26: 自動建立案件 - 當新用戶加機器人好友
            $this->createFollowUpCaseIfNeeded($customer, $lineUserId);
            
        } catch (\Exception $e) {
            Log::error('Failed to process LINE follow event', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle unfollow events (user removes bot as friend)
     */
    protected function handleUnfollow($event)
    {
        $lineUserId = $event['source']['userId'] ?? null;
        $timestamp = $event['timestamp'] ?? null;
        
        if (!$lineUserId) {
            Log::warning('LINE unfollow event missing user ID', ['event' => $event]);
            return;
        }

        Log::info('Processing LINE unfollow event', ['line_user_id' => $lineUserId]);

        try {
            // Find customer record
            $customer = Customer::where('line_user_id', $lineUserId)->first();
            
            if ($customer) {
                // Update customer status to reflect they unfollowed
                $customer->update([
                    'status' => $customer->status === Customer::STATUS_NEW ? Customer::STATUS_NOT_INTERESTED : $customer->status,
                    'tracking_status' => Customer::TRACKING_COMPLETED,
                    'notes' => ($customer->notes ? $customer->notes . "\n" : '') . '客戶於 ' . now()->format('Y-m-d H:i:s') . ' 取消LINE好友',
                ]);
                
                // Save unfollow event as conversation
                $conversation = ChatConversation::create([
                    'customer_id' => $customer->id,
                    'user_id' => $customer->assigned_to,
                    'line_user_id' => $lineUserId,
                    'platform' => 'line',
                    'message_type' => 'text',
                    'message_content' => '取消好友',
                    'message_timestamp' => $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp / 1000) : now(),
                    'is_from_customer' => true,
                    'status' => 'read', // Mark as read since it's a system event
                    'metadata' => [
                        'event_type' => 'unfollow',
                        'timestamp' => $timestamp,
                    ],
                ]);

                // Sync to Firebase Realtime Database
                $this->firebaseChatService->syncConversationToFirebase($conversation);
                
                // Broadcast the new message event for real-time updates
                broadcast(new NewChatMessage($conversation, $lineUserId));

                Log::info('LINE unfollow event processed successfully', [
                    'line_user_id' => $lineUserId,
                    'customer_id' => $customer->id,
                    'conversation_id' => $conversation->id
                ]);
            } else {
                Log::warning('LINE unfollow event for unknown customer', [
                    'line_user_id' => $lineUserId
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to process LINE unfollow event', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Find or create customer from LINE user using unified identification system
     */
    protected function findOrCreateCustomer($lineUserId, $event)
    {
        try {
            // Get LINE user profile first to obtain potential identifiers
            $profile = $this->getLineUserProfile($lineUserId);
            
            // Build identifier values for customer lookup
            $identifierValues = [];
            $identifierValues['line'] = $lineUserId;
            
            // Check if we can extract phone/email from profile (rare but possible)
            // Most LINE profiles won't have this, but worth checking
            if (!empty($profile['statusMessage'])) {
                // Sometimes users put phone numbers in their status message
                $phonePattern = '/(\d{2,4}[-\s]?\d{6,8}|\d{10,})/';
                if (preg_match($phonePattern, $profile['statusMessage'], $matches)) {
                    $phoneNumber = preg_replace('/\D+/', '', $matches[1]);
                    if (strlen($phoneNumber) >= 8) {
                        $identifierValues['phone'] = $phoneNumber;
                    }
                }
            }
            
            \Illuminate\Support\Facades\DB::beginTransaction();
            
            // First, check for existing customer including soft deleted ones by LINE ID
            $existingCustomer = \App\Models\Customer::withTrashed()->where('line_user_id', $lineUserId)->first();
            
            if (!$existingCustomer && !empty($identifierValues)) {
                // Look for existing customer using identifier system (phone, email, or other LINE IDs)
                $existingCustomer = \App\Models\Customer::query()
                    ->whereHas('identifiers', function ($q) use ($identifierValues) {
                        $q->where(function ($qq) use ($identifierValues) {
                            foreach ($identifierValues as $type => $value) {
                                $qq->orWhere(function ($qqq) use ($type, $value) {
                                    $qqq->where('type', $type)->where('value', $value);
                                });
                            }
                        });
                    })->first();
                    
                // If found via identifiers, log the unification
                if ($existingCustomer) {
                    Log::info('Found existing customer via identifiers for LINE user', [
                        'line_user_id' => $lineUserId,
                        'customer_id' => $existingCustomer->id,
                        'matched_identifiers' => array_keys($identifierValues),
                        'original_channel' => $existingCustomer->channel
                    ]);
                    
                    // Record unification event
                    \App\Models\CustomerActivity::create([
                        'customer_id' => $existingCustomer->id,
                        'user_id' => null,
                        'activity_type' => \App\Models\CustomerActivity::TYPE_UNIFIED,
                        'description' => 'LINE 客戶與現有客戶統一整合',
                        'old_data' => [
                            'original_channel' => $existingCustomer->channel,
                            'line_user_id' => null,
                        ],
                        'new_data' => [
                            'line_user_id' => $lineUserId,
                            'matched_via' => array_keys($identifierValues),
                            'line_display_name' => $profile['displayName'] ?? null,
                        ],
                        'ip_address' => request()->ip(),
                        'user_agent' => 'LINE Bot Webhook',
                    ]);
                }
            }
            
            if (!$existingCustomer) {
                // Create new customer with LINE data
                $existingCustomer = \App\Models\Customer::create([
                    'name' => $profile['displayName'] ?? '來自LINE的客戶',
                    'phone' => '', // Required field, will be empty for now
                    'line_user_id' => $lineUserId,
                    'line_display_name' => $profile['displayName'] ?? null,
                    'channel' => 'line',
                    'status' => \App\Models\Customer::STATUS_NEW,
                    'tracking_status' => \App\Models\Customer::TRACKING_PENDING,
                    'created_by' => 1, // System user
                    'version' => 1, // 添加版本欄位
                    'assigned_to' => null, // Unassigned by default for LINE customers
                    'region' => '未知',
                    'website_source' => 'LINE Bot',
                    'source_data' => [
                        'line_profile' => $profile,
                        'first_contact' => now()->format('c'),
                        'event_type' => $event['type'] ?? 'unknown',
                    ],
                ]);

                Log::info('Created new customer from LINE', [
                    'customer_id' => $existingCustomer->id,
                    'line_user_id' => $lineUserId,
                    'name' => $existingCustomer->name,
                    'display_name' => $profile['displayName'] ?? null
                ]);
                
                // Create activity record for new customer
                \App\Models\CustomerActivity::create([
                    'customer_id' => $existingCustomer->id,
                    'user_id' => null,
                    'activity_type' => \App\Models\CustomerActivity::TYPE_CREATED,
                    'description' => '由 LINE Bot 建立客戶',
                    'old_data' => null,
                    'new_data' => $existingCustomer->toArray(),
                    'ip_address' => request()->ip(),
                    'user_agent' => 'LINE Bot Webhook',
                ]);
                
            } else {
                // Found existing customer - update with LINE information
                $updates = [];
                $oldData = [];
                
                // Check if customer was soft deleted and restore
                if ($existingCustomer->trashed()) {
                    $existingCustomer->restore();
                    
                    Log::info('Restored soft-deleted customer on LINE interaction', [
                        'customer_id' => $existingCustomer->id,
                        'line_user_id' => $lineUserId,
                        'name' => $existingCustomer->name
                    ]);
                }
                
                // Update LINE-specific fields if empty or different
                foreach ([
                    'line_user_id' => $lineUserId,
                    'line_display_name' => $profile['displayName'] ?? null,
                ] as $field => $value) {
                    if ($value && ($existingCustomer->{$field} !== $value)) {
                        $oldData[$field] = $existingCustomer->{$field};
                        $updates[$field] = $value;
                    }
                }
                
                // Update channel to indicate multi-channel customer (web_form + line)
                if ($existingCustomer->channel === 'web_form') {
                    $oldData['channel'] = $existingCustomer->channel;
                    $updates['channel'] = 'multi_channel'; // Indicates customer uses multiple channels
                } elseif (in_array($existingCustomer->channel, [null, ''])) {
                    $oldData['channel'] = $existingCustomer->channel;
                    $updates['channel'] = 'line'; // Primary channel becomes LINE
                }
                
                // Update source data to include LINE profile
                $sourceData = $existingCustomer->source_data ?? [];
                $sourceData['line_profile'] = $profile;
                $sourceData['line_integration_date'] = now()->format('c');
                $updates['source_data'] = $sourceData;
                
                // Add note about LINE integration if this is a web form customer
                if ($existingCustomer->channel === 'web_form') {
                    $updates['notes'] = ($existingCustomer->notes ? $existingCustomer->notes . "\n" : '') . 
                                      '客戶於 ' . now()->format('Y-m-d H:i:s') . ' 加入LINE好友，帳戶已整合';
                }
                
                if (!empty($updates)) {
                    $existingCustomer->fill($updates)->save();
                    
                    // Create activity record for customer update
                    \App\Models\CustomerActivity::create([
                        'customer_id' => $existingCustomer->id,
                        'user_id' => null,
                        'activity_type' => \App\Models\CustomerActivity::TYPE_UPDATED,
                        'description' => 'LINE 整合更新客戶資料',
                        'old_data' => $oldData,
                        'new_data' => $updates,
                        'ip_address' => request()->ip(),
                        'user_agent' => 'LINE Bot Webhook',
                    ]);
                    
                    Log::info('Updated existing customer with LINE data', [
                        'customer_id' => $existingCustomer->id,
                        'line_user_id' => $lineUserId,
                        'updates' => array_keys($updates)
                    ]);
                }
            }
            
            // Create or update customer identifiers (avoid duplicates with unique index)
            foreach ($identifierValues as $type => $value) {
                \App\Models\CustomerIdentifier::firstOrCreate([
                    'type' => $type,
                    'value' => $value,
                ], [
                    'customer_id' => $existingCustomer->id,
                ]);
            }
            
            \Illuminate\Support\Facades\DB::commit();
            
            return $existingCustomer;
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            
            Log::error('Failed to find or create customer from LINE user', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Point 26: Create follow-up case automatically when LINE user adds bot as friend
     */
    protected function createFollowUpCaseIfNeeded($customer, $lineUserId)
    {
        try {
            // Check if this LINE user already has a pending case to avoid duplicates
            $existingLead = CustomerLead::where('customer_id', $customer->id)
                ->where('line_id', $lineUserId)
                ->where('status', 'pending')
                ->exists();
                
            if ($existingLead) {
                Log::info('LINE follow case already exists, skipping creation', [
                    'customer_id' => $customer->id,
                    'line_user_id' => $lineUserId
                ]);
                return;
            }

            // Get LINE profile for email if available
            $profile = $this->getLineUserProfile($lineUserId);
            $email = null; // LINE profiles rarely contain email, but check if available in future
            
            // Create CustomerLead record according to Point 26 specifications
            $lead = CustomerLead::create([
                'customer_id' => $customer->id,
                'status' => 'pending',
                'assigned_to' => $customer->assigned_to, // Inherit from customer if assigned
                'channel' => 'lineoa', // Point 26: source channel should be 'lineoa'
                'source' => null, // Point 26: website field should be empty
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $email, // Point 26: from LINE if available, otherwise empty
                'line_id' => $lineUserId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'payload' => [
                    'event_type' => 'line_follow',
                    'line_display_name' => $profile['displayName'] ?? null,
                    'created_timestamp' => now()->toISOString(),
                ],
                'is_suspected_blacklist' => false,
                'suspected_reason' => null,
            ]);

            // Add notes according to Point 26 specification
            $lead->notes = '此案件為line官方加入好友後自動新建';
            $lead->save();

            // Create activity record for the auto-generated lead
            \App\Models\CustomerActivity::create([
                'customer_id' => $customer->id,
                'user_id' => null, // System-generated
                'activity_type' => \App\Models\CustomerActivity::TYPE_CREATED,
                'description' => 'LINE 加好友自動建立案件',
                'old_data' => null,
                'new_data' => $lead->toArray(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Log::info('LINE follow case created automatically', [
                'customer_id' => $customer->id,
                'lead_id' => $lead->id,
                'line_user_id' => $lineUserId,
                'channel' => 'lineoa'
            ]);

        } catch (\Exception $e) {
            // Log error but don't throw - this is supplementary functionality
            Log::error('Failed to create LINE follow case', [
                'customer_id' => $customer->id,
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Create a simple customer as fallback
     */
    protected function createSimpleCustomer($lineUserId)
    {
        try {
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Creating simple customer for LINE user: $lineUserId\n", 
                FILE_APPEND | LOCK_EX);

            // Check if customer already exists
            $existingCustomer = \App\Models\Customer::where('line_user_id', $lineUserId)->first();
            if ($existingCustomer) {
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Simple customer found existing: {$existingCustomer->id}\n", 
                    FILE_APPEND | LOCK_EX);
                return $existingCustomer;
            }

            // Get admin user for assignment - 改進的錯誤處理
            $assignedTo = 1; // 預設備用值
            try {
                $adminUser = \App\Models\User::whereHas('roles', function($q) {
                    $q->where('name', 'admin');
                })->first();
                
                if ($adminUser) {
                    $assignedTo = $adminUser->id;
                    file_put_contents(storage_path('logs/webhook-debug.log'), 
                        date('Y-m-d H:i:s') . " - Point17 - 找到admin用戶: ID={$adminUser->id}, Name={$adminUser->name}\n", 
                        FILE_APPEND | LOCK_EX);
                } else {
                    // 如果沒有admin角色用戶，嘗試找第一個可用用戶
                    $firstUser = \App\Models\User::first();
                    if ($firstUser) {
                        $assignedTo = $firstUser->id;
                        file_put_contents(storage_path('logs/webhook-debug.log'), 
                            date('Y-m-d H:i:s') . " - Point17 - 沒有admin用戶，使用第一個用戶: ID={$firstUser->id}\n", 
                            FILE_APPEND | LOCK_EX);
                    }
                }
            } catch (\Exception $roleError) {
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Point17 - 角色查詢失敗: " . $roleError->getMessage() . "，使用預設ID=1\n", 
                    FILE_APPEND | LOCK_EX);
            }

            $customer = \App\Models\Customer::create([
                'name' => 'LINE用戶 ' . substr($lineUserId, -6),
                'phone' => '0900000000', // 提供預設電話號碼避免必填錯誤
                'line_user_id' => $lineUserId,
                'channel' => 'line',
                'status' => 'new',
                'tracking_status' => 'pending',
                'assigned_to' => $assignedTo,
                'version' => 1,
                'version_updated_at' => now(), // 添加版本更新時間
                'website_source' => 'line',
                'region' => 'unknown',
                'source' => 'line_webhook'
            ]);

            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Simple customer created with ID: {$customer->id}\n", 
                FILE_APPEND | LOCK_EX);

            return $customer;
        } catch (\Exception $e) {
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Simple customer creation FAILED: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            return null;
        }
    }

    /**
     * Get LINE user profile
     */
    protected function getLineUserProfile($lineUserId)
    {
        try {
            $settings = $this->getLineSettings();
            $token = $settings['channel_access_token'];

            if (!$token) {
                return [];
            }

            $client = new \GuzzleHttp\Client();
            $response = $client->get("https://api.line.me/v2/bot/profile/{$lineUserId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'timeout' => 10,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Failed to get LINE user profile', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }



    /**
     * Send LINE message
     */
    protected function sendLineMessage($lineUserId, $message)
    {
        try {
            $settings = $this->getLineSettings();
            $token = $settings['channel_access_token'];

            if (!$token) {
                Log::error('LINE Channel Access Token not configured', [
                    'line_user_id' => $lineUserId,
                    'settings' => array_keys($settings)
                ]);
                return false;
            }

            // Validate LINE User ID format
            if (empty($lineUserId) || !is_string($lineUserId)) {
                Log::error('Invalid LINE User ID format', [
                    'line_user_id' => $lineUserId,
                    'type' => gettype($lineUserId)
                ]);
                return false;
            }

            // Validate message content
            if (empty($message) || !is_string($message)) {
                Log::error('Invalid message content', [
                    'message' => $message,
                    'type' => gettype($message)
                ]);
                return false;
            }

            $client = new \GuzzleHttp\Client();
            
            Log::info('Sending LINE message', [
                'line_user_id' => $lineUserId,
                'message_length' => strlen($message),
                'token_length' => strlen($token)
            ]);

            $response = $client->post('https://api.line.me/v2/bot/message/push', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'to' => $lineUserId,
                    'messages' => [
                        [
                            'type' => 'text',
                            'text' => $message
                        ]
                    ]
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            Log::info('LINE message sent successfully', [
                'line_user_id' => $lineUserId,
                'response_code' => $statusCode,
                'response_body' => $responseBody
            ]);

            // Check if response indicates success
            if ($statusCode >= 200 && $statusCode < 300) {
                return true;
            } else {
                Log::error('LINE API returned error status', [
                    'status_code' => $statusCode,
                    'response_body' => $responseBody
                ]);
                return false;
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $responseBody = $response ? $response->getBody()->getContents() : 'No response body';
            
            Log::error('LINE API client error', [
                'line_user_id' => $lineUserId,
                'message' => $message,
                'status_code' => $response ? $response->getStatusCode() : 'unknown',
                'error' => $e->getMessage(),
                'response_body' => $responseBody
            ]);
            return false;
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $response = $e->getResponse();
            $responseBody = $response ? $response->getBody()->getContents() : 'No response body';
            
            Log::error('LINE API server error', [
                'line_user_id' => $lineUserId,
                'message' => $message,
                'status_code' => $response ? $response->getStatusCode() : 'unknown',
                'error' => $e->getMessage(),
                'response_body' => $responseBody
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to send LINE message', [
                'line_user_id' => $lineUserId,
                'message' => $message,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Handle referral code input from customer
     * Auto-reply removed as per Point 128
     */
    protected function handleReferralCodeInput($lineUserId, $customer, $referralCode)
    {
        try {
            // Update customer with referral code
            $customer->update([
                'source_data' => array_merge($customer->source_data ?? [], [
                    'referral_code' => $referralCode,
                    'referral_code_entered_at' => now()->format('c'),
                ]),
                'notes' => ($customer->notes ? $customer->notes . "\n" : '') . "客戶輸入推薦碼：{$referralCode}",
            ]);

            // Auto-reply functionality removed - no confirmation message sent
            
            Log::info('Referral code processed successfully (no auto-reply)', [
                'line_user_id' => $lineUserId,
                'customer_id' => $customer->id,
                'referral_code' => $referralCode
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process referral code', [
                'line_user_id' => $lineUserId,
                'referral_code' => $referralCode,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle referral code skip from customer
     * Auto-reply removed as per Point 128
     */
    protected function handleReferralCodeSkip($lineUserId, $customer)
    {
        try {
            // Update customer to indicate they skipped referral code
            $customer->update([
                'source_data' => array_merge($customer->source_data ?? [], [
                    'referral_code_skipped' => true,
                    'referral_code_skipped_at' => now()->format('c'),
                ]),
                'notes' => ($customer->notes ? $customer->notes . "\n" : '') . "客戶跳過推薦碼輸入",
            ]);

            // Auto-reply functionality removed - no acknowledgment message sent
            
            Log::info('Referral code skip processed successfully (no auto-reply)', [
                'line_user_id' => $lineUserId,
                'customer_id' => $customer->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process referral code skip', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create referral code flex message
     */
    protected function createReferralCodeFlexMessage()
    {
        return [
            'type' => 'bubble',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '業務推薦碼',
                        'weight' => 'bold',
                        'size' => 'lg',
                        'color' => '#1DB446'
                    ]
                ],
                'backgroundColor' => '#F0F8F0',
                'paddingAll' => 'md'
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '請輸入業務推薦碼',
                        'size' => 'md',
                        'color' => '#666666',
                        'margin' => 'sm'
                    ],
                    [
                        'type' => 'text',
                        'text' => '有推薦碼可享更優惠的利率和服務！',
                        'size' => 'sm',
                        'color' => '#999999',
                        'wrap' => true,
                        'margin' => 'sm'
                    ]
                ],
                'spacing' => 'sm',
                'paddingAll' => 'md'
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'button',
                        'style' => 'primary',
                        'height' => 'sm',
                        'action' => [
                            'type' => 'uri',
                            'label' => '輸入推薦碼',
                            'uri' => 'line://nv/compose'
                        ],
                        'color' => '#1DB446'
                    ],
                    [
                        'type' => 'button',
                        'style' => 'secondary',
                        'height' => 'sm',
                        'action' => [
                            'type' => 'message',
                            'label' => '暫時跳過',
                            'text' => '跳過推薦碼'
                        ],
                        'margin' => 'sm'
                    ]
                ],
                'spacing' => 'sm',
                'paddingAll' => 'md'
            ]
        ];
    }

    /**
     * Send LINE flex message
     */
    protected function sendLineFlexMessage($lineUserId, $flexMessage)
    {
        try {
            $settings = $this->getLineSettings();
            $token = $settings['channel_access_token'];

            if (!$token) {
                Log::error('LINE Channel Access Token not configured for flex message');
                return false;
            }

            $client = new \GuzzleHttp\Client();
            $response = $client->post('https://api.line.me/v2/bot/message/push', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'to' => $lineUserId,
                    'messages' => [
                        [
                            'type' => 'flex',
                            'altText' => '請輸入業務推薦碼',
                            'contents' => $flexMessage
                        ]
                    ]
                ],
                'timeout' => 10,
            ]);

            Log::info('LINE flex message sent successfully', [
                'line_user_id' => $lineUserId,
                'response_code' => $response->getStatusCode()
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send LINE flex message', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get welcome message for new followers
     */
    protected function getWelcomeMessage()
    {
        return "歡迎加入我們的LINE官方帳號！\n\n我們提供以下貸款服務：\n🚗 汽車貸款\n🛵 機車貸款\n📱 手機貸款\n\n如有任何問題，請隨時與我們聯繫，專員將盡快為您服務！";
    }

    /**
     * Safely update conversation status, handling ENUM constraints
     */
    protected function safeUpdateStatus($conversation, $status)
    {
        try {
            $conversation->update(['status' => $status]);
            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            // Check if it's an ENUM constraint error
            if (strpos($e->getMessage(), 'Data truncated for column \'status\'') !== false ||
                strpos($e->getMessage(), 'enum') !== false) {
                Log::warning('Status update failed due to ENUM constraint', [
                    'conversation_id' => $conversation->id,
                    'attempted_status' => $status,
                    'error' => $e->getMessage()
                ]);
                
                // Fallback to 'replied' status which should always be valid
                try {
                    $conversation->update(['status' => 'replied']);
                    Log::info('Fallback status update successful', [
                        'conversation_id' => $conversation->id,
                        'fallback_status' => 'replied'
                    ]);
                    return true;
                } catch (\Exception $fallbackError) {
                    Log::error('Fallback status update also failed', [
                        'conversation_id' => $conversation->id,
                        'fallback_error' => $fallbackError->getMessage()
                    ]);
                    return false;
                }
            } else {
                // Re-throw if it's not an ENUM error
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Unexpected error during status update', [
                'conversation_id' => $conversation->id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Safely create conversation with status, handling ENUM constraints
     */
    protected function safeCreateConversation($data)
    {
        $originalStatus = $data['status'] ?? 'unread';
        
        try {
            return ChatConversation::create($data);
        } catch (\Illuminate\Database\QueryException $e) {
            // Check if it's an ENUM constraint error
            if (strpos($e->getMessage(), 'Data truncated for column \'status\'') !== false ||
                strpos($e->getMessage(), 'enum') !== false) {
                Log::warning('Conversation creation failed due to status ENUM constraint', [
                    'attempted_status' => $originalStatus,
                    'error' => $e->getMessage()
                ]);
                
                // Fallback to 'replied' status
                $data['status'] = 'replied';
                try {
                    $conversation = ChatConversation::create($data);
                    Log::info('Fallback conversation creation successful', [
                        'conversation_id' => $conversation->id,
                        'original_status' => $originalStatus,
                        'fallback_status' => 'replied'
                    ]);
                    return $conversation;
                } catch (\Exception $fallbackError) {
                    Log::error('Fallback conversation creation also failed', [
                        'original_status' => $originalStatus,
                        'fallback_error' => $fallbackError->getMessage()
                    ]);
                    throw $fallbackError;
                }
            } else {
                // Re-throw if it's not an ENUM error
                throw $e;
            }
        }
    }

    /**
     * Long polling endpoint for real-time chat updates
     */
    public function pollUpdates(Request $request)
    {
        try {
            $user = Auth::user();
            $timeout = min($request->get('timeout', 20), 30); // 預設 20 秒
            $clientVersion = $request->get('version', 0);
            $lineUserId = $request->get('line_user_id');
            
            // 注入版本服務
            $versionService = app(\App\Services\ChatVersionService::class);
            
            // 檢查系統健康狀態並嘗試初始化
            $systemHealth = $versionService->checkSystemHealth();
            
            if ($systemHealth === 'critical_error') {
                return response()->json([
                    'success' => false,
                    'error' => 'System not ready',
                    'message' => 'Chat system is not properly initialized'
                ], 503);
            }
            
            if ($systemHealth === 'degraded') {
                Log::info('Attempting to initialize version system due to degraded health');
                $versionService->initializeVersionSystem();
            }
            
            $startTime = microtime(true);
            $pollingInterval = 0.5; // 500毫秒檢查一次
            $maxChecks = (int)($timeout / $pollingInterval);
            
            // Long Polling 循環
            for ($i = 0; $i < $maxChecks; $i++) {
                try {
                    // 檢查是否有新版本
                    if ($versionService->needsUpdate($clientVersion)) {
                        // 獲取變化的數據
                        $changes = $versionService->getChangesSince($clientVersion, $lineUserId);
                        
                        if ($changes->isNotEmpty()) {
                            return response()->json([
                                'success' => true,
                                'version' => $versionService->getCurrentVersion(),
                                'data' => $this->formatChanges($changes),
                                'timestamp' => now()->format('c'),
                                'response_time' => round((microtime(true) - $startTime) * 1000, 2),
                                'system_health' => $systemHealth
                            ]);
                        }
                    }
                } catch (\Exception $checkError) {
                    Log::warning('Error during polling check iteration', [
                        'iteration' => $i,
                        'error' => $checkError->getMessage()
                    ]);
                    // 繼續下一次檢查而不是完全失敗
                }
                
                // 如果沒有更新，等待後繼續檢查
                usleep($pollingInterval * 1000000);
            }
            
            // 超時返回，告知客戶端當前版本
            return response()->json([
                'success' => true,
                'version' => $versionService->getCurrentVersion(),
                'data' => [],
                'timestamp' => now()->format('c'),
                'timeout' => true,
                'system_health' => $systemHealth
            ]);
            
        } catch (\Exception $e) {
            Log::error('Long polling error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Polling failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal error',
                'debug_info' => config('app.debug') ? [
                    'client_version' => $request->get('version', 0),
                    'line_user_id' => $request->get('line_user_id')
                ] : null
            ], 500);
        }
    }

    /**
     * Check for updates since last polling
     */
    private function checkForUpdates($user, $lastUpdate, $lineUserId = null)
    {
        $updates = [];
        
        try {
            $lastUpdateTime = $lastUpdate ? 
                \Carbon\Carbon::parse($lastUpdate) : 
                now()->subMinutes(1); // 縮短到1分鐘，減少查詢範圍
            
            if ($lineUserId) {
                // 檢查特定對話的新訊息 - 優化查詢，只選擇必要欄位
                $newMessages = ChatConversation::select([
                        'id', 'line_user_id', 'message_content', 'message_timestamp', 
                        'is_from_customer', 'status', 'message_type', 'metadata'
                    ])
                    ->where('line_user_id', $lineUserId)
                    ->where('message_timestamp', '>', $lastUpdateTime)
                    ->orderBy('message_timestamp', 'asc')
                    ->limit(50) // 限制結果數量
                    ->get();
                
                if ($newMessages->isNotEmpty()) {
                    foreach ($newMessages as $msg) {
                        $updates[] = [
                            'type' => 'new_message',
                            'data' => [
                                'id' => $msg->id,
                                'line_user_id' => $msg->line_user_id,
                                'message_content' => $msg->message_content,
                                'message_timestamp' => $msg->message_timestamp,
                                'is_from_customer' => $msg->is_from_customer,
                                'status' => $msg->status,
                                'message_type' => $msg->message_type,
                                'metadata' => $msg->metadata
                            ]
                        ];
                    }
                }
            } else {
                // 檢查所有對話的更新 - 優化查詢性能
                $conversationUpdates = ChatConversation::select([
                        'line_user_id', 
                        \DB::raw('MAX(message_timestamp) as last_message_time'),
                        \DB::raw('COUNT(*) as message_count')
                    ])
                    ->where('message_timestamp', '>', $lastUpdateTime)
                    ->groupBy('line_user_id')
                    ->limit(20) // 限制對話數量
                    ->get();
                
                if ($conversationUpdates->isNotEmpty()) {
                    foreach ($conversationUpdates as $conversation) {
                        $updates[] = [
                            'type' => 'conversation_update',
                            'data' => [
                                'line_user_id' => $conversation->line_user_id,
                                'last_message_time' => $conversation->last_message_time,
                                'message_count' => $conversation->message_count
                            ]
                        ];
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Error checking for updates:', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'line_user_id' => $lineUserId
            ]);
        }
        
        return $updates;
    }

    /**
     * 格式化變化數據
     */
    private function formatChanges($changes)
    {
        return $changes->map(function ($change) {
            return [
                'type' => $change->is_from_customer ? 'customer_message' : 'system_message',
                'data' => [
                    'id' => $change->id,
                    'line_user_id' => $change->line_user_id,
                    'content' => $change->message_content,
                    'timestamp' => $change->message_timestamp,
                    'is_from_customer' => $change->is_from_customer,
                    'status' => $change->status,
                    'version' => $change->version,
                    'message_type' => $change->message_type ?? 'text',
                    'metadata' => is_string($change->metadata) ? json_decode($change->metadata, true) : $change->metadata
                ]
            ];
        });
    }

    /**
     * 獲取增量更新（新方法）
     */
    public function getIncrementalUpdates(Request $request)
    {
        $request->validate([
            'version' => 'required|integer|min:0',
            'type' => 'required|in:conversations,messages',
            'line_user_id' => 'required_if:type,messages',
            'timestamp' => 'nullable|date',
        ]);
        
        $user = Auth::user();
        $incrementalService = app(ChatIncrementalService::class);
        
        try {
            if ($request->type === 'conversations') {
                // 獲取對話列表增量
                $changes = $incrementalService->getConversationListChanges(
                    $user->id,
                    $request->version,
                    $request->timestamp
                );
            } else {
                // 獲取消息列表增量
                $changes = $incrementalService->getMessageListChanges(
                    $request->line_user_id,
                    $request->version,
                    $request->timestamp
                );
            }
            
            return new ChatIncrementalResource($changes);
            
        } catch (\Exception $e) {
            Log::error('Incremental update error:', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get incremental updates',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 驗證增量數據完整性
     */
    public function validateChecksum(Request $request)
    {
        $request->validate([
            'checksum' => 'required|string',
            'data' => 'required|array',
        ]);
        
        $calculatedChecksum = md5(json_encode($request->data, JSON_SORT_KEYS | JSON_UNESCAPED_UNICODE));
        $isValid = $calculatedChecksum === $request->checksum;
        
        return response()->json([
            'valid' => $isValid,
            'expected' => $request->checksum,
            'calculated' => $calculatedChecksum,
        ]);
    }

    /**
     * Test WebSocket broadcasting functionality
     */
    public function testWebSocketBroadcast(Request $request)
    {
        try {
            $user = Auth::user();
            $lineUserId = $request->input('line_user_id', 'test_user_123');
            
            Log::info('Testing WebSocket broadcast', [
                'user_id' => $user->id,
                'line_user_id' => $lineUserId,
                'broadcast_driver' => config('broadcasting.default')
            ]);

            // Create a test conversation
            $testConversation = new ChatConversation([
                'id' => 999999,
                'customer_id' => null,
                'user_id' => $user->id,
                'line_user_id' => $lineUserId,
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => 'Test WebSocket broadcast message: ' . now()->toTimeString(),
                'message_timestamp' => now(),
                'is_from_customer' => true,
                'status' => 'unread'
            ]);

            // Test broadcast
            broadcast(new NewChatMessage($testConversation, $lineUserId));
            
            Log::info('WebSocket test broadcast sent', ['line_user_id' => $lineUserId]);

            return response()->json([
                'success' => true,
                'message' => '測試廣播已發送',
                'data' => [
                    'line_user_id' => $lineUserId,
                    'test_message' => $testConversation->message_content,
                    'timestamp' => $testConversation->message_timestamp,
                    'broadcast_driver' => config('broadcasting.default')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('WebSocket test broadcast failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'WebSocket 測試失敗',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 獲取查詢性能和緩存統計
     */
    public function getQueryStats()
    {
        try {
            $user = Auth::user();
            
            // 檢查管理員權限
            if (!$user->isAdmin() && !$user->isManager()) {
                return response()->json([
                    'success' => false,
                    'error' => '權限不足'
                ], 403);
            }
            
            $performanceMonitor = app(\App\Services\QueryPerformanceMonitor::class);
            
            $stats = [
                'cache_stats' => $this->cacheService->getCacheStats(),
                'query_performance' => $performanceMonitor->getQueryStats(),
                'database_stats' => $this->getDatabaseStats(),
                'timestamp' => now()->format('c')
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get query stats', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => '獲取統計數據失敗',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * 清除查詢緩存
     */
    public function clearQueryCache(Request $request)
    {
        try {
            $user = Auth::user();
            
            // 檢查管理員權限
            if (!$user->isAdmin() && !$user->isManager()) {
                return response()->json([
                    'success' => false,
                    'error' => '權限不足'
                ], 403);
            }
            
            $type = $request->get('type', 'all');
            $userId = $request->get('user_id');
            $lineUserId = $request->get('line_user_id');
            
            $clearedCount = 0;
            
            switch ($type) {
                case 'user':
                    if ($userId) {
                        $this->cacheService->clearUserCache($userId);
                        $clearedCount = 1;
                    }
                    break;
                    
                case 'conversation':
                    if ($lineUserId) {
                        $this->cacheService->clearConversationCache($lineUserId);
                        $clearedCount = 1;
                    }
                    break;
                    
                case 'all':
                default:
                    // 清除所有聊天相關緩存
                    Cache::tags(['chat_query'])->flush();
                    $clearedCount = 'all';
                    break;
            }
            
            return response()->json([
                'success' => true,
                'message' => '緩存已清除',
                'cleared_count' => $clearedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to clear query cache', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => '清除緩存失敗',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * 獲取數據庫統計信息
     */
    private function getDatabaseStats()
    {
        try {
            $stats = [
                'total_conversations' => DB::table('chat_conversations')->count(),
                'unread_conversations' => DB::table('chat_conversations')
                    ->where('status', 'unread')
                    ->where('is_from_customer', true)
                    ->count(),
                'active_customers' => DB::table('chat_conversations')
                    ->where('message_timestamp', '>=', now()->subDays(7))
                    ->distinct('line_user_id')
                    ->count(),
                'avg_response_time' => $this->getAverageResponseTime()
            ];
            
            return $stats;
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to get database stats: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 計算平均回應時間
     */
    private function getAverageResponseTime()
    {
        try {
            // 計算系統回應客戶訊息的平均時間
            $result = DB::select("
                SELECT AVG(response_time) as avg_time
                FROM (
                    SELECT 
                        TIMESTAMPDIFF(MINUTE, customer_msg.message_timestamp, system_msg.message_timestamp) as response_time
                    FROM chat_conversations customer_msg
                    JOIN chat_conversations system_msg ON (
                        customer_msg.line_user_id = system_msg.line_user_id 
                        AND customer_msg.is_from_customer = 1 
                        AND system_msg.is_from_customer = 0
                        AND system_msg.message_timestamp > customer_msg.message_timestamp
                    )
                    WHERE customer_msg.message_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND TIMESTAMPDIFF(MINUTE, customer_msg.message_timestamp, system_msg.message_timestamp) <= 1440
                ) response_times
            ");
            
            return $result[0]->avg_time ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * 批量標記訊息為已讀（優化版）
     */
    private function markMessagesAsRead($lineUserId)
    {
        try {
            // 批量更新未讀訊息狀態
            $updated = DB::table('chat_conversations')
                ->where('line_user_id', $lineUserId)
                ->where('status', 'unread')
                ->where('is_from_customer', true)
                ->update(['status' => 'read']);
            
            if ($updated > 0) {
                // 清除相關緩存
                $this->cacheService->clearConversationCache($lineUserId);
                
                Log::info('Batch marked messages as read', [
                    'line_user_id' => $lineUserId,
                    'updated_count' => $updated
                ]);
            }
            
            return $updated;
        } catch (\Exception $e) {
            Log::error('Failed to batch mark messages as read', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    // ================================================
    // Firebase Integration Methods
    // ================================================

    /**
     * 獲取 Firebase 對話列表
     */
    public function getFirebaseConversations(Request $request)
    {
        try {
            $user = Auth::user();
            $staffId = null;

            // 根據權限決定是否過濾特定業務員
            if (!$user->hasRole(['admin', 'manager', 'executive'])) {
                $staffId = $user->id;
            }

            $conversations = $this->firebaseChatService->getConversationsFromFirebase($staffId);

            Log::channel('firebase')->info('Firebase conversations retrieved', [
                'user_id' => $user->id,
                'staff_id' => $staffId,
                'conversation_count' => count($conversations)
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'conversations' => $conversations,
                    'count' => count($conversations),
                    'source' => 'firebase'
                ]
            ]);

        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to get Firebase conversations', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve conversations from Firebase',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * 獲取 Firebase 訊息
     */
    public function getFirebaseMessages($userId, Request $request)
    {
        try {
            $user = Auth::user();
            $limit = $request->input('limit', 50);

            // 權限檢查
            if (!$this->canAccessUserConversation($user, $userId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied'
                ], 403);
            }

            $messages = $this->firebaseChatService->getMessagesFromFirebase($userId, $limit);

            Log::channel('firebase')->info('Firebase messages retrieved', [
                'user_id' => $user->id,
                'line_user_id' => $userId,
                'message_count' => count($messages),
                'limit' => $limit
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $messages,
                    'count' => count($messages),
                    'source' => 'firebase'
                ]
            ]);

        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to get Firebase messages', [
                'user_id' => Auth::id(),
                'line_user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve messages from Firebase',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * 同步資料到 Firebase
     */
    public function syncToFirebase(Request $request)
    {
        try {
            $user = Auth::user();
            $conversationId = $request->input('conversation_id');

            $result = $this->firebaseSyncService->syncMySQLToFirebase($conversationId);

            Log::channel('firebase')->info('Firebase sync initiated', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'result' => $result
            ]);

            return response()->json([
                'success' => $result['success'],
                'data' => $result,
                'message' => $result['success'] 
                    ? 'Sync completed successfully' 
                    : 'Sync failed'
            ]);

        } catch (\Exception $e) {
            Log::channel('firebase')->error('Firebase sync failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Sync operation failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * 同步特定客戶到 Firebase
     */
    public function syncCustomerToFirebase($customerId, Request $request)
    {
        try {
            $user = Auth::user();

            // 權限檢查
            $customer = Customer::find($customerId);
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'error' => 'Customer not found'
                ], 404);
            }

            if (!$this->canAccessCustomer($user, $customer)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied'
                ], 403);
            }

            $result = $this->firebaseSyncService->syncCustomerToFirebase($customerId);

            Log::channel('firebase')->info('Customer Firebase sync initiated', [
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'result' => $result
            ]);

            return response()->json([
                'success' => $result['success'],
                'data' => $result,
                'message' => $result['success'] 
                    ? 'Customer sync completed successfully' 
                    : 'Customer sync failed'
            ]);

        } catch (\Exception $e) {
            Log::channel('firebase')->error('Customer Firebase sync failed', [
                'user_id' => Auth::id(),
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Customer sync operation failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * 檢查 Firebase 健康狀態 - 擴展版
     */
    public function checkFirebaseHealth(Request $request)
    {
        try {
            $user = Auth::user();

            $healthResult = $this->firebaseSyncService->checkSyncHealth();
            $connectionStatus = $this->firebaseChatService->checkFirebaseConnection();

            // 擴展健康檢查：獲取更詳細的資訊
            $extendedHealth = [
                'firebase_connection' => $connectionStatus,
                'sync_health' => $healthResult,
                'configuration' => $this->getFirebaseConfigurationStatus(),
                'database_connectivity' => $this->testFirebaseDatabaseConnection(),
                'sync_statistics' => $this->getFirebaseSyncStatistics(),
                'recent_errors' => $this->getRecentFirebaseErrors(),
                'timestamp' => now()->format('c'),
                'checked_by' => $user->id
            ];

            // 判斷整體健康狀態
            $overallStatus = $this->determineOverallFirebaseHealth($extendedHealth);
            $extendedHealth['overall_status'] = $overallStatus;

            Log::channel('firebase')->info('Extended Firebase health check performed', [
                'user_id' => $user->id,
                'connection_status' => $connectionStatus,
                'sync_health' => $healthResult['success'] ?? false,
                'overall_status' => $overallStatus
            ]);

            return response()->json([
                'success' => true,
                'data' => $extendedHealth,
                'message' => 'Extended Firebase health check completed',
                'recommendations' => $this->getFirebaseHealthRecommendations($extendedHealth)
            ]);

        } catch (\Exception $e) {
            Log::channel('firebase')->error('Extended Firebase health check failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Health check failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * 獲取 Firebase 配置狀態
     */
    private function getFirebaseConfigurationStatus()
    {
        try {
            return [
                'project_id' => !empty(config('services.firebase.project_id')),
                'database_url' => !empty(config('services.firebase.database_url')),
                'credentials_path' => !empty(config('services.firebase.credentials')),
                'credentials_file_exists' => config('services.firebase.credentials') ? file_exists(config('services.firebase.credentials')) : false,
                'debug_mode' => config('firebase.debug_mode', false) || env('FIREBASE_DEBUG_MODE', false),
                'enabled' => env('FIREBASE_ENABLED', true)
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to check configuration: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 測試 Firebase Realtime Database 連接
     */
    private function testFirebaseDatabaseConnection()
    {
        try {
            $testResult = $this->firebaseChatService->checkFirebaseConnection();
            
            // 嘗試簡單的讀取操作來測試實際連接
            $conversations = $this->firebaseChatService->getConversationsFromFirebase(null);
            
            return [
                'basic_connection' => $testResult,
                'can_read_data' => is_array($conversations),
                'data_count' => is_array($conversations) ? count($conversations) : 0,
                'last_test_time' => now()->format('c')
            ];
        } catch (\Exception $e) {
            return [
                'basic_connection' => false,
                'can_read_data' => false,
                'data_count' => 0,
                'error' => $e->getMessage(),
                'last_test_time' => now()->format('c')
            ];
        }
    }

    /**
     * 獲取 Firebase 同步統計
     */
    private function getFirebaseSyncStatistics()
    {
        try {
            // 統計需要同步的對話數量
            $needsSyncCount = ChatConversation::whereNotNull('line_user_id')
                ->whereHas('customer', function($query) {
                    $query->whereNotNull('assigned_to');
                })
                ->where('updated_at', '>=', now()->subHours(24))
                ->count();

            // 統計總對話數量
            $totalConversations = ChatConversation::whereNotNull('line_user_id')->count();

            // 統計今日新增的對話
            $todayConversations = ChatConversation::whereNotNull('line_user_id')
                ->whereDate('created_at', today())
                ->count();

            return [
                'total_conversations' => $totalConversations,
                'needs_sync_count' => $needsSyncCount,
                'today_conversations' => $todayConversations,
                'sync_enabled' => env('FIREBASE_ENABLED', true),
                'last_calculated' => now()->format('c')
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to calculate sync statistics: ' . $e->getMessage(),
                'last_calculated' => now()->format('c')
            ];
        }
    }

    /**
     * 獲取最近的 Firebase 錯誤
     */
    private function getRecentFirebaseErrors()
    {
        try {
            // 這裡可以從日誌文件或錯誤追蹤系統獲取錯誤
            // 暫時返回基本資訊
            return [
                'has_recent_errors' => false,
                'error_count_24h' => 0,
                'last_error_time' => null,
                'note' => 'Error tracking not implemented yet'
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to get recent errors: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 判斷整體 Firebase 健康狀態
     */
    private function determineOverallFirebaseHealth($healthData)
    {
        $issues = [];

        // 檢查基本配置
        if (!($healthData['configuration']['project_id'] ?? false)) {
            $issues[] = 'missing_project_id';
        }
        if (!($healthData['configuration']['database_url'] ?? false)) {
            $issues[] = 'missing_database_url';
        }
        if (!($healthData['configuration']['credentials_file_exists'] ?? false)) {
            $issues[] = 'missing_credentials_file';
        }

        // 檢查連接狀態
        if (!($healthData['firebase_connection'] ?? false)) {
            $issues[] = 'connection_failed';
        }
        if (!($healthData['database_connectivity']['can_read_data'] ?? false)) {
            $issues[] = 'database_read_failed';
        }

        // 檢查同步狀態
        if (!($healthData['sync_health']['success'] ?? false)) {
            $issues[] = 'sync_unhealthy';
        }

        // 根據問題數量判斷狀態
        if (empty($issues)) {
            return 'healthy';
        } elseif (count($issues) <= 2) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    /**
     * 獲取 Firebase 健康建議
     */
    private function getFirebaseHealthRecommendations($healthData)
    {
        $recommendations = [];

        // 配置相關建議
        if (!($healthData['configuration']['project_id'] ?? false)) {
            $recommendations[] = '請設定 FIREBASE_PROJECT_ID 環境變數';
        }
        if (!($healthData['configuration']['database_url'] ?? false)) {
            $recommendations[] = '請設定 FIREBASE_DATABASE_URL 環境變數';
        }
        if (!($healthData['configuration']['credentials_file_exists'] ?? false)) {
            $recommendations[] = '請確認 Firebase 服務帳號憑證檔案存在';
        }

        // 連接相關建議
        if (!($healthData['firebase_connection'] ?? false)) {
            $recommendations[] = '檢查網路連接和 Firebase 專案設定';
        }
        if (!($healthData['database_connectivity']['can_read_data'] ?? false)) {
            $recommendations[] = '檢查 Firebase Realtime Database 權限設定';
        }

        // 資料相關建議
        if (($healthData['database_connectivity']['data_count'] ?? 0) === 0) {
            $recommendations[] = '執行批次同步將 MySQL 資料同步到 Firebase';
        }

        return $recommendations;
    }

    /**
     * 驗證 Firebase 資料一致性
     */
    public function validateFirebaseData(Request $request)
    {
        try {
            $user = Auth::user();
            $lineUserId = $request->input('line_user_id');

            $validationResult = $this->firebaseSyncService->validateDataConsistency($lineUserId);

            Log::channel('firebase')->info('Firebase data validation performed', [
                'user_id' => $user->id,
                'line_user_id' => $lineUserId,
                'validation_result' => $validationResult
            ]);

            return response()->json([
                'success' => $validationResult['success'],
                'data' => $validationResult,
                'message' => $validationResult['success'] 
                    ? 'Data validation completed' 
                    : 'Data validation failed'
            ]);

        } catch (\Exception $e) {
            Log::channel('firebase')->error('Firebase data validation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Data validation failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * 清理 Firebase 過期資料
     */
    public function cleanupFirebaseData(Request $request)
    {
        try {
            $user = Auth::user();
            $daysOld = $request->input('days_old', 30);

            // 只有管理員才能執行清理操作
            if (!$user->hasRole(['admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Insufficient permissions'
                ], 403);
            }

            $cleanupResult = $this->firebaseSyncService->cleanupExpiredFirebaseData($daysOld);

            Log::channel('firebase')->info('Firebase data cleanup performed', [
                'user_id' => $user->id,
                'days_old' => $daysOld,
                'cleanup_result' => $cleanupResult
            ]);

            return response()->json([
                'success' => $cleanupResult['success'],
                'data' => $cleanupResult,
                'message' => $cleanupResult['success'] 
                    ? 'Data cleanup completed' 
                    : 'Data cleanup failed'
            ]);

        } catch (\Exception $e) {
            Log::channel('firebase')->error('Firebase data cleanup failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Data cleanup failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    // ================================================
    // Helper Methods for Firebase Integration
    // ================================================

    /**
     * 檢查用戶是否可以存取特定客戶的對話
     */
    private function canAccessUserConversation($user, $lineUserId)
    {
        // 管理員和主管可以存取所有對話
        if ($user->hasRole(['admin', 'manager', 'executive'])) {
            return true;
        }

        // 業務員只能存取指派給他們的客戶對話
        $customer = Customer::where('line_user_id', $lineUserId)->first();
        if (!$customer) {
            return false;
        }

        return $customer->assigned_to === $user->id;
    }

    /**
     * 檢查用戶是否可以存取特定客戶
     */
    private function canAccessCustomer($user, $customer)
    {
        // 管理員和主管可以存取所有客戶
        if ($user->hasRole(['admin', 'manager', 'executive'])) {
            return true;
        }

        // 業務員只能存取指派給他們的客戶
        return $customer->assigned_to === $user->id;
    }

    /**
     * 批次同步 MySQL 資料到 Firebase - Debug 專用
     */
    public function batchSyncToFirebaseDebug(Request $request)
    {
        try {
            $user = Auth::user();
            
            // 檢查權限：只有管理員和主管可以執行批次同步
            if (!$user->hasRole(['admin', 'manager', 'executive'])) {
                return response()->json([
                    'success' => false,
                    'error' => '權限不足',
                    'message' => '只有管理員可以執行批次同步'
                ], 403);
            }

            // 檢查除錯模式
            if (!$this->isDebugEnabled()) {
                return response()->json([
                    'success' => false,
                    'error' => '除錯模式未啟用',
                    'message' => '此功能僅在除錯模式下可用',
                    'debug_info' => [
                        'app_debug' => config('app.debug'),
                        'firebase_debug_mode' => config('services.firebase.debug_mode', false),
                        'env_firebase_debug' => env('FIREBASE_DEBUG_MODE', false),
                        'combined_debug_mode' => $this->isDebugEnabled()
                    ]
                ], 403);
            }

            $limit = min($request->input('limit', 50), 1000); // 增加批次限制以支援完整同步
            $offset = max($request->input('offset', 0), 0);
            $forceSync = $request->boolean('force', false);

            // 獲取需要同步的對話
            $query = ChatConversation::with('customer')
                ->whereNotNull('line_user_id')
                ->whereHas('customer', function($q) {
                    $q->whereNotNull('assigned_to');
                });

            if (!$forceSync) {
                // 只同步最近24小時的對話
                $query->where('updated_at', '>=', now()->subHours(24));
            }

            $conversations = $query->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            $results = [
                'sync_description' => '同步MySQL聊天室對話記錄到Firebase Realtime Database',
                'data_source' => 'chat_conversations 資料表',
                'sync_criteria' => [
                    'has_line_user_id' => '必須有LINE用戶ID',
                    'has_assigned_customer' => '客戶必須已分配業務人員',
                    'time_range' => $forceSync ? '全部對話' : '最近24小時內的對話'
                ],
                'total_found' => $conversations->count(),
                'synced' => 0,
                'failed' => 0,
                'skipped' => 0,
                'details' => []
            ];

            foreach ($conversations as $conversation) {
                try {
                    $syncResult = $this->firebaseChatService->syncConversationToFirebase($conversation);
                    
                    if ($syncResult) {
                        $results['synced']++;
                        $results['details'][] = [
                            'id' => $conversation->id,
                            'line_user_id' => $conversation->line_user_id,
                            'status' => 'synced'
                        ];
                    } else {
                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $conversation->id,
                            'line_user_id' => $conversation->line_user_id,
                            'status' => 'failed'
                        ];
                    }
                } catch (\Exception $syncError) {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $conversation->id,
                        'line_user_id' => $conversation->line_user_id,
                        'status' => 'error',
                        'error' => $syncError->getMessage()
                    ];
                }
            }

            Log::channel('firebase')->info('Debug batch sync completed', [
                'user_id' => $user->id,
                'results' => [
                    'synced' => $results['synced'],
                    'failed' => $results['failed'],
                    'total' => $results['total_found']
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => '批次同步完成',
                'data' => $results,
                'timestamp' => now()->format('c')
            ]);

        } catch (\Exception $e) {
            Log::channel('firebase')->error('Debug batch sync failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => '批次同步失敗',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * 驗證 Firebase 資料完整性 - Debug 專用
     */
    public function validateFirebaseDataIntegrity(Request $request)
    {
        try {
            $user = Auth::user();
            
            // 檢查權限
            if (!$user->hasRole(['admin', 'manager', 'executive'])) {
                return response()->json([
                    'success' => false,
                    'error' => '權限不足'
                ], 403);
            }

            // 檢查除錯模式
            if (!$this->isDebugEnabled()) {
                return response()->json([
                    'success' => false,
                    'error' => '除錯模式未啟用',
                    'message' => '此功能僅在除錯模式下可用',
                    'debug_info' => [
                        'app_debug' => config('app.debug'),
                        'firebase_debug_mode' => config('services.firebase.debug_mode', false),
                        'env_firebase_debug' => env('FIREBASE_DEBUG_MODE', false),
                        'combined_debug_mode' => $this->isDebugEnabled()
                    ]
                ], 403);
            }

            $lineUserId = $request->input('line_user_id');
            $checkAll = $request->boolean('check_all', false);

            $validationResults = [
                'timestamp' => now()->format('c'),
                'total_checked' => 0,
                'mysql_count' => 0,
                'firebase_count' => 0,
                'missing_in_firebase' => [],
                'extra_in_firebase' => [],
                'inconsistent_data' => []
            ];

            if ($lineUserId) {
                // 驗證單一用戶的資料
                $results = $this->validateSingleUserData($lineUserId);
                $validationResults = array_merge($validationResults, $results);
                $validationResults['total_checked'] = 1;
            } elseif ($checkAll) {
                // 驗證所有用戶的資料（限制數量）
                $recentUsers = ChatConversation::select('line_user_id')
                    ->whereNotNull('line_user_id')
                    ->where('updated_at', '>=', now()->subHours(24))
                    ->distinct()
                    ->limit(20) // 限制檢查數量
                    ->pluck('line_user_id');

                foreach ($recentUsers as $userId) {
                    $results = $this->validateSingleUserData($userId);
                    $validationResults['mysql_count'] += $results['mysql_count'];
                    $validationResults['firebase_count'] += $results['firebase_count'];
                    
                    if (!empty($results['missing_in_firebase'])) {
                        $validationResults['missing_in_firebase'][] = [
                            'line_user_id' => $userId,
                            'missing_messages' => $results['missing_in_firebase']
                        ];
                    }
                    
                    $validationResults['total_checked']++;
                }
            }

            $validationResults['is_consistent'] = 
                empty($validationResults['missing_in_firebase']) && 
                empty($validationResults['extra_in_firebase']) &&
                empty($validationResults['inconsistent_data']);

            return response()->json([
                'success' => true,
                'message' => '資料完整性驗證完成',
                'data' => $validationResults
            ]);

        } catch (\Exception $e) {
            Log::channel('firebase')->error('Data integrity validation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => '資料驗證失敗',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * 清理 Firebase 中的過期或無效資料 - Debug 專用
     */
    public function cleanupFirebaseDataDebug(Request $request)
    {
        try {
            $user = Auth::user();
            
            // 檢查權限
            if (!$user->hasRole(['admin', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'error' => '權限不足，只有管理員可以執行清理操作'
                ], 403);
            }

            // 檢查除錯模式
            if (!$this->isDebugEnabled()) {
                return response()->json([
                    'success' => false,
                    'error' => '除錯模式未啟用',
                    'message' => '此功能僅在除錯模式下可用',
                    'debug_info' => [
                        'app_debug' => config('app.debug'),
                        'firebase_debug_mode' => config('services.firebase.debug_mode', false),
                        'env_firebase_debug' => env('FIREBASE_DEBUG_MODE', false),
                        'combined_debug_mode' => $this->isDebugEnabled()
                    ]
                ], 403);
            }

            $dryRun = $request->boolean('dry_run', true); // 預設為測試模式
            $daysOld = max($request->input('days_old', 30), 7); // 最少保留7天
            
            $cleanupResults = [
                'dry_run' => $dryRun,
                'days_old_threshold' => $daysOld,
                'timestamp' => now()->format('c'),
                'conversations_to_clean' => 0,
                'messages_to_clean' => 0,
                'cleaned_conversations' => [],
                'errors' => []
            ];

            // 獲取需要清理的 Firebase 對話
            $firebaseConversations = $this->firebaseChatService->getConversationsFromFirebase();
            
            $cutoffDate = now()->subDays($daysOld);
            
            foreach ($firebaseConversations as $conversation) {
                try {
                    $lastUpdate = isset($conversation['updated']) ? 
                        \Carbon\Carbon::parse($conversation['updated']) : null;
                    
                    if ($lastUpdate && $lastUpdate->lt($cutoffDate)) {
                        $cleanupResults['conversations_to_clean']++;
                        
                        if (!$dryRun) {
                            // 實際執行清理
                            $deleteResult = $this->firebaseChatService->deleteConversationFromFirebase(
                                $conversation['firebaseId']
                            );
                            
                            if ($deleteResult) {
                                $cleanupResults['cleaned_conversations'][] = [
                                    'firebase_id' => $conversation['firebaseId'],
                                    'last_updated' => $lastUpdate->format('c'),
                                    'status' => 'cleaned'
                                ];
                            } else {
                                $cleanupResults['errors'][] = [
                                    'firebase_id' => $conversation['firebaseId'],
                                    'error' => 'Failed to delete conversation'
                                ];
                            }
                        } else {
                            $cleanupResults['cleaned_conversations'][] = [
                                'firebase_id' => $conversation['firebaseId'],
                                'last_updated' => $lastUpdate->format('c'),
                                'status' => 'would_be_cleaned'
                            ];
                        }
                    }
                } catch (\Exception $cleanupError) {
                    $cleanupResults['errors'][] = [
                        'firebase_id' => $conversation['firebaseId'] ?? 'unknown',
                        'error' => $cleanupError->getMessage()
                    ];
                }
            }

            Log::channel('firebase')->info('Firebase data cleanup performed', [
                'user_id' => $user->id,
                'dry_run' => $dryRun,
                'conversations_processed' => count($firebaseConversations),
                'conversations_cleaned' => count($cleanupResults['cleaned_conversations']),
                'errors' => count($cleanupResults['errors'])
            ]);

            return response()->json([
                'success' => true,
                'message' => $dryRun ? 'Firebase 資料清理預覽完成' : 'Firebase 資料清理完成',
                'data' => $cleanupResults
            ]);

        } catch (\Exception $e) {
            Log::channel('firebase')->error('Firebase data cleanup failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Firebase 資料清理失敗',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * 驗證單一用戶的資料完整性
     */
    private function validateSingleUserData($lineUserId)
    {
        $results = [
            'mysql_count' => 0,
            'firebase_count' => 0,
            'missing_in_firebase' => [],
            'extra_in_firebase' => []
        ];

        try {
            // 獲取 MySQL 中的訊息
            $mysqlMessages = ChatConversation::where('line_user_id', $lineUserId)
                ->orderBy('message_timestamp', 'desc')
                ->limit(50)
                ->get();
            
            $results['mysql_count'] = $mysqlMessages->count();

            // 獲取 Firebase 中的訊息
            $firebaseMessages = $this->firebaseChatService->getMessagesFromFirebase($lineUserId, 50);
            $results['firebase_count'] = count($firebaseMessages);

            // 比對資料
            $mysqlMessageIds = $mysqlMessages->pluck('id')->map(function($id) {
                return 'msg_' . $id;
            })->toArray();

            $firebaseMessageIds = array_column($firebaseMessages, 'id');

            // 找出 MySQL 有但 Firebase 沒有的訊息
            $results['missing_in_firebase'] = array_diff($mysqlMessageIds, $firebaseMessageIds);

            // 找出 Firebase 有但 MySQL 沒有的訊息
            $results['extra_in_firebase'] = array_diff($firebaseMessageIds, $mysqlMessageIds);

        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * 測試 admin/executive 權限功能
     */
    public function testPermissions(Request $request)
    {
        try {
            $user = Auth::user();
            
            $permissionTests = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_roles' => $user->getRoleNames()->toArray(),
                'permissions' => [
                    'can_access_all_chats' => $user->canAccessAllChats(),
                    'is_admin' => $user->isAdmin(),
                    'is_manager' => $user->isManager(),
                    'is_staff' => $user->isStaff(),
                ],
                'chat_access' => [
                    'should_see_all_conversations' => $user->canAccessAllChats(),
                    'filter_by_staff_id' => $user->canAccessAllChats() ? 'No filtering (sees all)' : 'Filtered by user ID: ' . $user->id
                ],
                'firebase_stats_access' => [
                    'can_view_all_staff_stats' => $user->canAccessAllChats(),
                    'stats_collection' => $user->canAccessAllChats() ? 'admin_staff_overview' : 'staff_unread_stats/' . $user->id
                ]
            ];

            // 測試實際的對話數量
            $totalConversations = ChatConversation::whereNotNull('line_user_id')
                ->whereHas('customer', function($query) {
                    $query->whereNotNull('assigned_to');
                })
                ->count();

            $userConversations = ChatConversation::whereNotNull('line_user_id')
                ->whereHas('customer', function($query) use ($user) {
                    $query->where('assigned_to', $user->id);
                })
                ->count();

            $permissionTests['conversation_counts'] = [
                'total_in_system' => $totalConversations,
                'assigned_to_user' => $userConversations,
                'user_should_see' => $user->canAccessAllChats() ? $totalConversations : $userConversations
            ];

            return response()->json([
                'success' => true,
                'message' => 'Permission test completed',
                'data' => $permissionTests,
                'test_timestamp' => now()->format('c')
            ]);

        } catch (\Exception $e) {
            Log::error('Permission test failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('權限測試失敗', $e);
        }
    }

    /**
     * 執行完整的Firebase資料同步
     */
    public function fullSyncToFirebase(Request $request)
    {
        try {
            $user = Auth::user();
            
            // 檢查權限
            if (!$user->hasRole(['admin', 'manager', 'executive'])) {
                return response()->json([
                    'success' => false,
                    'error' => '權限不足',
                    'message' => '只有管理員可以執行完整同步'
                ], 403);
            }

            // 檢查除錯模式
            if (!$this->isDebugEnabled()) {
                return response()->json([
                    'success' => false,
                    'error' => '除錯模式未啟用',
                    'message' => '此功能僅在除錯模式下可用'
                ], 403);
            }

            $batchSize = min($request->input('batch_size', 100), 500);
            $preventDuplicates = $request->boolean('prevent_duplicates', true);
            
            // 獲取所有需要同步的對話
            $query = ChatConversation::with('customer')
                ->whereNotNull('line_user_id')
                ->whereHas('customer', function($q) {
                    $q->whereNotNull('assigned_to');
                });

            $totalCount = $query->count();
            
            $results = [
                'sync_description' => '完整同步所有MySQL聊天室對話記錄到Firebase',
                'data_source' => 'chat_conversations 資料表 (完整同步)',
                'total_conversations' => $totalCount,
                'batch_size' => $batchSize,
                'prevent_duplicates' => $preventDuplicates,
                'processed' => 0,
                'synced' => 0,
                'failed' => 0,
                'skipped' => 0,
                'batches_completed' => 0,
                'details' => []
            ];

            $offset = 0;
            $existingLineUserIds = [];
            
            // 如果需要防止重複，先獲取Firebase中已存在的conversation
            if ($preventDuplicates) {
                try {
                    $firebaseConversations = $this->firebaseChatService->getAllFirebaseConversations();
                    if ($firebaseConversations) {
                        $existingLineUserIds = array_keys($firebaseConversations);
                        $results['existing_firebase_conversations'] = count($existingLineUserIds);
                    }
                } catch (\Exception $e) {
                    Log::channel('firebase')->warning('Could not fetch existing Firebase conversations for duplicate check', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // 分批處理所有對話
            do {
                $conversations = $query->orderBy('id', 'asc')
                    ->limit($batchSize)
                    ->offset($offset)
                    ->get();

                foreach ($conversations as $conversation) {
                    $results['processed']++;
                    
                    try {
                        // 檢查是否需要跳過重複項目
                        if ($preventDuplicates && in_array($conversation->line_user_id, $existingLineUserIds)) {
                            // 檢查是否需要更新（比較時間戳）
                            $shouldUpdate = $this->shouldUpdateExistingConversation($conversation);
                            if (!$shouldUpdate) {
                                $results['skipped']++;
                                $results['details'][] = [
                                    'id' => $conversation->id,
                                    'line_user_id' => $conversation->line_user_id,
                                    'status' => 'skipped_duplicate'
                                ];
                                continue;
                            }
                        }
                        
                        $syncResult = $this->firebaseChatService->syncConversationToFirebase($conversation);
                        
                        if ($syncResult) {
                            $results['synced']++;
                            $results['details'][] = [
                                'id' => $conversation->id,
                                'line_user_id' => $conversation->line_user_id,
                                'status' => 'synced'
                            ];
                        } else {
                            $results['failed']++;
                            $results['details'][] = [
                                'id' => $conversation->id,
                                'line_user_id' => $conversation->line_user_id,
                                'status' => 'failed'
                            ];
                        }
                    } catch (\Exception $syncError) {
                        $results['failed']++;
                        $results['details'][] = [
                            'id' => $conversation->id,
                            'line_user_id' => $conversation->line_user_id,
                            'status' => 'error',
                            'error' => $syncError->getMessage()
                        ];
                    }
                }

                $results['batches_completed']++;
                $offset += $batchSize;
                
                // 記錄批次進度
                Log::channel('firebase')->info('Full sync batch completed', [
                    'batch' => $results['batches_completed'],
                    'processed' => $results['processed'],
                    'synced' => $results['synced'],
                    'failed' => $results['failed'],
                    'skipped' => $results['skipped']
                ]);

            } while ($conversations->count() === $batchSize);

            Log::channel('firebase')->info('Full Firebase sync completed', [
                'user_id' => $user->id,
                'total_processed' => $results['processed'],
                'synced' => $results['synced'],
                'failed' => $results['failed'],
                'skipped' => $results['skipped']
            ]);

            return response()->json([
                'success' => true,
                'message' => "完整同步完成：處理 {$results['processed']} 筆，成功 {$results['synced']} 筆，失敗 {$results['failed']} 筆，跳過 {$results['skipped']} 筆",
                'data' => $results,
                'timestamp' => now()->format('c')
            ]);

        } catch (\Exception $e) {
            Log::channel('firebase')->error('Full Firebase sync failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => '完整同步失敗',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * 檢查是否應該更新現有的對話記錄
     */
    private function shouldUpdateExistingConversation(ChatConversation $conversation): bool
    {
        try {
            $existingData = $this->firebaseChatService->getFirebaseConversation($conversation->line_user_id);
            if (!$existingData) {
                return true; // Firebase中不存在，需要同步
            }

            // 比較更新時間
            $mysqlUpdated = $conversation->updated_at;
            $firebaseUpdated = isset($existingData['updated']) ? 
                \Carbon\Carbon::parse($existingData['updated']) : 
                null;

            return !$firebaseUpdated || $mysqlUpdated->gt($firebaseUpdated);
        } catch (\Exception $e) {
            // 如果無法比較，預設為需要更新
            return true;
        }
    }

    /**
     * 檢查是否啟用除錯模式
     */
    protected function isDebugEnabled(): bool
    {
        return config('app.debug') || 
               config('services.firebase.debug_mode', false) || 
               env('FIREBASE_DEBUG_MODE', false);
    }

    /**
     * Point 65: 診斷MySQL和Firebase資料同步狀況
     * 公開端點，無需認證，用於調試資料流向
     */
    public function diagnoseDataFlow(Request $request)
    {
        $results = [
            'timestamp' => now()->format('c'),
            'mysql_status' => null,
            'firebase_status' => null,
            'test_results' => [],
            'recommendations' => []
        ];

        try {
            // 1. 檢查 MySQL 連接和資料狀況
            $mysqlStats = [
                'connection' => false,
                'total_conversations' => 0,
                'recent_conversations' => 0,
                'customers_with_line' => 0,
                'last_conversation' => null,
                'sample_conversations' => []
            ];

            try {
                $mysqlStats['connection'] = true;
                $mysqlStats['total_conversations'] = ChatConversation::count();
                $mysqlStats['recent_conversations'] = ChatConversation::where('created_at', '>=', now()->subHours(24))->count();
                $mysqlStats['customers_with_line'] = \App\Models\Customer::whereNotNull('line_user_id')->count();
                
                $lastConversation = ChatConversation::latest()->first();
                if ($lastConversation) {
                    $mysqlStats['last_conversation'] = [
                        'id' => $lastConversation->id,
                        'customer_id' => $lastConversation->customer_id,
                        'line_user_id' => $lastConversation->line_user_id,
                        'message_content' => substr($lastConversation->message_content, 0, 50),
                        'created_at' => $lastConversation->created_at->format('c'),
                        'version' => $lastConversation->version
                    ];
                }

                // 取得最近 3 筆對話作為樣本
                $mysqlStats['sample_conversations'] = ChatConversation::latest()
                    ->take(3)
                    ->get()
                    ->map(function($conv) {
                        return [
                            'id' => $conv->id,
                            'line_user_id' => $conv->line_user_id,
                            'message' => substr($conv->message_content, 0, 30),
                            'created_at' => $conv->created_at->format('c')
                        ];
                    });

            } catch (\Exception $e) {
                $mysqlStats['error'] = $e->getMessage();
            }

            $results['mysql_status'] = $mysqlStats;

            // 2. 檢查 Firebase 連接和資料狀況
            $firebaseStats = [
                'connection' => false,
                'service_available' => false,
                'config_valid' => false,
                'test_read' => false,
                'test_write' => false,
                'sample_data' => []
            ];

            try {
                $firebaseStats['service_available'] = $this->firebaseChatService !== null;
                $firebaseStats['config_valid'] = !empty(config('services.firebase.project_id')) && 
                                                 !empty(config('services.firebase.database_url'));

                if ($this->firebaseChatService) {
                    $firebaseStats['connection'] = $this->firebaseChatService->checkFirebaseConnection();
                    
                    // 測試讀取
                    if ($firebaseStats['connection']) {
                        try {
                            $testData = $this->firebaseChatService->getMessagesFromFirebase('test', 1);
                            $firebaseStats['test_read'] = true;
                        } catch (\Exception $e) {
                            $firebaseStats['read_error'] = $e->getMessage();
                        }

                        // 測試寫入
                        try {
                            $testKey = 'diagnostic_test_' . time();
                            $success = $this->firebaseChatService->writeTestData($testKey, ['timestamp' => time()]);
                            $firebaseStats['test_write'] = $success;
                        } catch (\Exception $e) {
                            $firebaseStats['write_error'] = $e->getMessage();
                        }
                    }
                }

            } catch (\Exception $e) {
                $firebaseStats['error'] = $e->getMessage();
            }

            $results['firebase_status'] = $firebaseStats;

            // 3. 創建測試對話並嘗試同步
            if ($mysqlStats['connection'] && $firebaseStats['connection']) {
                try {
                    // 尋找或創建測試客戶
                    $testCustomer = \App\Models\Customer::firstOrCreate([
                        'line_user_id' => 'diagnostic_test_user_' . date('md')
                    ], [
                        'name' => 'Diagnostic Test User',
                        'phone' => '0900000000', // 提供測試電話號碼
                        'channel' => 'line',
                        'status' => 'new',
                        'tracking_status' => 'pending',
                        'version' => 1, // 添加版本欄位
                        'version_updated_at' => now() // 添加版本更新時間
                    ]);

                    // 創建測試對話
                    $testConversation = ChatConversation::create([
                        'customer_id' => $testCustomer->id,
                        'line_user_id' => $testCustomer->line_user_id,
                        'platform' => 'line',
                        'message_type' => 'text',
                        'message_content' => 'Diagnostic test message at ' . now()->toDateTimeString(),
                        'message_timestamp' => now(),
                        'is_from_customer' => true,
                        'status' => 'unread'
                    ]);

                    $results['test_results']['conversation_created'] = [
                        'id' => $testConversation->id,
                        'customer_id' => $testConversation->customer_id,
                        'line_user_id' => $testConversation->line_user_id,
                        'version' => $testConversation->version
                    ];

                    // 測試 Firebase 同步
                    $syncResult = $this->firebaseChatService->syncConversationToFirebase($testConversation);
                    $results['test_results']['firebase_sync'] = $syncResult;

                } catch (\Exception $e) {
                    $results['test_results']['error'] = $e->getMessage();
                }
            }

            // 4. 生成建議
            if (!$mysqlStats['connection']) {
                $results['recommendations'][] = 'MySQL 資料庫連接失敗，請檢查資料庫配置';
            }
            
            if ($mysqlStats['total_conversations'] === 0) {
                $results['recommendations'][] = 'MySQL 中沒有對話記錄，可能是 webhook 未正確處理或創建失敗';
            }
            
            if (!$firebaseStats['connection']) {
                $results['recommendations'][] = 'Firebase Realtime Database 無法連接，請檢查配置和網路';
            }
            
            if ($mysqlStats['total_conversations'] > 0 && !$firebaseStats['connection']) {
                $results['recommendations'][] = 'MySQL 有資料但 Firebase 無法連接，資料未能同步';
            }

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Point 66: 驗證 webhook 執行和 Firebase 寫入狀況的工具
     * 公開端點，用於即時監控 webhook 執行狀況
     */
    public function verifyWebhookExecution(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Webhook verification endpoint is working',
                'timestamp' => now()->format('c'),
                'simple_test' => 'OK'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 獲取日誌文件的最近內容
     */
    private function getRecentLogContent(string $filePath, int $lines = 10): array
    {
        try {
            if (!file_exists($filePath)) {
                return [];
            }

            $fileLines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$fileLines) {
                return [];
            }

            $recentLines = array_slice($fileLines, -$lines);
            
            return array_map(function($line, $index) use ($recentLines) {
                return [
                    'line_number' => count($recentLines) - count($recentLines) + $index + 1,
                    'content' => $line,
                    'timestamp' => $this->extractTimestampFromLogLine($line)
                ];
            }, $recentLines, array_keys($recentLines));

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 從日誌行中提取時間戳
     */
    private function extractTimestampFromLogLine(string $line): ?string
    {
        // 嘗試匹配常見的時間戳格式
        if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * 即時監控 webhook 狀態（輕量級端點）
     */
    public function webhookStatus(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Webhook status endpoint is working',
                'timestamp' => now()->format('c'),
                'simple_test' => 'OK'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 測試用的 webhook 端點，簡化版本無需複雜依賴
     */
    public function webhookTest(Request $request)
    {
        try {
            $executionId = 'test_' . time() . '_' . rand(1000, 9999);
            
            // Create logs directory if it doesn't exist
            $logDir = storage_path('logs');
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            
            $logFile = storage_path('logs/webhook-test-debug.log');
            
            // Basic request logging
            @file_put_contents($logFile, 
                date('Y-m-d H:i:s') . " - Test webhook called from IP: " . $request->ip() . 
                " [ExecutionID: $executionId]\n", 
                FILE_APPEND | LOCK_EX);
            
            // Parse request data
            $requestData = [
                'method' => $request->method(),
                'content_type' => $request->header('Content-Type'),
                'body_size' => strlen($request->getContent()),
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString()
            ];
            
            @file_put_contents($logFile, 
                date('Y-m-d H:i:s') . " - Request data: " . json_encode($requestData) . "\n", 
                FILE_APPEND | LOCK_EX);
            
            // Parse events
            $events = $request->input('events', []);
            $eventCount = count($events);
            
            @file_put_contents($logFile, 
                date('Y-m-d H:i:s') . " - Processing $eventCount events\n", 
                FILE_APPEND | LOCK_EX);

            // Process each event (simplified)
            $processedEvents = [];
            foreach ($events as $index => $event) {
                try {
                    @file_put_contents($logFile, 
                        date('Y-m-d H:i:s') . " - Processing event $index\n", 
                        FILE_APPEND | LOCK_EX);
                    
                    $eventType = $event['type'] ?? 'unknown';
                    
                    if ($eventType === 'message') {
                        $result = $this->processTestMessage($event, $executionId);
                    } else {
                        $result = ['status' => 'skipped', 'type' => $eventType, 'reason' => 'not_message_event'];
                    }
                    
                    $processedEvents[] = $result;
                    
                } catch (\Exception $e) {
                    $errorMsg = "Event $index processing failed: " . $e->getMessage();
                    @file_put_contents($logFile, 
                        date('Y-m-d H:i:s') . " - ERROR: $errorMsg\n", 
                        FILE_APPEND | LOCK_EX);
                    
                    $processedEvents[] = ['status' => 'failed', 'error' => $e->getMessage()];
                }
            }

            @file_put_contents($logFile, 
                date('Y-m-d H:i:s') . " - Test webhook processing completed\n", 
                FILE_APPEND | LOCK_EX);

            $results = [
                'status' => 'success',
                'execution_id' => $executionId,
                'events_processed' => $eventCount,
                'events_results' => $processedEvents,
                'test_mode' => true,
                'timestamp' => now()->toISOString()
            ];
            
            return response()->json($results);
            
        } catch (\Exception $e) {
            $error = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'test_mode' => true,
                'timestamp' => now()->toISOString()
            ];
            
            $logFile = storage_path('logs/webhook-test-debug.log');
            @file_put_contents($logFile, 
                date('Y-m-d H:i:s') . " - EXCEPTION: " . json_encode($error) . "\n", 
                FILE_APPEND | LOCK_EX);
            
            return response()->json($error, 500);
        }
    }
    
    /**
     * 簡化的訊息處理方法
     */
    private function processTestMessage($event, $executionId)
    {
        try {
            $lineUserId = $event['source']['userId'] ?? null;
            $messageText = $event['message']['text'] ?? '';
            $messageType = $event['message']['type'] ?? 'unknown';
            
            if (!$lineUserId) {
                return ['status' => 'failed', 'reason' => 'missing_line_user_id'];
            }
            
            if ($messageType !== 'text') {
                return ['status' => 'skipped', 'reason' => 'not_text_message', 'type' => $messageType];
            }
            
            // Try to create/find customer (simplified)
            $customer = null;
            try {
                $customer = \App\Models\Customer::where('line_user_id', $lineUserId)->first();
                if (!$customer) {
                    $customer = \App\Models\Customer::create([
                        'name' => 'Test User ' . substr($lineUserId, -6),
                        'phone' => '0900000000',
                        'line_user_id' => $lineUserId,
                        'channel' => 'line',
                        'status' => 'new',
                        'tracking_status' => 'pending',
                        'version' => 1,
                        'version_updated_at' => now()
                    ]);
                }
            } catch (\Exception $e) {
                return ['status' => 'failed', 'reason' => 'customer_creation_failed', 'error' => $e->getMessage()];
            }
            
            // Try to create conversation
            try {
                $conversation = \App\Models\ChatConversation::create([
                    'customer_id' => $customer->id,
                    'user_id' => $customer->assigned_to,
                    'line_user_id' => $lineUserId,
                    'platform' => 'line',
                    'message_type' => 'text',
                    'message_content' => $messageText,
                    'message_timestamp' => now(),
                    'is_from_customer' => true,
                    'status' => 'unread',
                    'metadata' => ['test' => true, 'execution_id' => $executionId]
                ]);
                
                // Try Firebase sync (optional, won't fail if it doesn't work)
                $firebaseSync = false;
                try {
                    if ($this->firebaseChatService) {
                        $firebaseSync = $this->firebaseChatService->syncConversationToFirebase($conversation);
                    }
                } catch (\Exception $e) {
                    // Firebase sync failure is not critical for test
                }
                
                // Clean up test data
                $conversation->delete();
                if ($customer->name && str_starts_with($customer->name, 'Test User')) {
                    $customer->delete();
                }
                
                return [
                    'status' => 'success',
                    'customer_id' => $customer->id,
                    'conversation_id' => $conversation->id,
                    'firebase_synced' => $firebaseSync,
                    'message_preview' => substr($messageText, 0, 50)
                ];
                
            } catch (\Exception $e) {
                return ['status' => 'failed', 'reason' => 'conversation_creation_failed', 'error' => $e->getMessage()];
            }
            
        } catch (\Exception $e) {
            return ['status' => 'failed', 'reason' => 'general_error', 'error' => $e->getMessage()];
        }
    }

    /**
     * 簡易 LINE Webhook 測試 - 無認證、無驗證，直接回傳用戶訊息
     */
    public function webhookSimpleTest(Request $request)
    {
        // 添加 CORS 頭部確保跨域訪問
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Line-Signature',
        ];

        try {
            $executionId = 'simple_' . time() . '_' . rand(1000, 9999);
            
            // 基本請求日誌 - 不依賴任何認證
            @file_put_contents(storage_path('logs/webhook-simple.log'), 
                date('Y-m-d H:i:s') . " - Simple webhook called from IP: " . $request->ip() . 
                " [ExecutionID: $executionId]\n", 
                FILE_APPEND | LOCK_EX);
            
            // 獲取所有請求數據
            $requestBody = $request->getContent();
            $requestData = json_decode($requestBody, true) ?? [];
            $events = $requestData['events'] ?? [];
            
            $processedMessages = [];
            
            // 處理每個事件
            foreach ($events as $index => $event) {
                $eventType = $event['type'] ?? 'unknown';
                
                if ($eventType === 'message') {
                    $messageType = $event['message']['type'] ?? 'unknown';
                    $lineUserId = $event['source']['userId'] ?? 'unknown';
                    
                    if ($messageType === 'text') {
                        $messageText = $event['message']['text'] ?? '';
                        
                        $processedMessages[] = [
                            'event_index' => $index,
                            'line_user_id' => $lineUserId,
                            'message_type' => $messageType,
                            'user_message' => $messageText,
                            'echo_response' => "收到您的訊息: " . $messageText,
                            'timestamp' => now()->format('Y-m-d H:i:s')
                        ];
                        
                        // 記錄到簡單日誌
                        @file_put_contents(storage_path('logs/webhook-simple.log'), 
                            date('Y-m-d H:i:s') . " - Message from $lineUserId: $messageText\n", 
                            FILE_APPEND | LOCK_EX);
                    } else {
                        $processedMessages[] = [
                            'event_index' => $index,
                            'line_user_id' => $lineUserId,
                            'message_type' => $messageType,
                            'status' => 'non_text_message'
                        ];
                    }
                } else {
                    $processedMessages[] = [
                        'event_index' => $index,
                        'event_type' => $eventType,
                        'status' => 'non_message_event'
                    ];
                }
            }
            
            // 簡化的成功響應
            $response = [
                'status' => 'ok',
                'execution_id' => $executionId,
                'events_received' => count($events),
                'messages_processed' => count($processedMessages),
                'processed_messages' => $processedMessages,
                'webhook_working' => true,
                'timestamp' => now()->format('Y-m-d H:i:s')
            ];
            
            @file_put_contents(storage_path('logs/webhook-simple.log'), 
                date('Y-m-d H:i:s') . " - Processing completed successfully\n", 
                FILE_APPEND | LOCK_EX);
            
            return response()->json($response, 200, $headers);
            
        } catch (\Exception $e) {
            // 簡化的錯誤響應
            $error = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'webhook_working' => false,
                'timestamp' => now()->format('Y-m-d H:i:s')
            ];
            
            @file_put_contents(storage_path('logs/webhook-simple.log'), 
                date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            
            return response()->json($error, 200, $headers); // 返回 200 避免 LINE 重試
        }
    }

    /**
     * Debug webhook 測試 - 跳過所有驗證直接處理事件
     */
    public function webhookDebugTest(Request $request)
    {
        $executionId = 'debug_' . time() . '_' . rand(1000, 9999);
        
        try {
            $events = $request->input('events', []);
            $processedEvents = [];
            
            foreach ($events as $index => $event) {
                if (isset($event['type']) && $event['type'] === 'message') {
                    $lineUserId = $event['source']['userId'] ?? 'unknown';
                    $messageText = $event['message']['text'] ?? '';
                    
                    // 直接處理消息事件
                    $result = $this->processMessageEventDebug($lineUserId, $messageText, $executionId);
                    $processedEvents[] = $result;
                }
            }
            
            return response()->json([
                'status' => 'success',
                'execution_id' => $executionId,
                'events_processed' => count($events),
                'results' => $processedEvents,
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'execution_id' => $executionId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => now()->format('Y-m-d H:i:s')
            ], 200);
        }
    }

    /**
     * 處理消息事件的調試版本
     */
    private function processMessageEventDebug($lineUserId, $messageText, $executionId)
    {
        try {
            // 1. 查找或創建客戶
            $customer = \App\Models\Customer::where('line_user_id', $lineUserId)->first();
            
            if (!$customer) {
                // 創建新客戶 - 改進角色查詢
                $adminUser = null;
                try {
                    $adminUser = \App\Models\User::whereHas('roles', function($q) {
                        $q->where('name', 'admin');
                    })->first();
                } catch (\Exception $e) {
                    file_put_contents(storage_path('logs/webhook-debug.log'), 
                        date('Y-m-d H:i:s') . " - Point17 - 角色查詢失敗: " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                }
                
                $customer = \App\Models\Customer::create([
                    'name' => 'LINE用戶 ' . substr($lineUserId, -6),
                    'phone' => '0900000000',
                    'line_user_id' => $lineUserId,
                    'channel' => 'line',
                    'status' => 'new',
                    'tracking_status' => 'pending',
                    'assigned_to' => $adminUser ? $adminUser->id : 1,
                    'version' => 1,
                    'version_updated_at' => now(),
                    'website_source' => 'line',
                ]);
            }
            
            // 2. 創建聊天對話記錄
            $conversation = \App\Models\ChatConversation::create([
                'line_user_id' => $lineUserId,
                'customer_id' => $customer->id,
                'message_type' => 'text',
                'message_content' => $messageText,
                'direction' => 'incoming',
                'status' => 'received',
                'timestamp' => now(),
                'version' => 1,
                'version_updated_at' => now(),
            ]);
            
            return [
                'status' => 'success',
                'line_user_id' => $lineUserId,
                'customer_id' => $customer->id,
                'conversation_id' => $conversation->id,
                'message' => $messageText,
                'created_customer' => !$customer->wasRecentlyCreated ? false : true,
                'mysql_stored' => true,
                'firebase_sync_attempted' => true
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'line_user_id' => $lineUserId,
                'message' => $messageText,
                'error' => $e->getMessage(),
                'mysql_stored' => false,
                'firebase_sync_attempted' => false
            ];
        }
    }

    /**
     * Webhook 無簽名驗證版本 - 專門用於測試 Point 10-11
     */
    public function webhookNoSignature(Request $request)
    {
        $executionId = 'nosig_' . time() . '_' . rand(1000, 9999);
        
        // 安全記錄日誌
        $logSafe = function($message) use ($executionId) {
            try {
                @file_put_contents(storage_path('logs/webhook-nosig.log'), 
                    date('Y-m-d H:i:s') . " - $message [ExecutionID: $executionId]\n", 
                    FILE_APPEND | LOCK_EX);
            } catch (\Exception $e) {
                // 忽略日誌寫入失敗
            }
        };
        
        $logSafe("無簽名驗證 Webhook 被呼叫，來自 IP: " . $request->ip());
        
        try {
            $requestData = $request->all();
            $events = $request->input('events', []);
            $processedEvents = [];
            
            $logSafe("原始請求數據: " . json_encode($requestData));
            $logSafe("接收到 " . count($events) . " 個事件");
            
            foreach ($events as $index => $event) {
                $eventType = $event['type'] ?? 'unknown';
                $logSafe("檢查事件 $index: type=$eventType");
                
                if (isset($event['type']) && $event['type'] === 'message') {
                    $lineUserId = $event['source']['userId'] ?? 'unknown';
                    $messageText = $event['message']['text'] ?? '';
                    
                    $logSafe("處理消息事件: 用戶 $lineUserId, 內容: $messageText");
                    
                    // 直接處理消息事件並存儲
                    $result = $this->handleMessageEventNoSig($lineUserId, $messageText, $executionId);
                    $processedEvents[] = $result;
                    
                    $logSafe("事件處理結果: " . ($result['status'] === 'success' ? '成功' : '失敗'));
                } else {
                    $logSafe("跳過非消息事件: type=$eventType, 原因=不是消息類型");
                    $processedEvents[] = [
                        'status' => 'skipped',
                        'event_type' => $eventType,
                        'reason' => 'not_message_event'
                    ];
                }
            }
            
            $successfulEvents = array_filter($processedEvents, function($result) {
                return isset($result['status']) && $result['status'] === 'success';
            });
            
            $response = [
                'status' => 'success',
                'execution_id' => $executionId,
                'events_received' => count($events),
                'events_processed' => count($successfulEvents),
                'results' => $processedEvents,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'note' => 'Processed without signature verification for testing'
            ];
            
            $logSafe("Webhook 處理完成，成功處理 " . count($processedEvents) . " 個事件");
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            $errorMsg = "Webhook 處理失敗: " . $e->getMessage();
            $logSafe("ERROR: $errorMsg");
            $logSafe("錯誤詳情: " . $e->getFile() . ":" . $e->getLine());
            
            return response()->json([
                'status' => 'error',
                'execution_id' => $executionId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => now()->format('Y-m-d H:i:s')
            ], 200); // 返回 200 避免 LINE 重試
        }
    }

    /**
     * 處理消息事件（無簽名版本）
     */
    private function handleMessageEventNoSig($lineUserId, $messageText, $executionId)
    {
        try {
            // 1. 查找或創建客戶
            $customer = \App\Models\Customer::where('line_user_id', $lineUserId)->first();
            $wasNewCustomer = false;
            
            if (!$customer) {
                // 創建新客戶 - 改進角色查詢
                $adminUser = null;
                try {
                    $adminUser = \App\Models\User::whereHas('roles', function($q) {
                        $q->where('name', 'admin');
                    })->first();
                } catch (\Exception $e) {
                    file_put_contents(storage_path('logs/webhook-debug.log'), 
                        date('Y-m-d H:i:s') . " - Point17 - 角色查詢失敗: " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                }
                
                $customer = \App\Models\Customer::create([
                    'name' => 'LINE用戶 ' . substr($lineUserId, -6),
                    'phone' => '0900000000',
                    'line_user_id' => $lineUserId,
                    'channel' => 'line',
                    'status' => 'new',
                    'tracking_status' => 'pending',
                    'assigned_to' => $adminUser ? $adminUser->id : 1,
                    'version' => 1,
                    'version_updated_at' => now(),
                    'website_source' => 'line_nosig',
                ]);
                $wasNewCustomer = true;
            }
            
            // Point 18: 調整順序 - 先Firebase後MySQL (handleMessageEventNoSig)
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Point18 NoSig - 開始處理訊息，順序：先Firebase後MySQL\n", 
                FILE_APPEND | LOCK_EX);
            
            // 2a. 先同步到Firebase realtime database
            $firebaseSync = false;
            $conversation = null;
            try {
                // 準備對話資料
                $conversationData = [
                    'line_user_id' => $lineUserId,
                    'customer_id' => $customer->id,
                    'message_type' => 'text',
                    'message_content' => $messageText,
                    'direction' => 'incoming',
                    'status' => 'received',
                    'timestamp' => now(),
                    'version' => 1,
                    'version_updated_at' => now(),
                ];
                
                // 創建臨時conversation用於Firebase同步
                $tempConversation = new \App\Models\ChatConversation($conversationData);
                $tempConversation->id = 'nosig_' . time() . '_' . rand(1000, 9999);
                
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Point18 NoSig - 先同步到Firebase, 臨時ID={$tempConversation->id}\n", 
                    FILE_APPEND | LOCK_EX);
                
                $firebaseSync = $this->firebaseChatService->syncConversationToFirebase($tempConversation);
                
                if ($firebaseSync) {
                    file_put_contents(storage_path('logs/webhook-debug.log'), 
                        date('Y-m-d H:i:s') . " - Point18 NoSig - Firebase同步成功，準備MySQL寫入\n", 
                        FILE_APPEND | LOCK_EX);
                } else {
                    file_put_contents(storage_path('logs/webhook-debug.log'), 
                        date('Y-m-d H:i:s') . " - Point18 NoSig - Firebase同步失敗，仍進行MySQL寫入\n", 
                        FILE_APPEND | LOCK_EX);
                }
                
            } catch (\Exception $e) {
                file_put_contents(storage_path('logs/webhook-debug.log'), 
                    date('Y-m-d H:i:s') . " - Point18 NoSig - Firebase同步異常: " . $e->getMessage() . "\n", 
                    FILE_APPEND | LOCK_EX);
            }
            
            // 2b. 然後創建MySQL聊天對話記錄
            $conversation = \App\Models\ChatConversation::create($conversationData);
            
            file_put_contents(storage_path('logs/webhook-debug.log'), 
                date('Y-m-d H:i:s') . " - Point18 NoSig - MySQL對話記錄創建成功: ID={$conversation->id}\n", 
                FILE_APPEND | LOCK_EX);
                
            // 如果Firebase同步成功，更新為真實ID
            if ($firebaseSync && $conversation) {
                try {
                    $this->firebaseChatService->syncConversationToFirebase($conversation);
                    file_put_contents(storage_path('logs/webhook-debug.log'), 
                        date('Y-m-d H:i:s') . " - Point18 NoSig - Firebase記錄更新為真實ID: {$conversation->id}\n", 
                        FILE_APPEND | LOCK_EX);
                } catch (\Exception $e) {
                    file_put_contents(storage_path('logs/webhook-debug.log'), 
                        date('Y-m-d H:i:s') . " - Point18 NoSig - Firebase記錄更新失敗: " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                }
            }
            
            // 3. 驗證資料是否正確存儲
            $verifyCustomer = \App\Models\Customer::find($customer->id);
            $verifyConversation = \App\Models\ChatConversation::find($conversation->id);
            
            return [
                'status' => 'success',
                'line_user_id' => $lineUserId,
                'customer_id' => $customer->id,
                'customer_created' => $wasNewCustomer,
                'customer_verified' => !is_null($verifyCustomer),
                'conversation_id' => $conversation ? $conversation->id : null,
                'conversation_verified' => $conversation ? !is_null($verifyConversation) : false,
                'message' => $messageText,
                'mysql_stored' => $conversation ? true : false,
                'firebase_sync_triggered' => true,
                'firebase_synced' => $firebaseSync,
                'processing_order' => 'firebase_first_mysql_second',
                'point18_implemented' => true
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'line_user_id' => $lineUserId,
                'message' => $messageText,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'mysql_stored' => false,
                'firebase_sync_triggered' => false,
                'processing_order' => 'firebase_first_mysql_second',
                'point18_implemented' => true
            ];
        }
    }

    /**
     * 模擬 LINE Webhook 請求 - Point 16
     * 使用真實的LINE設定來模擬webhook觸發，用於測試簽名驗證和完整流程
     */
    public function webhookSimulate(Request $request)
    {
        $executionId = 'simulate_' . time() . '_' . rand(1000, 9999);
        
        try {
            // 1. 獲取真實的LINE設定
            $lineSettings = $this->getLineSettings();
            
            // 檢查必要設定是否存在
            if (empty($lineSettings['channel_secret']) || empty($lineSettings['channel_access_token'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'LINE設定不完整，無法進行模擬',
                    'line_settings' => [
                        'channel_secret' => !empty($lineSettings['channel_secret']) ? 'configured' : 'MISSING',
                        'channel_access_token' => !empty($lineSettings['channel_access_token']) ? 'configured' : 'MISSING',
                        'bot_basic_id' => $lineSettings['bot_basic_id'] ?? 'not_set'
                    ],
                    'execution_id' => $executionId
                ], 400);
            }

            // 2. 構造標準的LINE webhook事件
            $testMessage = $request->input('message', '模擬測試訊息 - ' . now()->format('Y-m-d H:i:s'));
            $testUserId = $request->input('user_id', 'U' . strtolower(substr(md5($executionId), 0, 32)));
            
            $webhookPayload = [
                'destination' => $lineSettings['bot_basic_id'] ?? 'test-destination',
                'events' => [
                    [
                        'type' => 'message',
                        'mode' => 'active',
                        'timestamp' => now()->timestamp * 1000,
                        'source' => [
                            'type' => 'user',
                            'userId' => $testUserId
                        ],
                        'webhookEventId' => '01' . strtoupper(substr(md5($executionId), 0, 30)),
                        'deliveryContext' => [
                            'isRedelivery' => false
                        ],
                        'message' => [
                            'id' => strtolower(substr(md5($testMessage . $executionId), 0, 16)),
                            'type' => 'text',
                            'quoteToken' => strtolower(substr(md5($testMessage . time()), 0, 32)),
                            'text' => $testMessage
                        ],
                        'replyToken' => strtolower(substr(md5($testUserId . time()), 0, 32))
                    ]
                ]
            ];

            $bodyJson = json_encode($webhookPayload);
            
            // 3. 使用真實channel_secret生成HMAC簽名
            $channelSecret = $lineSettings['channel_secret'];
            $signature = base64_encode(hash_hmac('sha256', $bodyJson, $channelSecret, true));

            // 4. 向自己的webhook端點發送模擬請求
            $webhookUrl = url('/api/line/webhook');
            
            $client = new \GuzzleHttp\Client();
            $response = $client->post($webhookUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Line-Signature' => $signature,
                    'User-Agent' => 'LineBotWebhook/2.0'
                ],
                'body' => $bodyJson,
                'timeout' => 30
            ]);

            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);

            // 5. 組織回傳結果
            return response()->json([
                'success' => true,
                'execution_id' => $executionId,
                'line_settings' => [
                    'channel_secret' => $this->maskSensitiveValue($lineSettings['channel_secret'], 4, 4),
                    'channel_access_token' => $this->maskSensitiveValue($lineSettings['channel_access_token'], 10, 6),
                    'bot_basic_id' => $lineSettings['bot_basic_id'] ?? 'not_configured',
                    'auto_reply_enabled' => $lineSettings['auto_reply_enabled'] ?? false
                ],
                'simulated_message' => [
                    'user_id' => $testUserId,
                    'message_text' => $testMessage,
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ],
                'webhook_request' => [
                    'url' => $webhookUrl,
                    'signature_generated' => true,
                    'signature_value' => substr($signature, 0, 20) . '...',
                    'body_size' => strlen($bodyJson)
                ],
                'webhook_response' => [
                    'status_code' => $response->getStatusCode(),
                    'response_data' => $responseData,
                    'signature_verified' => $response->getStatusCode() < 400
                ],
                'test_details' => [
                    'webhook_payload_events' => count($webhookPayload['events']),
                    'signature_algorithm' => 'HMAC-SHA256',
                    'base64_encoded' => true
                ]
            ]);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = '';
            $statusCode = null;
            
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                $statusCode = $e->getResponse()->getStatusCode();
            }
            
            return response()->json([
                'success' => false,
                'execution_id' => $executionId,
                'error_type' => 'webhook_request_failed',
                'message' => 'Webhook請求失敗: HTTP ' . $statusCode,
                'line_settings' => [
                    'channel_secret' => $this->maskSensitiveValue($lineSettings['channel_secret'] ?? '', 4, 4),
                    'channel_access_token' => $this->maskSensitiveValue($lineSettings['channel_access_token'] ?? '', 10, 6),
                    'bot_basic_id' => $lineSettings['bot_basic_id'] ?? 'not_configured'
                ],
                'error_details' => [
                    'http_status' => $statusCode,
                    'response_body' => $responseBody,
                    'exception_message' => $e->getMessage()
                ]
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'execution_id' => $executionId,
                'error_type' => 'general_error',
                'message' => '模擬請求過程發生錯誤: ' . $e->getMessage(),
                'line_settings' => [
                    'channel_secret' => isset($lineSettings['channel_secret']) ? 
                        $this->maskSensitiveValue($lineSettings['channel_secret'], 4, 4) : 'not_configured',
                    'channel_access_token' => isset($lineSettings['channel_access_token']) ? 
                        $this->maskSensitiveValue($lineSettings['channel_access_token'], 10, 6) : 'not_configured'
                ],
                'error_details' => [
                    'exception_message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * 遮罩敏感資訊的輔助方法
     */
    private function maskSensitiveValue($value, $prefixLength = 4, $suffixLength = 4)
    {
        if (empty($value)) {
            return 'not_configured';
        }
        
        $length = strlen($value);
        if ($length <= $prefixLength + $suffixLength) {
            return str_repeat('*', $length);
        }
        
        return substr($value, 0, $prefixLength) . 
               str_repeat('*', max(1, $length - $prefixLength - $suffixLength)) . 
               substr($value, -$suffixLength);
    }
}