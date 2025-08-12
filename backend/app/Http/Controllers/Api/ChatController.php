<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        // Verify LINE webhook signature
        $signature = $request->header('X-Line-Signature');
        $body = $request->getContent();
        
        // TODO: Verify signature with LINE Bot Channel Secret
        
        $events = $request->input('events', []);

        foreach ($events as $event) {
            if ($event['type'] === 'message' && $event['message']['type'] === 'text') {
                $this->handleTextMessage($event);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle incoming text message from LINE.
     */
    protected function handleTextMessage($event)
    {
        $lineUserId = $event['source']['userId'] ?? null;
        $messageText = $event['message']['text'] ?? '';
        
        if (!$lineUserId || !$messageText) {
            return;
        }

        // Find or create customer record
        $customer = Customer::where('line_user_id', $lineUserId)->first();
        
        if (!$customer) {
            // Get LINE user profile (would need to call LINE API)
            $customer = Customer::create([
                'name' => '來自LINE的客戶',
                'line_user_id' => $lineUserId,
                'channel' => 'line',
                'status' => Customer::STATUS_NEW,
                'tracking_status' => Customer::TRACKING_PENDING,
                'created_by' => 1, // System user
            ]);
        }

        // Save conversation
        ChatConversation::create([
            'customer_id' => $customer->id,
            'user_id' => $customer->assigned_to,
            'line_user_id' => $lineUserId,
            'platform' => 'line',
            'message_type' => 'text',
            'message_content' => $messageText,
            'message_timestamp' => now(),
            'is_from_customer' => true,
            'status' => 'unread',
            'metadata' => [
                'event_id' => $event['message']['id'] ?? null,
                'timestamp' => $event['timestamp'] ?? null,
            ],
        ]);
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
}