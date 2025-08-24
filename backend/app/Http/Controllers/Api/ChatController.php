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
        $this->middleware('auth:api', ['except' => ['webhook']]);
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
                'timestamp' => now()->toISOString()
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
                'timestamp' => now()->toISOString()
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
                
                Log::error('LINE message send failed', [
                    'conversation_id' => $conversation->id,
                    'line_user_id' => $userId
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => '送出LINE訊息失敗',
                    'error' => '送出LINE訊息失敗，請檢查LINE整合設定',
                    'conversation' => $conversation->load(['customer', 'user', 'replier'])
                ], 500);
            }

            // Update conversation status to sent (with fallback handling)
            $this->safeUpdateStatus($conversation, 'sent');
            
            // Sync to Firebase Realtime Database
            $this->firebaseChatService->syncConversationToFirebase($conversation);
            
            // Broadcast the new message event for real-time updates
            broadcast(new NewChatMessage($conversation, $userId));
            
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
        try {
            // Verify LINE webhook signature
            if (!$this->verifySignature($request)) {
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $events = $request->input('events', []);
            Log::info('LINE Webhook received events', ['events_count' => count($events)]);

            foreach ($events as $event) {
                $this->processEvent($event);
            }

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('LINE Webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Internal server error'], 500);
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
        $dbSettings = LineIntegrationSetting::getAllSettings(true);
        $cachedSettings = Cache::get('line_integration_settings', []);
        
        return [
            'channel_access_token' => $dbSettings['channel_access_token'] ?? $cachedSettings['channel_access_token'] ?? config('services.line.channel_access_token', ''),
            'channel_secret' => $dbSettings['channel_secret'] ?? $cachedSettings['channel_secret'] ?? config('services.line.channel_secret', ''),
        ];
    }

    /**
     * Verify LINE webhook signature
     */
    protected function verifySignature(Request $request)
    {
        $signature = $request->header('X-Line-Signature');
        $body = $request->getContent();
        
        if (!$signature || !$body) {
            return false;
        }

        // Get channel secret from database
        $settings = $this->getLineSettings();
        $channelSecret = $settings['channel_secret'];
        
        if (!$channelSecret) {
            Log::warning('LINE Channel Secret not configured for webhook verification');
            return true; // Allow webhook if secret not configured (for testing)
        }

        $expectedSignature = base64_encode(hash_hmac('sha256', $body, $channelSecret, true));
        
        return hash_equals($expectedSignature, $signature);
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
        
        if (!$lineUserId || !$messageText) {
            return;
        }

        Log::info('Processing LINE text message', [
            'line_user_id' => $lineUserId,
            'message' => $messageText
        ]);

        // Find or create customer record
        $customer = $this->findOrCreateCustomer($lineUserId, $event);
        
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
        $conversation = ChatConversation::create([
            'customer_id' => $customer->id,
            'user_id' => $customer->assigned_to,
            'line_user_id' => $lineUserId,
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

        // Sync to Firebase Realtime Database
        $this->firebaseChatService->syncConversationToFirebase($conversation);
        
        // Broadcast the new message event for real-time updates
        broadcast(new NewChatMessage($conversation, $lineUserId));

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
                    'assigned_to' => null, // Unassigned by default for LINE customers
                    'region' => '未知',
                    'website_source' => 'LINE Bot',
                    'source_data' => [
                        'line_profile' => $profile,
                        'first_contact' => now()->toISOString(),
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
                $sourceData['line_integration_date'] = now()->toISOString();
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
                    'referral_code_entered_at' => now()->toISOString(),
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
                    'referral_code_skipped_at' => now()->toISOString(),
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
                                'timestamp' => now()->toISOString(),
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
                'timestamp' => now()->toISOString(),
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
                'timestamp' => now()->toISOString()
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
     * 檢查 Firebase 健康狀態
     */
    public function checkFirebaseHealth(Request $request)
    {
        try {
            $user = Auth::user();

            $healthResult = $this->firebaseSyncService->checkSyncHealth();
            $connectionStatus = $this->firebaseChatService->checkFirebaseConnection();

            $health = [
                'firebase_connection' => $connectionStatus,
                'sync_health' => $healthResult,
                'timestamp' => now()->toISOString(),
                'checked_by' => $user->id
            ];

            Log::channel('firebase')->info('Firebase health check performed', [
                'user_id' => $user->id,
                'connection_status' => $connectionStatus,
                'sync_health' => $healthResult['success'] ?? false
            ]);

            return response()->json([
                'success' => true,
                'data' => $health,
                'message' => 'Firebase health check completed'
            ]);

        } catch (\Exception $e) {
            Log::channel('firebase')->error('Firebase health check failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Health check failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
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
                'test_timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Permission test failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('權限測試失敗', $e);
        }
    }
}