<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\ChatConversation;
use App\Models\Customer;

class LineIntegrationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin|executive|manager');
    }

    /**
     * Get LINE integration settings
     */
    public function getSettings()
    {
        $user = Auth::user();
        
        // Get cached settings first, then fall back to config
        $cachedSettings = Cache::get('line_integration_settings', []);
        
        $settings = [
            'channel_access_token' => $this->getMaskedToken($cachedSettings['channel_access_token'] ?? config('services.line.channel_access_token', '')),
            'channel_secret' => $this->getMaskedSecret($cachedSettings['channel_secret'] ?? config('services.line.channel_secret', '')),
            'webhook_url' => url('/api/line/webhook'),
            'bot_basic_id' => $cachedSettings['bot_basic_id'] ?? config('services.line.bot_basic_id', ''),
            'auto_reply_enabled' => $cachedSettings['auto_reply_enabled'] ?? config('services.line.auto_reply_enabled', true),
            'default_reply_message' => $cachedSettings['default_reply_message'] ?? config('services.line.default_reply_message', '感謝您的訊息，專員將盡快回覆您。'),
            'business_hours' => [
                'enabled' => $cachedSettings['business_hours_enabled'] ?? config('services.line.business_hours_enabled', false),
                'start_time' => $cachedSettings['business_hours_start'] ?? config('services.line.business_hours_start', '09:00'),
                'end_time' => $cachedSettings['business_hours_end'] ?? config('services.line.business_hours_end', '18:00'),
                'out_of_hours_message' => $cachedSettings['out_of_hours_message'] ?? config('services.line.out_of_hours_message', '目前為非營業時間，我們將在營業時間內盡快回覆您。營業時間：週一至週五 9:00-18:00')
            ],
            'webhook_status' => $this->getWebhookStatus(),
            'integration_status' => $this->getIntegrationStatus(),
        ];

        return response()->json(['settings' => $settings]);
    }

    /**
     * Update LINE integration settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'channel_access_token' => 'nullable|string|min:10',
            'channel_secret' => 'nullable|string|min:10',
            'bot_basic_id' => 'nullable|string',
            'auto_reply_enabled' => 'boolean',
            'default_reply_message' => 'nullable|string|max:500',
            'business_hours.enabled' => 'boolean',
            'business_hours.start_time' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'business_hours.end_time' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'business_hours.out_of_hours_message' => 'nullable|string|max:500',
        ]);

        try {
            // Update configuration (in a real app, this would be saved to database or .env file)
            // For now, we'll store in cache as a temporary solution
            $settings = [
                'channel_access_token' => $request->input('channel_access_token'),
                'channel_secret' => $request->input('channel_secret'),
                'bot_basic_id' => $request->input('bot_basic_id'),
                'auto_reply_enabled' => $request->input('auto_reply_enabled', true),
                'default_reply_message' => $request->input('default_reply_message', '感謝您的訊息，專員將盡快回覆您。'),
                'business_hours_enabled' => $request->input('business_hours.enabled', false),
                'business_hours_start' => $request->input('business_hours.start_time', '09:00'),
                'business_hours_end' => $request->input('business_hours.end_time', '18:00'),
                'out_of_hours_message' => $request->input('business_hours.out_of_hours_message', '目前為非營業時間'),
            ];

            // Store in cache for 24 hours (in production, this should be in database)
            Cache::put('line_integration_settings', $settings, now()->addHours(24));

            // Test connection if access token is provided
            $connectionStatus = null;
            if ($settings['channel_access_token']) {
                $connectionStatus = $this->testLineConnection($settings['channel_access_token']);
            }

            // Get the updated settings using the same method as getSettings()
            $cachedSettings = Cache::get('line_integration_settings', []);
            $updatedSettings = [
                'channel_access_token' => $this->getMaskedToken($cachedSettings['channel_access_token'] ?? ''),
                'channel_secret' => $this->getMaskedSecret($cachedSettings['channel_secret'] ?? ''),
                'webhook_url' => url('/api/line/webhook'),
                'bot_basic_id' => $cachedSettings['bot_basic_id'] ?? '',
                'auto_reply_enabled' => $cachedSettings['auto_reply_enabled'] ?? true,
                'default_reply_message' => $cachedSettings['default_reply_message'] ?? '感謝您的訊息，專員將盡快回覆您。',
                'business_hours' => [
                    'enabled' => $cachedSettings['business_hours_enabled'] ?? false,
                    'start_time' => $cachedSettings['business_hours_start'] ?? '09:00',
                    'end_time' => $cachedSettings['business_hours_end'] ?? '18:00',
                    'out_of_hours_message' => $cachedSettings['out_of_hours_message'] ?? '目前為非營業時間'
                ],
                'webhook_status' => $this->getWebhookStatus(),
                'integration_status' => $this->getIntegrationStatus(),
            ];

            return response()->json([
                'message' => 'LINE 整合設定已更新',
                'connection_status' => $connectionStatus,
                'settings' => $updatedSettings
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update LINE integration settings', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => '更新設定時發生錯誤：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test LINE Bot connection
     */
    public function testConnection()
    {
        $settings = Cache::get('line_integration_settings', []);
        $token = $settings['channel_access_token'] ?? config('services.line.channel_access_token');

        Log::info('Test connection requested', [
            'cached_settings_exists' => !empty($settings),
            'has_cached_token' => !empty($settings['channel_access_token']),
            'has_config_token' => !empty(config('services.line.channel_access_token')),
            'final_token_length' => $token ? strlen($token) : 0
        ]);

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => '請先設定 Channel Access Token',
                'debug_info' => [
                    'cached_settings' => $settings,
                    'config_token_exists' => !empty(config('services.line.channel_access_token'))
                ]
            ], 400);
        }

        $result = $this->testLineConnection($token);

        return response()->json($result);
    }

    /**
     * Debug LINE Bot connection with detailed information
     */
    public function debugConnection()
    {
        $settings = Cache::get('line_integration_settings', []);
        $configToken = config('services.line.channel_access_token');
        $token = $settings['channel_access_token'] ?? $configToken;

        $debugInfo = [
            'cache_settings' => $settings,
            'config_token_exists' => !empty($configToken),
            'config_token_length' => $configToken ? strlen($configToken) : 0,
            'final_token_exists' => !empty($token),
            'final_token_length' => $token ? strlen($token) : 0,
            'php_version' => PHP_VERSION,
            'guzzle_exists' => class_exists('\GuzzleHttp\Client'),
            'cache_driver' => config('cache.default'),
            'app_env' => config('app.env')
        ];

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => '無法找到 Channel Access Token',
                'debug_info' => $debugInfo
            ]);
        }

        // Test the connection with detailed error reporting
        $result = $this->testLineConnection($token);
        $result['debug_info'] = $debugInfo;

        return response()->json($result);
    }

    /**
     * Get LINE Bot profile information
     */
    public function getBotInfo()
    {
        $settings = Cache::get('line_integration_settings', []);
        $token = $settings['channel_access_token'] ?? config('services.line.channel_access_token');

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => '請先設定 Channel Access Token'
            ], 400);
        }

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->get('https://api.line.me/v2/bot/info', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'timeout' => 10,
            ]);

            $botInfo = json_decode($response->getBody()->getContents(), true);

            return response()->json([
                'status' => 'success',
                'bot_info' => $botInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '無法取得機器人資訊：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get LINE integration statistics
     */
    public function getStats()
    {
        $user = Auth::user();
        
        $query = ChatConversation::where('platform', 'line');
        
        // Staff can only see their assigned customers' stats
        if ($user->isStaff()) {
            $query->whereHas('customer', function($q) use ($user) {
                $q->where('assigned_to', $user->id);
            });
        }

        $stats = [
            'total_conversations' => (clone $query)->distinct('line_user_id')->count(),
            'total_messages' => (clone $query)->count(),
            'unread_messages' => (clone $query)->where('status', 'unread')->where('is_from_customer', true)->count(),
            'today_messages' => (clone $query)->whereDate('message_timestamp', today())->count(),
            'this_week_messages' => (clone $query)->whereBetween('message_timestamp', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'this_month_messages' => (clone $query)->whereMonth('message_timestamp', now()->month)->count(),
            'active_customers_7_days' => (clone $query)->where('message_timestamp', '>=', now()->subDays(7))->distinct('line_user_id')->count(),
            'response_rate' => $this->calculateResponseRate(),
        ];

        return response()->json(['stats' => $stats]);
    }

    /**
     * Get recent LINE conversations
     */
    public function getRecentConversations(Request $request)
    {
        $user = Auth::user();
        $limit = $request->input('limit', 10);
        
        $query = ChatConversation::with(['customer', 'user'])
            ->where('platform', 'line')
            ->select('line_user_id', 'customer_id')
            ->selectRaw('MAX(message_timestamp) as last_message_time')
            ->selectRaw('COUNT(CASE WHEN status = "unread" AND is_from_customer = 1 THEN 1 END) as unread_count')
            ->selectRaw('(SELECT message_content FROM chat_conversations c2 WHERE c2.line_user_id = chat_conversations.line_user_id ORDER BY c2.message_timestamp DESC LIMIT 1) as last_message')
            ->groupBy('line_user_id', 'customer_id');

        // Staff can only see their assigned customers
        if ($user->isStaff()) {
            $query->whereHas('customer', function($q) use ($user) {
                $q->where('assigned_to', $user->id);
            });
        }

        $conversations = $query->orderBy('last_message_time', 'desc')
            ->limit($limit)
            ->get();

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * Send message to LINE user
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'line_user_id' => 'required|string',
            'message' => 'required|string|max:1000',
        ]);

        $user = Auth::user();
        $lineUserId = $request->input('line_user_id');
        $messageText = $request->input('message');

        try {
            // Find customer
            $customer = Customer::where('line_user_id', $lineUserId)->first();
            
            if (!$customer) {
                return response()->json(['error' => '找不到對應的客戶'], 404);
            }

            // Check permissions
            if ($user->isStaff() && $customer->assigned_to !== $user->id) {
                return response()->json(['error' => '您沒有權限傳送訊息給此客戶'], 403);
            }

            // Send message via LINE API
            $result = $this->sendLineMessage($lineUserId, $messageText);

            if ($result['success']) {
                // Save to database
                $conversation = ChatConversation::create([
                    'customer_id' => $customer->id,
                    'user_id' => $customer->assigned_to,
                    'line_user_id' => $lineUserId,
                    'platform' => 'line',
                    'message_type' => 'text',
                    'message_content' => $messageText,
                    'message_timestamp' => now(),
                    'is_from_customer' => false,
                    'reply_content' => $messageText,
                    'replied_at' => now(),
                    'replied_by' => $user->id,
                    'status' => 'sent',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => '訊息已成功送出',
                    'conversation' => $conversation->load(['customer', 'user', 'replier'])
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '傳送失敗：' . $result['error']
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send LINE message', [
                'error' => $e->getMessage(),
                'line_user_id' => $lineUserId,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'message' => '傳送訊息時發生錯誤'
            ], 500);
        }
    }

    /**
     * Mask sensitive token for display
     */
    private function getMaskedToken($token)
    {
        if (!$token) return '';
        if (strlen($token) <= 10) return str_repeat('*', strlen($token));
        
        // For longer tokens, use a more concise display format
        if (strlen($token) > 20) {
            return substr($token, 0, 4) . '...' . substr($token, -4);
        }
        
        return substr($token, 0, 6) . str_repeat('*', strlen($token) - 10) . substr($token, -4);
    }

    /**
     * Mask sensitive secret for display
     */
    private function getMaskedSecret($secret)
    {
        if (!$secret) return '';
        if (strlen($secret) <= 8) return str_repeat('*', strlen($secret));
        
        return substr($secret, 0, 4) . str_repeat('*', strlen($secret) - 8) . substr($secret, -4);
    }

    /**
     * Get webhook status
     */
    private function getWebhookStatus()
    {
        // Check if webhook endpoint is accessible
        try {
            $response = file_get_contents(url('/api/line/webhook'), false, stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5
                ]
            ]));
            return 'active';
        } catch (\Exception $e) {
            return 'inactive';
        }
    }

    /**
     * Get integration status
     */
    private function getIntegrationStatus()
    {
        $settings = Cache::get('line_integration_settings', []);
        $hasToken = !empty($settings['channel_access_token']) || !empty(config('services.line.channel_access_token'));
        $hasSecret = !empty($settings['channel_secret']) || !empty(config('services.line.channel_secret'));
        
        if ($hasToken && $hasSecret) {
            return 'configured';
        } elseif ($hasToken || $hasSecret) {
            return 'partial';
        } else {
            return 'not_configured';
        }
    }

    /**
     * Validate LINE token format
     */
    private function validateTokenFormat($token)
    {
        // LINE Channel Access Token format validation
        if (empty($token)) {
            return ['valid' => false, 'reason' => 'Token is empty'];
        }
        
        if (strlen($token) < 100) {
            return ['valid' => false, 'reason' => 'Token too short (should be >100 characters)'];
        }
        
        // Basic format check - LINE tokens usually contain alphanumeric and some special chars
        if (!preg_match('/^[a-zA-Z0-9+\/=]+$/', $token)) {
            return ['valid' => false, 'reason' => 'Token contains invalid characters'];
        }
        
        return ['valid' => true, 'reason' => 'Token format appears valid'];
    }

    /**
     * Test LINE connection
     */
    private function testLineConnection($token)
    {
        // First validate token format
        $validation = $this->validateTokenFormat($token);
        if (!$validation['valid']) {
            return [
                'status' => 'error',
                'message' => 'Token 格式錯誤：' . $validation['reason'],
                'validation' => $validation
            ];
        }

        try {
            Log::info('Testing LINE connection', [
                'token_length' => strlen($token),
                'token_prefix' => substr($token, 0, 10) . '...',
                'validation' => $validation
            ]);

            $client = new \GuzzleHttp\Client();
            $response = $client->get('https://api.line.me/v2/bot/info', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'timeout' => 10,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            
            Log::info('LINE connection test successful', [
                'response_code' => $response->getStatusCode(),
                'bot_info' => $responseData
            ]);

            return [
                'status' => 'success',
                'message' => 'LINE Bot 連線測試成功',
                'response_code' => $response->getStatusCode(),
                'bot_info' => $responseData
            ];

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = '';
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
            }
            
            Log::error('LINE connection test failed - Client error', [
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : 'unknown',
                'response_body' => $responseBody,
                'token_length' => strlen($token)
            ]);

            return [
                'status' => 'error',
                'message' => 'LINE Bot 連線失敗：權限驗證錯誤 (HTTP ' . ($e->getResponse() ? $e->getResponse()->getStatusCode() : 'unknown') . ')',
                'response_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null,
                'details' => $responseBody
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('LINE connection test failed - Request error', [
                'error' => $e->getMessage(),
                'token_length' => strlen($token)
            ]);

            return [
                'status' => 'error',
                'message' => 'LINE Bot 連線失敗：網路連線錯誤 - ' . $e->getMessage(),
                'details' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('LINE connection test failed - General error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'token_length' => strlen($token)
            ]);

            return [
                'status' => 'error',
                'message' => 'LINE Bot 連線失敗：' . $e->getMessage(),
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate response rate
     */
    private function calculateResponseRate()
    {
        $totalCustomerMessages = ChatConversation::where('platform', 'line')
            ->where('is_from_customer', true)
            ->whereDate('message_timestamp', '>=', now()->subDays(7))
            ->distinct('line_user_id')
            ->count();

        if ($totalCustomerMessages === 0) {
            return 0;
        }

        $respondedConversations = ChatConversation::where('platform', 'line')
            ->where('is_from_customer', false)
            ->whereDate('message_timestamp', '>=', now()->subDays(7))
            ->distinct('line_user_id')
            ->count();

        return round(($respondedConversations / $totalCustomerMessages) * 100, 1);
    }

    /**
     * Send message via LINE API
     */
    private function sendLineMessage($lineUserId, $message)
    {
        try {
            $settings = Cache::get('line_integration_settings', []);
            $token = $settings['channel_access_token'] ?? config('services.line.channel_access_token');

            if (!$token) {
                return ['success' => false, 'error' => 'Access Token 未設定'];
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

            return ['success' => true, 'response_code' => $response->getStatusCode()];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}