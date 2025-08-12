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
        
        $query = ChatConversation::with(['customer', 'user'])
            ->select('line_user_id', 'customer_id')
            ->selectRaw('MAX(message_timestamp) as last_message_time')
            ->selectRaw('COUNT(CASE WHEN status = "unread" AND is_from_customer = 1 THEN 1 END) as unread_count')
            ->groupBy('line_user_id', 'customer_id');

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

        // TODO: Send actual message via LINE Bot API
        // This would integrate with LINE Bot API to send the message

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

        $conversationQuery = ChatConversation::with(['customer', 'user'])
            ->select('line_user_id', 'customer_id')
            ->selectRaw('MAX(message_timestamp) as last_message_time')
            ->selectRaw('COUNT(CASE WHEN status = "unread" AND is_from_customer = 1 THEN 1 END) as unread_count');

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

        $conversations = $conversationQuery->groupBy('line_user_id', 'customer_id')
            ->orderBy('last_message_time', 'desc')
            ->paginate(20);

        return response()->json($conversations);
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

        // Get channel secret from cache or config
        $settings = Cache::get('line_integration_settings', []);
        $channelSecret = $settings['channel_secret'] ?? config('services.line.channel_secret');
        
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
            ],
        ]);

        // Send auto-reply if enabled
        $this->sendAutoReply($lineUserId, $messageText, $customer);
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
        
        if (!$lineUserId) {
            return;
        }

        Log::info('Processing LINE follow event', ['line_user_id' => $lineUserId]);

        // Find or create customer record
        $customer = $this->findOrCreateCustomer($lineUserId, $event);
        
        // Save follow event as conversation
        ChatConversation::create([
            'customer_id' => $customer->id,
            'user_id' => $customer->assigned_to,
            'line_user_id' => $lineUserId,
            'platform' => 'line',
            'message_type' => 'text',
            'message_content' => '加入好友',
            'message_timestamp' => now(),
            'is_from_customer' => true,
            'status' => 'unread',
            'metadata' => [
                'event_type' => 'follow',
            ],
        ]);

        // Send welcome message
        $welcomeMessage = $this->getWelcomeMessage();
        $this->sendLineMessage($lineUserId, $welcomeMessage);
    }

    /**
     * Handle unfollow events (user removes bot as friend)
     */
    protected function handleUnfollow($event)
    {
        $lineUserId = $event['source']['userId'] ?? null;
        
        if (!$lineUserId) {
            return;
        }

        Log::info('Processing LINE unfollow event', ['line_user_id' => $lineUserId]);

        // Find customer record
        $customer = Customer::where('line_user_id', $lineUserId)->first();
        
        if ($customer) {
            // Save unfollow event as conversation
            ChatConversation::create([
                'customer_id' => $customer->id,
                'user_id' => $customer->assigned_to,
                'line_user_id' => $lineUserId,
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => '取消好友',
                'message_timestamp' => now(),
                'is_from_customer' => true,
                'status' => 'unread',
                'metadata' => [
                    'event_type' => 'unfollow',
                ],
            ]);
        }
    }

    /**
     * Find or create customer from LINE user
     */
    protected function findOrCreateCustomer($lineUserId, $event)
    {
        $customer = Customer::where('line_user_id', $lineUserId)->first();
        
        if (!$customer) {
            // Try to get LINE user profile
            $profile = $this->getLineUserProfile($lineUserId);
            
            $customer = Customer::create([
                'name' => $profile['displayName'] ?? '來自LINE的客戶',
                'line_user_id' => $lineUserId,
                'channel' => 'line',
                'status' => 'NEW', // Use constants from Customer model if available
                'tracking_status' => 'PENDING',
                'created_by' => 1, // System user
                'region' => '未知',
                'source' => 'LINE Bot',
                'metadata' => [
                    'line_profile' => $profile,
                    'first_contact' => now()->toISOString(),
                ],
            ]);

            Log::info('Created new customer from LINE', [
                'customer_id' => $customer->id,
                'line_user_id' => $lineUserId,
                'name' => $customer->name
            ]);
        }

        return $customer;
    }

    /**
     * Get LINE user profile
     */
    protected function getLineUserProfile($lineUserId)
    {
        try {
            $settings = Cache::get('line_integration_settings', []);
            $token = $settings['channel_access_token'] ?? config('services.line.channel_access_token');

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
        $settings = Cache::get('line_integration_settings', []);
        $autoReplyEnabled = $settings['auto_reply_enabled'] ?? config('services.line.auto_reply_enabled', true);
        
        if (!$autoReplyEnabled) {
            return;
        }

        // Check business hours
        if ($this->isOutOfBusinessHours()) {
            $message = $settings['out_of_hours_message'] ?? config('services.line.out_of_hours_message', '目前為非營業時間，我們將在營業時間內盡快回覆您。');
        } else {
            $message = $settings['default_reply_message'] ?? config('services.line.default_reply_message', '感謝您的訊息，專員將盡快回覆您。');
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
        $settings = Cache::get('line_integration_settings', []);
        $businessHoursEnabled = $settings['business_hours_enabled'] ?? config('services.line.business_hours_enabled', false);
        
        if (!$businessHoursEnabled) {
            return false;
        }

        $now = now();
        $startTime = $settings['business_hours_start'] ?? config('services.line.business_hours_start', '09:00');
        $endTime = $settings['business_hours_end'] ?? config('services.line.business_hours_end', '18:00');
        
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
            $settings = Cache::get('line_integration_settings', []);
            $token = $settings['channel_access_token'] ?? config('services.line.channel_access_token');

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
     * Get welcome message for new followers
     */
    protected function getWelcomeMessage()
    {
        return "歡迎加入我們的LINE官方帳號！\n\n我們提供以下貸款服務：\n🚗 汽車貸款\n🛵 機車貸款\n📱 手機貸款\n\n如有任何問題，請隨時與我們聯繫，專員將盡快為您服務！";
    }
}