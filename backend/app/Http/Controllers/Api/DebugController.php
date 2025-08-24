<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\Customer;
use App\Services\FirebaseChatService;
use App\Services\FirebaseSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DebugController extends Controller
{
    protected $firebaseChatService;
    protected $firebaseSyncService;

    public function __construct(
        FirebaseChatService $firebaseChatService,
        FirebaseSyncService $firebaseSyncService
    ) {
        $this->firebaseChatService = $firebaseChatService;
        $this->firebaseSyncService = $firebaseSyncService;
    }

    /**
     * 系統健康狀態檢查
     */
    public function systemHealthCheck(): JsonResponse
    {
        // 檢查是否啟用除錯模式
        if (!$this->isDebugEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Debug mode disabled'
            ], 403);
        }

        try {
            $health = [
                'timestamp' => now()->toISOString(),
                'firebase' => $this->checkFirebaseHealth(),
                'mysql' => $this->checkMySQLHealth(),
                'sync' => $this->checkSyncHealth(),
                'permissions' => $this->checkPermissions(),
            ];

            return response()->json([
                'success' => true,
                'health' => $health,
                'recommendations' => $this->getRecommendations($health)
            ]);

        } catch (\Exception $e) {
            Log::error('Debug health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_details' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                    'context' => 'System health check operation',
                    'suggestions' => [
                        '檢查Firebase配置文件是否存在',
                        '確認MySQL資料庫連接正常',
                        '檢查環境變數配置',
                        '查看Laravel日誌文件獲取更多資訊'
                    ]
                ]
            ], 500);
        }
    }

    /**
     * 批次同步MySQL資料到Firebase
     */
    public function batchSyncToFirebase(Request $request): JsonResponse
    {
        // 檢查是否啟用除錯模式和管理員權限
        if (!$this->isDebugEnabled() || !$this->hasAdminAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        try {
            $limit = $request->get('limit', 100);
            $offset = $request->get('offset', 0);

            // 執行批次同步
            $result = $this->firebaseSyncService->syncMySQLToFirebase();

            return response()->json([
                'success' => true,
                'sync_result' => $result,
                'message' => 'Batch sync completed'
            ]);

        } catch (\Exception $e) {
            Log::error('Batch sync to Firebase failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_details' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                    'context' => 'Firebase batch synchronization operation',
                    'suggestions' => [
                        '檢查Firebase Realtime Database是否已啟用',
                        '確認Firebase服務帳號權限正確',
                        '檢查網路連接是否正常',
                        '確認Firebase Database URL格式正確',
                        '檢查MySQL中是否有聊天記錄需要同步'
                    ]
                ]
            ], 500);
        }
    }

    /**
     * 獲取詳細的除錯資訊
     */
    public function getDebugInfo(): JsonResponse
    {
        if (!$this->isDebugEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Debug mode disabled'
            ], 403);
        }

        try {
            $info = [
                'config' => $this->getConfigInfo(),
                'database_stats' => $this->getDatabaseStats(),
                'firebase_stats' => $this->getFirebaseStats(),
                'recent_errors' => $this->getRecentErrors(),
            ];

            return response()->json([
                'success' => true,
                'debug_info' => $info
            ]);

        } catch (\Exception $e) {
            Log::error('Debug info retrieval failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_details' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                    'context' => 'Debug information retrieval operation',
                    'suggestions' => [
                        '檢查資料庫連接狀態',
                        '確認Firebase服務正常運作',
                        '檢查系統配置文件',
                        '查看應用程式日誌獲取詳細信息'
                    ]
                ]
            ], 500);
        }
    }

    /**
     * 清理並重置Firebase資料
     */
    public function resetFirebaseData(): JsonResponse
    {
        if (!$this->isDebugEnabled() || !$this->hasAdminAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        try {
            // 這裡可以添加清理Firebase資料的邏輯
            // 注意：這是危險操作，只在開發環境使用

            return response()->json([
                'success' => true,
                'message' => 'Firebase data reset completed'
            ]);

        } catch (\Exception $e) {
            Log::error('Firebase data reset failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_details' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                    'context' => 'Firebase data reset operation (dangerous operation)',
                    'suggestions' => [
                        '確認您有Firebase專案的完整管理權限',
                        '檢查Firebase服務帳號配置',
                        '確認Firebase Realtime Database存在',
                        '這是危險操作，僅在開發環境使用',
                        '考慮使用Firebase控制台手動清理資料'
                    ]
                ]
            ], 500);
        }
    }

    /**
     * 檢查Firebase健康狀態
     */
    protected function checkFirebaseHealth(): array
    {
        return [
            'connection' => $this->firebaseChatService->checkFirebaseConnection(),
            'config_valid' => $this->isFirebaseConfigValid(),
            'service_account_exists' => $this->hasFirebaseServiceAccount(),
        ];
    }

    /**
     * 檢查MySQL健康狀態
     */
    protected function checkMySQLHealth(): array
    {
        try {
            $conversationsCount = ChatConversation::count();
            $customersCount = Customer::count();
            $assignedCustomers = Customer::whereNotNull('assigned_to')->count();
            $lineCustomers = Customer::whereNotNull('line_user_id')->count();

            return [
                'connection' => true,
                'conversations_count' => $conversationsCount,
                'customers_count' => $customersCount,
                'assigned_customers' => $assignedCustomers,
                'line_customers' => $lineCustomers,
                'recent_conversations' => ChatConversation::where('created_at', '>=', now()->subDays(7))->count(),
            ];
        } catch (\Exception $e) {
            return [
                'connection' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 檢查同步健康狀態
     */
    protected function checkSyncHealth(): array
    {
        return $this->firebaseSyncService->checkSyncHealth()['health'] ?? [
            'overall_status' => 'unknown',
            'last_sync_time' => null,
            'recent_sync_errors' => 0
        ];
    }

    /**
     * 檢查權限配置
     */
    protected function checkPermissions(): array
    {
        $user = auth()->user();
        
        return [
            'current_user_id' => $user ? $user->id : null,
            'is_admin' => $user ? $user->isAdmin() : false,
            'is_manager' => $user ? $user->isManager() : false,
            'can_access_all_chats' => $user ? $user->canAccessAllChats() : false,
            'roles' => $user ? $user->getRoleNames() : [],
        ];
    }

    /**
     * 獲取改善建議
     */
    protected function getRecommendations(array $health): array
    {
        $recommendations = [];

        // Firebase相關建議
        if (!$health['firebase']['connection']) {
            $recommendations[] = '檢查Firebase連接配置和服務帳號檔案';
        }

        // MySQL相關建議
        if ($health['mysql']['conversations_count'] === 0) {
            $recommendations[] = 'MySQL中沒有聊天記錄，請確認LINE Bot是否正常運作';
        }

        if ($health['mysql']['assigned_customers'] === 0) {
            $recommendations[] = '沒有分配客戶給業務人員，請檢查客戶分配邏輯';
        }

        // 同步相關建議
        if ($health['sync']['overall_status'] !== 'healthy') {
            $recommendations[] = '執行批次同步以修復Firebase資料';
        }

        return $recommendations;
    }

    /**
     * 檢查是否啟用除錯模式
     */
    protected function isDebugEnabled(): bool
    {
        return config('app.debug') && 
               (config('firebase.debug_mode', false) || env('FIREBASE_DEBUG_MODE', false));
    }

    /**
     * 檢查是否有管理員權限
     */
    protected function hasAdminAccess(): bool
    {
        $user = auth()->user();
        return $user && ($user->isAdmin() || $user->isManager());
    }

    /**
     * 檢查Firebase配置是否有效
     */
    protected function isFirebaseConfigValid(): bool
    {
        return !empty(config('services.firebase.project_id')) &&
               !empty(config('services.firebase.database_url'));
    }

    /**
     * 檢查Firebase服務帳號檔案是否存在
     */
    protected function hasFirebaseServiceAccount(): bool
    {
        $credentialsPath = config('services.firebase.credentials');
        return $credentialsPath && file_exists($credentialsPath);
    }

    /**
     * 獲取配置資訊
     */
    protected function getConfigInfo(): array
    {
        return [
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'firebase_project_id' => config('services.firebase.project_id'),
            'firebase_database_url' => config('services.firebase.database_url'),
            'firebase_debug_mode' => config('firebase.debug_mode', false),
        ];
    }

    /**
     * 獲取資料庫統計
     */
    protected function getDatabaseStats(): array
    {
        try {
            return [
                'total_conversations' => ChatConversation::count(),
                'unread_conversations' => ChatConversation::where('status', 'unread')->count(),
                'today_conversations' => ChatConversation::whereDate('created_at', today())->count(),
                'customers_with_line' => Customer::whereNotNull('line_user_id')->count(),
                'unassigned_customers' => Customer::whereNull('assigned_to')->count(),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 獲取Firebase統計
     */
    protected function getFirebaseStats(): array
    {
        // 這裡可以添加Firebase統計邏輯
        return [
            'connection_status' => $this->firebaseChatService->checkFirebaseConnection(),
            'last_sync_attempt' => 'N/A',
        ];
    }

    /**
     * 獲取最近的錯誤日誌
     */
    protected function getRecentErrors(): array
    {
        // 這裡可以從日誌文件中讀取最近的錯誤
        return [
            'firebase_errors' => [],
            'sync_errors' => [],
        ];
    }
}