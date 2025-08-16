<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\ChatConversation;
use App\Models\Customer;
use App\Models\User;
use App\Models\LineIntegrationSetting;
use App\Models\CustomerIdentifier;
use App\Models\CustomerActivity;

class ChatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['webhook']]);
    }

    /**
     * Get chat conversations list.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get the latest conversation for each line_user_id with the actual message content
        $subquery = ChatConversation::select('line_user_id')
            ->selectRaw('MAX(message_timestamp) as max_timestamp')
            ->groupBy('line_user_id');

        $query = ChatConversation::with(['customer', 'user'])
            ->select('line_user_id', 'customer_id', 'message_content as last_message', 'message_timestamp as last_message_time')
            ->selectRaw('(SELECT COUNT(*) FROM chat_conversations c2 WHERE c2.line_user_id = chat_conversations.line_user_id AND c2.status = "unread" AND c2.is_from_customer = 1) as unread_count')
            ->joinSub($subquery, 'latest', function($join) {
                $join->on('chat_conversations.line_user_id', '=', 'latest.line_user_id')
                     ->on('chat_conversations.message_timestamp', '=', 'latest.max_timestamp');
            });

        // Staff can only see their assigned customers' chats
        if ($user->isStaff()) {
            $query->whereHas('customer', function($q) use ($user) {
                $q->where('assigned_to', $user->id);
            });
        }

        $conversations = $query->orderBy('last_message_time', 'desc')
            ->paginate(20);

        return response()->json($conversations);
    }

    /**
     * Get conversation with specific user/customer.
     */
    public function getConversation(Request $request, $userId)
    {
        $user = Auth::user();
        
        $query = ChatConversation::with(['customer', 'user', 'replier'])
            ->where('line_user_id', $userId)
            ->orderBy('message_timestamp', 'asc');

        // Staff can only see their assigned customers' chats
        if ($user->isStaff()) {
            $query->whereHas('customer', function($q) use ($user) {
                $q->where('assigned_to', $user->id);
            });
        }

        $messages = $query->paginate(50);

        // Mark messages as read
        ChatConversation::where('line_user_id', $userId)
            ->where('status', 'unread')
            ->update(['status' => 'read']);

        return response()->json($messages);
    }

    /**
     * Reply to a chat message.
     */
    public function reply(Request $request, $userId)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = Auth::user();
        
        // Find the customer associated with this LINE user
        $customer = Customer::where('line_user_id', $userId)->first();
        
        if (!$customer) {
            return response()->json(['error' => '找不到對應的客戶'], 404);
        }

        // Check if staff user has access to this customer
        if ($user->isStaff() && $customer->assigned_to !== $user->id) {
            return response()->json(['error' => '您沒有權限回覆此對話'], 403);
        }

        // Create reply message record
        $conversation = ChatConversation::create([
            'customer_id' => $customer->id,
            'user_id' => $customer->assigned_to,
            'line_user_id' => $userId,
            'platform' => 'line',
            'message_type' => 'text',
            'message_content' => $request->message,
            'message_timestamp' => now(),
            'is_from_customer' => false,
            'reply_content' => $request->message,
            'replied_at' => now(),
            'replied_by' => $user->id,
            'status' => 'sent',
        ]);

        // Send message via LINE Bot API
        $lineSuccess = $this->sendLineMessage($userId, $request->message);
        
        if (!$lineSuccess) {
            // Update conversation status to failed
            $conversation->update(['status' => 'failed']);
            
            return response()->json([
                'error' => '送出LINE訊息失敗，請檢查LINE整合設定',
                'conversation' => $conversation->load(['customer', 'user', 'replier'])
            ], 500);
        }

        return response()->json([
            'message' => '訊息已送出',
            'conversation' => $conversation->load(['customer', 'user', 'replier'])
        ]);
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

        $updated = $query->update(['status' => 'read']);

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
        $user = Auth::user();
        $query = $request->get('q', '');

        // Get the latest conversation for each line_user_id with the actual message content
        $subquery = ChatConversation::select('line_user_id')
            ->selectRaw('MAX(message_timestamp) as max_timestamp')
            ->groupBy('line_user_id');

        $conversationQuery = ChatConversation::with(['customer', 'user'])
            ->select('line_user_id', 'customer_id', 'message_content as last_message', 'message_timestamp as last_message_time')
            ->selectRaw('(SELECT COUNT(*) FROM chat_conversations c2 WHERE c2.line_user_id = chat_conversations.line_user_id AND c2.status = "unread" AND c2.is_from_customer = 1) as unread_count')
            ->joinSub($subquery, 'latest', function($join) {
                $join->on('chat_conversations.line_user_id', '=', 'latest.line_user_id')
                     ->on('chat_conversations.message_timestamp', '=', 'latest.max_timestamp');
            });

        // Staff can only search their assigned customers
        if ($user->isStaff()) {
            $conversationQuery->whereHas('customer', function($q) use ($user) {
                $q->where('assigned_to', $user->id);
            });
        }

        // Search in customer names, phone, or message content
        if ($query) {
            $conversationQuery->where(function($q) use ($query) {
                $q->whereHas('customer', function($customerQuery) use ($query) {
                    $customerQuery->where('name', 'LIKE', "%{$query}%")
                        ->orWhere('phone', 'LIKE', "%{$query}%");
                })
                ->orWhere('message_content', 'LIKE', "%{$query}%");
            });
        }

        $conversations = $conversationQuery->orderBy('last_message_time', 'desc')
            ->paginate(20);

        return response()->json($conversations);
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
            'auto_reply_enabled' => $dbSettings['auto_reply_enabled'] ?? $cachedSettings['auto_reply_enabled'] ?? config('services.line.auto_reply_enabled', true),
            'default_reply_message' => $dbSettings['default_reply_message'] ?? $cachedSettings['default_reply_message'] ?? config('services.line.default_reply_message', '感謝您的訊息，專員將盡快回覆您。'),
            'business_hours_enabled' => $dbSettings['business_hours_enabled'] ?? $cachedSettings['business_hours_enabled'] ?? config('services.line.business_hours_enabled', false),
            'business_hours_start' => $dbSettings['business_hours_start'] ?? $cachedSettings['business_hours_start'] ?? config('services.line.business_hours_start', '09:00'),
            'business_hours_end' => $dbSettings['business_hours_end'] ?? $cachedSettings['business_hours_end'] ?? config('services.line.business_hours_end', '18:00'),
            'out_of_hours_message' => $dbSettings['out_of_hours_message'] ?? $cachedSettings['out_of_hours_message'] ?? config('services.line.out_of_hours_message', '目前為非營業時間'),
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

        // Send auto-reply if enabled and not a referral code response
        if (!$isReferralCode) {
            $this->sendAutoReply($lineUserId, $messageText, $customer);
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

        // Send auto-reply acknowledging media
        $this->sendAutoReply($lineUserId, '檔案', $customer);
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

        // Send auto-reply for sticker
        $this->sendAutoReply($lineUserId, '貼圖', $customer);
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

        // Send auto-reply for location
        $this->sendAutoReply($lineUserId, '位置', $customer);
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

            Log::info('LINE follow event processed successfully', [
                'line_user_id' => $lineUserId,
                'customer_id' => $customer->id,
                'conversation_id' => $conversation->id
            ]);

            // Send welcome message
            $welcomeMessage = $this->getWelcomeMessage();
            $messageSent = $this->sendLineMessage($lineUserId, $welcomeMessage);
            
            // Record welcome message in conversation if successfully sent
            if ($messageSent) {
                ChatConversation::create([
                    'customer_id' => $customer->id,
                    'user_id' => $customer->assigned_to,
                    'line_user_id' => $lineUserId,
                    'platform' => 'line',
                    'message_type' => 'text',
                    'message_content' => $welcomeMessage,
                    'message_timestamp' => now(),
                    'is_from_customer' => false,
                    'reply_content' => $welcomeMessage,
                    'replied_at' => now(),
                    'replied_by' => 1, // System user
                    'status' => 'sent',
                    'metadata' => [
                        'is_welcome_message' => true,
                        'event_type' => 'welcome',
                    ],
                ]);
                
                // Send flex message for business referral code input
                $referralFlexMessage = $this->createReferralCodeFlexMessage();
                $flexMessageSent = $this->sendLineFlexMessage($lineUserId, $referralFlexMessage);
                
                // Record flex message in conversation if successfully sent
                if ($flexMessageSent) {
                    ChatConversation::create([
                        'customer_id' => $customer->id,
                        'user_id' => $customer->assigned_to,
                        'line_user_id' => $lineUserId,
                        'platform' => 'line',
                        'message_type' => 'flex',
                        'message_content' => '請輸入業務推薦碼',
                        'message_timestamp' => now(),
                        'is_from_customer' => false,
                        'reply_content' => '請輸入業務推薦碼',
                        'replied_at' => now(),
                        'replied_by' => 1, // System user
                        'status' => 'sent',
                        'metadata' => [
                            'is_flex_message' => true,
                            'flex_type' => 'referral_code_input',
                            'event_type' => 'welcome',
                        ],
                    ]);
                }
            }
            
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
     * Send auto-reply message
     */
    protected function sendAutoReply($lineUserId, $messageText, $customer)
    {
        $settings = $this->getLineSettings();
        $autoReplyEnabled = $settings['auto_reply_enabled'];
        
        if (!$autoReplyEnabled) {
            return;
        }

        // Check business hours
        if ($this->isOutOfBusinessHours()) {
            $message = $settings['out_of_hours_message'];
        } else {
            $message = $settings['default_reply_message'];
        }

        // Send the auto-reply
        if ($this->sendLineMessage($lineUserId, $message)) {
            // Save auto-reply as conversation
            ChatConversation::create([
                'customer_id' => $customer->id,
                'user_id' => $customer->assigned_to,
                'line_user_id' => $lineUserId,
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => $message,
                'message_timestamp' => now(),
                'is_from_customer' => false,
                'reply_content' => $message,
                'replied_at' => now(),
                'replied_by' => 1, // System user
                'status' => 'sent',
                'metadata' => [
                    'is_auto_reply' => true,
                ],
            ]);
        }
    }

    /**
     * Check if current time is out of business hours
     */
    protected function isOutOfBusinessHours()
    {
        $settings = $this->getLineSettings();
        $businessHoursEnabled = $settings['business_hours_enabled'];
        
        if (!$businessHoursEnabled) {
            return false;
        }

        $now = now();
        $startTime = $settings['business_hours_start'];
        $endTime = $settings['business_hours_end'];
        
        $currentTime = $now->format('H:i');
        
        // Only check weekdays (Monday to Friday)
        if ($now->isWeekend()) {
            return true;
        }
        
        return $currentTime < $startTime || $currentTime > $endTime;
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
                Log::error('LINE Channel Access Token not configured');
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
                            'type' => 'text',
                            'text' => $message
                        ]
                    ]
                ],
                'timeout' => 10,
            ]);

            Log::info('LINE message sent successfully', [
                'line_user_id' => $lineUserId,
                'response_code' => $response->getStatusCode()
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send LINE message', [
                'line_user_id' => $lineUserId,
                'message' => $message,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Handle referral code input from customer
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

            // Send confirmation message
            $confirmationMessage = "感謝您輸入推薦碼：{$referralCode}\n\n我們將為您提供更優惠的服務方案，專員將盡快與您聯繫！";
            $this->sendLineMessage($lineUserId, $confirmationMessage);
            
            // Record confirmation message
            ChatConversation::create([
                'customer_id' => $customer->id,
                'user_id' => $customer->assigned_to,
                'line_user_id' => $lineUserId,
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => $confirmationMessage,
                'message_timestamp' => now(),
                'is_from_customer' => false,
                'reply_content' => $confirmationMessage,
                'replied_at' => now(),
                'replied_by' => 1, // System user
                'status' => 'sent',
                'metadata' => [
                    'is_referral_confirmation' => true,
                    'referral_code' => $referralCode,
                ],
            ]);

            Log::info('Referral code processed successfully', [
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

            // Send acknowledgment message
            $skipMessage = "沒問題！您仍然可以享受我們優質的貸款服務。\n\n如有任何問題，歡迎隨時與我們聯繫！";
            $this->sendLineMessage($lineUserId, $skipMessage);
            
            // Record skip message
            ChatConversation::create([
                'customer_id' => $customer->id,
                'user_id' => $customer->assigned_to,
                'line_user_id' => $lineUserId,
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => $skipMessage,
                'message_timestamp' => now(),
                'is_from_customer' => false,
                'reply_content' => $skipMessage,
                'replied_at' => now(),
                'replied_by' => 1, // System user
                'status' => 'sent',
                'metadata' => [
                    'is_referral_skip_confirmation' => true,
                ],
            ]);

            Log::info('Referral code skip processed successfully', [
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
}