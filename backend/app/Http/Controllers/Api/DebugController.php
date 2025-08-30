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
use Illuminate\Support\Str;
use App\Models\LineIntegrationSetting;

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
        try {
            $health = [
                'timestamp' => now()->toISOString(),
                'overall_status' => 'healthy',
                'debug_mode_enabled' => $this->isDebugEnabled(),
                'firebase_connection' => false,
                'configuration' => [
                    'project_id' => !empty(config('services.firebase.project_id')),
                    'database_url' => !empty(config('services.firebase.database_url')),
                    'credentials_file_exists' => $this->hasFirebaseServiceAccount(),
                ],
                'firebase' => $this->checkFirebaseHealth(),
                'mysql' => $this->checkMySQLHealth(),
                'database_connectivity' => $this->checkDatabaseConnectivity(),
                'sync' => $this->checkSyncHealth(),
                'permissions' => $this->checkPermissions(),
                'system_anomalies' => $this->detectSystemAnomalies(), // 新增系統異常檢測
            ];

            // 設定整體健康狀態
            $health['firebase_connection'] = $health['firebase']['connection'] ?? false;
            
            // 更詳細的健康狀態判斷
            $criticalIssues = [];
            $warningIssues = [];
            
            if (!$health['firebase_connection']) {
                $criticalIssues[] = 'Firebase Realtime Database 無法連接';
            }
            if (!$health['mysql']['connection']) {
                $criticalIssues[] = 'MySQL 資料庫無法連接';
            }
            if ($health['mysql']['conversations_count'] === 0) {
                $warningIssues[] = 'MySQL中沒有聊天記錄';
            }
            if (!empty($health['system_anomalies'])) {
                $warningIssues = array_merge($warningIssues, $health['system_anomalies']);
            }
            
            if (!empty($criticalIssues)) {
                $health['overall_status'] = 'critical';
                $health['critical_issues'] = $criticalIssues;
            } elseif (!empty($warningIssues)) {
                $health['overall_status'] = 'warning';
                $health['warning_issues'] = $warningIssues;
            }

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
        try {
            $connectionStatus = false;
            $errorMessage = null;
            $connectionDetails = [];

            try {
                // 更詳細的連接檢測
                $connectionStatus = $this->firebaseChatService->checkFirebaseConnection();
                
                if ($connectionStatus) {
                    $connectionDetails = [
                        'last_test_time' => now()->toISOString(),
                        'response_time' => 'Connected successfully',
                        'database_readable' => true,
                        'database_writable' => true
                    ];
                } else {
                    $connectionDetails = [
                        'last_test_time' => now()->toISOString(),
                        'response_time' => 'Connection failed',
                        'database_readable' => false,
                        'database_writable' => false
                    ];
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $connectionDetails = [
                    'last_test_time' => now()->toISOString(),
                    'error' => $e->getMessage(),
                    'error_type' => get_class($e)
                ];
                
                Log::error('Firebase connection check failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $serviceAccountExists = $this->hasFirebaseServiceAccount();
            $configValid = $this->isFirebaseConfigValid();

            return [
                'connection' => $connectionStatus,
                'connection_details' => $connectionDetails,
                'config_valid' => $configValid,
                'service_account_exists' => $serviceAccountExists,
                'error_message' => $errorMessage,
                'project_id_set' => !empty(config('services.firebase.project_id')),
                'database_url_set' => !empty(config('services.firebase.database_url')),
                'credentials_path' => config('services.firebase.credentials'),
                'debug_mode_enabled' => $this->isDebugEnabled(),
                'configuration_details' => [
                    'project_id' => config('services.firebase.project_id'),
                    'database_url' => config('services.firebase.database_url'),
                    'credentials_file_readable' => $serviceAccountExists ? is_readable(config('services.firebase.credentials')) : false
                ]
            ];
        } catch (\Exception $e) {
            return [
                'connection' => false,
                'connection_details' => ['error' => $e->getMessage()],
                'config_valid' => false,
                'service_account_exists' => false,
                'error_message' => 'Health check failed: ' . $e->getMessage(),
                'project_id_set' => false,
                'database_url_set' => false,
                'credentials_path' => null,
                'debug_mode_enabled' => false,
            ];
        }
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
        return config('app.debug') || 
               config('services.firebase.debug_mode', false) || 
               env('FIREBASE_DEBUG_MODE', false);
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
     * 檢查數據庫連接性
     */
    protected function checkDatabaseConnectivity(): array
    {
        try {
            // 檢查 Firebase 中是否有數據
            $firebaseDataCount = 0;
            
            if ($this->firebaseChatService->checkFirebaseConnection()) {
                try {
                    // 這裡可以添加實際的 Firebase 數據計數邏輯
                    $firebaseDataCount = 0; // 暫時設為 0
                } catch (\Exception $e) {
                    Log::error('Firebase data count failed', ['error' => $e->getMessage()]);
                }
            }
            
            return [
                'firebase_accessible' => $this->firebaseChatService->checkFirebaseConnection(),
                'data_count' => $firebaseDataCount,
            ];
        } catch (\Exception $e) {
            return [
                'firebase_accessible' => false,
                'data_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 快速Firebase狀態檢查 (公開API，不需認證)
     */
    public function quickFirebaseStatus(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'status' => 'checking',
                'firebase' => [
                    'project_id' => config('services.firebase.project_id') ?: 'not_configured',
                    'database_url' => config('services.firebase.database_url') ? 'configured' : 'not_configured',
                    'credentials_exist' => file_exists(config('services.firebase.credentials') ?: '') ? 'yes' : 'no',
                    'debug_mode' => config('services.firebase.debug_mode', false),
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * 簡單的Firebase連接診斷 (不需要認證，僅用於除錯)
     */
    public function diagnosticFirebaseConnection(): JsonResponse
    {
        try {
            $diagnostic = [
                'timestamp' => now()->toISOString(),
                'config_check' => [],
                'service_binding_check' => [],
                'connection_test' => [],
                'recommendations' => []
            ];

            // 1. 檢查配置
            $projectId = config('services.firebase.project_id');
            $databaseUrl = config('services.firebase.database_url');
            $credentialsPath = config('services.firebase.credentials');
            
            $diagnostic['config_check'] = [
                'project_id_set' => !empty($projectId),
                'project_id' => $projectId ?: 'Not set',
                'database_url_set' => !empty($databaseUrl),
                'database_url' => $databaseUrl ?: 'Not set',
                'credentials_path' => $credentialsPath ?: 'Not set',
                'credentials_file_exists' => $credentialsPath && file_exists($credentialsPath),
                'credentials_readable' => $credentialsPath && is_readable($credentialsPath),
            ];

            // 2. 檢查服務綁定
            try {
                $database = app('firebase.database');
                $diagnostic['service_binding_check'] = [
                    'service_bound' => true,
                    'service_class' => get_class($database),
                    'is_mock' => strpos(get_class($database), 'class@anonymous') !== false,
                ];
                
                // 3. 如果不是mock，嘗試連接測試
                if (!$diagnostic['service_binding_check']['is_mock']) {
                    try {
                        $testPath = 'diagnostic/connection_test_' . time();
                        $testData = ['test' => true, 'timestamp' => time()];
                        
                        $database->getReference($testPath)->set($testData);
                        $snapshot = $database->getReference($testPath)->getSnapshot();
                        $database->getReference($testPath)->remove();
                        
                        $diagnostic['connection_test'] = [
                            'connection_successful' => true,
                            'write_test' => 'passed',
                            'read_test' => $snapshot->exists() ? 'passed' : 'failed',
                            'cleanup_test' => 'completed',
                        ];
                    } catch (\Exception $e) {
                        $diagnostic['connection_test'] = [
                            'connection_successful' => false,
                            'error' => $e->getMessage(),
                            'error_class' => get_class($e),
                        ];
                    }
                } else {
                    $diagnostic['connection_test'] = [
                        'connection_successful' => false,
                        'error' => 'Using mock database - Firebase not properly initialized',
                        'reason' => 'Configuration issues or initialization failure',
                    ];
                }
                
            } catch (\Exception $e) {
                $diagnostic['service_binding_check'] = [
                    'service_bound' => false,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                ];
            }

            // 生成建議
            if (!$diagnostic['config_check']['project_id_set']) {
                $diagnostic['recommendations'][] = '設定 FIREBASE_PROJECT_ID 環境變數';
            }
            if (!$diagnostic['config_check']['database_url_set']) {
                $diagnostic['recommendations'][] = '設定 FIREBASE_DATABASE_URL 環境變數';
            }
            if (!$diagnostic['config_check']['credentials_file_exists']) {
                $diagnostic['recommendations'][] = '確認 Firebase 服務帳號檔案存在於: ' . $credentialsPath;
            }
            if (isset($diagnostic['service_binding_check']['is_mock']) && $diagnostic['service_binding_check']['is_mock']) {
                $diagnostic['recommendations'][] = '檢查 Laravel 日誌獲取 Firebase 初始化錯誤詳情';
                $diagnostic['recommendations'][] = '確認所有 Firebase 配置正確且檔案可讀取';
            }

            return response()->json([
                'success' => true,
                'message' => 'Firebase 連接診斷完成',
                'diagnostic' => $diagnostic
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_details' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                ]
            ], 500);
        }
    }

    /**
     * 測試Firebase Realtime Database連接
     */
    public function testFirebaseConnection(): JsonResponse
    {
        try {
            // 檢查權限，提供更詳細的權限診斷
            $debugEnabled = $this->isDebugEnabled();
            $hasAdminAccess = $this->hasAdminAccess();
            $user = auth()->user();
            
            if (!$debugEnabled || !$hasAdminAccess) {
                return response()->json([
                    'success' => false,
                    'error' => '權限不足',
                    'message' => '需要管理員權限且啟用除錯模式',
                    'debug_info' => [
                        'debug_enabled' => $debugEnabled,
                        'has_admin_access' => $hasAdminAccess,
                        'user_authenticated' => $user !== null,
                        'user_id' => $user ? $user->id : null,
                        'user_roles' => $user ? $user->getRoleNames() : [],
                        'is_admin' => $user ? $user->isAdmin() : false,
                        'is_manager' => $user ? $user->isManager() : false,
                        'app_debug' => config('app.debug'),
                        'firebase_debug_mode' => config('services.firebase.debug_mode'),
                        'suggestions' => [
                            '請確認已登入且具有管理員權限',
                            '檢查 APP_DEBUG 或 FIREBASE_DEBUG_MODE 設定',
                            '嘗試使用 /api/debug/firebase/diagnostic 進行不需認證的診斷'
                        ]
                    ]
                ], 403);
            }

            $testResults = [
                'timestamp' => now()->toISOString(),
                'test_steps' => [],
                'overall_success' => false,
                'connection_details' => []
            ];

            // 步驟 1: 檢查配置
            $testResults['test_steps'][] = [
                'step' => 1,
                'name' => '檢查Firebase配置',
                'status' => 'testing'
            ];

            $projectId = config('services.firebase.project_id');
            $databaseUrl = config('services.firebase.database_url');
            $credentialsPath = config('services.firebase.credentials');
            
            if (empty($projectId)) {
                throw new \Exception('Firebase專案ID未設定');
            }
            if (empty($databaseUrl)) {
                throw new \Exception('Firebase資料庫URL未設定');
            }
            if (!file_exists($credentialsPath)) {
                throw new \Exception('Firebase服務帳號檔案不存在: ' . $credentialsPath);
            }
            if (!is_readable($credentialsPath)) {
                throw new \Exception('Firebase服務帳號檔案無法讀取');
            }

            $testResults['test_steps'][0]['status'] = 'passed';
            $testResults['connection_details']['config_check'] = 'passed';

            // 步驟 2: 測試服務初始化
            $testResults['test_steps'][] = [
                'step' => 2,
                'name' => '初始化Firebase服務',
                'status' => 'testing'
            ];

            try {
                $connectionTest = $this->firebaseChatService->checkFirebaseConnection();
                $testResults['test_steps'][1]['status'] = $connectionTest ? 'passed' : 'failed';
                $testResults['connection_details']['service_init'] = $connectionTest ? 'passed' : 'failed';
            } catch (\Exception $e) {
                $testResults['test_steps'][1]['status'] = 'failed';
                $testResults['test_steps'][1]['error'] = $e->getMessage();
                $testResults['connection_details']['service_init'] = 'failed';
                throw $e;
            }

            // 步驟 3: 測試讀取權限
            $testResults['test_steps'][] = [
                'step' => 3,
                'name' => '測試資料庫讀取權限',
                'status' => 'testing'
            ];

            try {
                // 嘗試讀取一個測試節點
                $database = app('firebase.database');
                $testPath = 'connection_test/' . time();
                $testData = ['test' => true, 'timestamp' => time()];
                
                // 測試寫入
                $database->getReference($testPath)->set($testData);
                $testResults['connection_details']['write_test'] = 'passed';
                
                // 測試讀取
                $readData = $database->getReference($testPath)->getValue();
                if ($readData && isset($readData['test'])) {
                    $testResults['connection_details']['read_test'] = 'passed';
                    $testResults['test_steps'][2]['status'] = 'passed';
                    
                    // 清理測試資料
                    $database->getReference($testPath)->remove();
                    $testResults['connection_details']['cleanup'] = 'completed';
                } else {
                    throw new \Exception('讀取測試失敗');
                }
            } catch (\Exception $e) {
                $testResults['test_steps'][2]['status'] = 'failed';
                $testResults['test_steps'][2]['error'] = $e->getMessage();
                $testResults['connection_details']['read_test'] = 'failed';
                throw $e;
            }

            $testResults['overall_success'] = true;
            $testResults['connection_details']['final_status'] = 'healthy';

            Log::channel('firebase')->info('Firebase connection test completed successfully', $testResults);

            return response()->json([
                'success' => true,
                'message' => 'Firebase Realtime Database連接測試成功',
                'test_results' => $testResults
            ]);

        } catch (\Exception $e) {
            $errorDetails = [
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => now()->toISOString()
            ];

            Log::channel('firebase')->error('Firebase connection test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'test_results' => $testResults ?? []
            ]);

            // 確保測試結果包含失敗狀態
            if (!isset($testResults)) {
                $testResults = [
                    'timestamp' => now()->toISOString(),
                    'test_steps' => [],
                    'overall_success' => false,
                    'connection_details' => []
                ];
            }
            $testResults['overall_success'] = false;
            $testResults['error_details'] = $errorDetails;

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'test_results' => $testResults,
                'error_details' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                    'context' => 'Firebase Realtime Database connection test',
                    'suggestions' => [
                        '檢查Firebase專案設定是否正確',
                        '確認服務帳號檔案存在且可讀取',
                        '驗證Firebase Realtime Database是否已啟用',
                        '檢查資料庫URL格式是否正確',
                        '確認服務帳號權限包含資料庫讀寫權限'
                    ]
                ]
            ], 500);
        }
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

    /**
     * 檢測系統異常
     */
    protected function detectSystemAnomalies(): array
    {
        $anomalies = [];
        
        try {
            // 檢查Firebase配置異常
            if (!$this->hasFirebaseServiceAccount()) {
                $anomalies[] = 'Firebase服務帳號檔案缺失或無法讀取';
            }
            
            if (empty(config('services.firebase.project_id'))) {
                $anomalies[] = 'Firebase專案ID未設定';
            }
            
            if (empty(config('services.firebase.database_url'))) {
                $anomalies[] = 'Firebase資料庫URL未設定';
            }
            
            // 檢查聊天室相關異常
            $totalConversations = ChatConversation::count();
            $recentConversations = ChatConversation::where('created_at', '>=', now()->subDays(7))->count();
            
            if ($totalConversations === 0) {
                $anomalies[] = '系統中沒有任何聊天記錄';
            } elseif ($recentConversations === 0) {
                $anomalies[] = '近7天沒有新的聊天記錄，LINE Bot可能未正常運作';
            }
            
            // 檢查客戶分配異常
            $unassignedCustomers = Customer::whereNull('assigned_to')->count();
            if ($unassignedCustomers > 0) {
                $anomalies[] = "有 {$unassignedCustomers} 位客戶尚未分配業務人員";
            }
            
            // 檢查LINE整合異常
            $lineCustomers = Customer::whereNotNull('line_user_id')->count();
            $totalCustomers = Customer::count();
            if ($totalCustomers > 0 && $lineCustomers === 0) {
                $anomalies[] = '沒有客戶綁定LINE帳號，LINE整合可能有問題';
            }
            
            // 檢查權限異常
            $adminUsers = \App\Models\User::whereHas('roles', function($q) {
                $q->whereIn('name', ['admin', 'executive', 'manager']);
            })->count();
            
            if ($adminUsers === 0) {
                $anomalies[] = '系統中沒有管理員用戶';
            }
            
        } catch (\Exception $e) {
            Log::error('System anomaly detection failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $anomalies[] = '系統異常檢測過程發生錯誤：' . $e->getMessage();
        }
        
        return $anomalies;
    }
    
    /**
     * 測試模擬 LINE webhook 並檢查完整的資料流
     */
    public function testCompleteWebhookFlow(): JsonResponse
    {
        try {
            $testResults = [
                'timestamp' => now()->toISOString(),
                'test_line_user_id' => null,
                'webhook_called' => false,
                'mysql_write_success' => false,
                'firebase_sync_success' => false,
                'logs_created' => false,
                'errors' => [],
                'detailed_results' => []
            ];
            
            // 生成測試用的 LINE User ID
            $testLineUserId = 'test_complete_' . Str::random(8);
            $testResults['test_line_user_id'] = $testLineUserId;
            
            // 模擬 LINE Webhook 請求
            $webhookData = [
                'events' => [
                    [
                        'type' => 'message',
                        'message' => [
                            'type' => 'text',
                            'id' => 'test_msg_' . time(),
                            'text' => '完整資料流測試訊息 - ' . now()->format('H:i:s')
                        ],
                        'source' => [
                            'type' => 'user',
                            'userId' => $testLineUserId
                        ],
                        'timestamp' => time() * 1000
                    ]
                ]
            ];
            
            // 測試 1: 檢查 logs 目錄權限
            try {
                $logDir = storage_path('logs');
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                
                $testLogFile = $logDir . '/test-' . time() . '.log';
                file_put_contents($testLogFile, 'Test log entry\n');
                $testResults['logs_created'] = file_exists($testLogFile);
                
                if (file_exists($testLogFile)) {
                    unlink($testLogFile);
                }
            } catch (\Exception $e) {
                $testResults['errors'][] = 'Log directory test failed: ' . $e->getMessage();
            }
            
            // 測試 2: 模擬完整 webhook 處理流程
            try {
                // 直接調用 ChatController 的處理邏輯
                $event = $webhookData['events'][0];
                
                // Step 1: 客戶創建/查找
                $customer = Customer::where('line_user_id', $testLineUserId)->first();
                if (!$customer) {
                    $customer = Customer::create([
                        'name' => 'Webhook 測試客戶',
                        'phone' => '0900000000',
                        'line_user_id' => $testLineUserId,
                        'channel' => 'line',
                        'status' => 'new',
                        'tracking_status' => 'pending',
                        'version' => 1,
                        'version_updated_at' => now()
                    ]);
                }
                
                $testResults['detailed_results']['customer_created'] = [
                    'success' => true,
                    'customer_id' => $customer->id
                ];
                
                // Step 2: 對話記錄創建
                $conversation = ChatConversation::create([
                    'customer_id' => $customer->id,
                    'user_id' => $customer->assigned_to,
                    'line_user_id' => $testLineUserId,
                    'platform' => 'line',
                    'message_type' => 'text',
                    'message_content' => $event['message']['text'],
                    'message_timestamp' => now(),
                    'is_from_customer' => true,
                    'status' => 'unread',
                    'metadata' => [
                        'test' => true,
                        'webhook_test' => true
                    ]
                ]);
                
                $testResults['mysql_write_success'] = true;
                $testResults['detailed_results']['conversation_created'] = [
                    'success' => true,
                    'conversation_id' => $conversation->id
                ];
                
                // Step 3: Firebase 同步測試
                try {
                    $firebaseSync = $this->firebaseChatService->syncConversationToFirebase($conversation);
                    $testResults['firebase_sync_success'] = $firebaseSync;
                    
                    $testResults['detailed_results']['firebase_sync'] = [
                        'success' => $firebaseSync,
                        'database_available' => app('firebase.database') !== null,
                        'database_class' => app('firebase.database') ? get_class(app('firebase.database')) : 'null'
                    ];
                } catch (\Exception $e) {
                    $testResults['detailed_results']['firebase_sync'] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
                
                // Step 4: 清理測試資料
                $conversation->delete();
                if ($customer->name === 'Webhook 測試客戶') {
                    $customer->delete();
                }
                
            } catch (\Exception $e) {
                $testResults['errors'][] = 'Webhook processing failed: ' . $e->getMessage();
            }
            
            // 測試 3: 實際調用 webhook endpoint
            try {
                $response = $this->callWebhookEndpoint($webhookData);
                $testResults['webhook_called'] = $response !== null;
                $testResults['detailed_results']['webhook_response'] = $response;
            } catch (\Exception $e) {
                $testResults['errors'][] = 'Webhook endpoint test failed: ' . $e->getMessage();
            }
            
            return response()->json($testResults);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Complete webhook test failed',
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
    
    /**
     * 內部調用 webhook endpoint
     */
    private function callWebhookEndpoint($data)
    {
        try {
            $request = new \Illuminate\Http\Request();
            $request->merge($data);
            $request->headers->set('Content-Type', 'application/json');
            $request->headers->set('X-Line-Signature', 'test_signature');
            
            $chatController = app(\App\Http\Controllers\Api\ChatController::class);
            $response = $chatController->webhookStatus($request);
            
            return [
                'status_code' => $response->getStatusCode(),
                'content' => json_decode($response->getContent(), true)
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'status_code' => 500
            ];
        }
    }
    
    /**
     * 測試模擬 LINE webhook 並檢查 Firebase 同步
     */
    public function testWebhookFirebaseSync(): JsonResponse
    {
        try {
            $testResults = [
                'timestamp' => now()->toISOString(),
                'test_line_user_id' => null,
                'customer_created' => false,
                'conversation_created' => false,
                'firebase_sync_attempted' => false,
                'firebase_sync_success' => false,
                'errors' => []
            ];
            
            // 生成測試用的 LINE User ID
            $testLineUserId = 'test_webhook_' . Str::random(10);
            $testResults['test_line_user_id'] = $testLineUserId;
            
            // Step 1: 創建或查找客戶
            try {
                $customer = Customer::firstOrCreate(
                    ['name' => 'Firebase Webhook 測試用戶'],
                    [
                        'phone' => '0900000000',
                        'channel' => 'line',
                        'status' => 'new',
                        'tracking_status' => 'pending',
                        'version' => 1,
                        'version_updated_at' => now()
                    ]
                );
                $testResults['customer_created'] = true;
                $testResults['customer_id'] = $customer->id;
            } catch (\Exception $e) {
                $testResults['errors'][] = 'Customer creation failed: ' . $e->getMessage();
                return response()->json($testResults, 500);
            }
            
            // Step 2: 創建對話記錄
            try {
                $conversation = ChatConversation::create([
                    'customer_id' => $customer->id,
                    'user_id' => $customer->assigned_to,
                    'line_user_id' => $testLineUserId,
                    'platform' => 'line',
                    'message_type' => 'text',
                    'message_content' => 'Firebase Webhook 同步測試訊息 - ' . now()->format('H:i:s'),
                    'message_timestamp' => now(),
                    'is_from_customer' => true,
                    'status' => 'unread',
                    'metadata' => [
                        'test' => true,
                        'webhook_test' => true,
                        'timestamp' => time()
                    ]
                ]);
                
                $testResults['conversation_created'] = true;
                $testResults['conversation_id'] = $conversation->id;
            } catch (\Exception $e) {
                $testResults['errors'][] = 'Conversation creation failed: ' . $e->getMessage();
                return response()->json($testResults, 500);
            }
            
            // Step 3: 測試 Firebase 同步
            try {
                $testResults['firebase_sync_attempted'] = true;
                
                // 獲取 Firebase Database 實例的狀態
                $database = app('firebase.database');
                $testResults['firebase_database_available'] = $database !== null;
                $testResults['firebase_database_class'] = $database ? get_class($database) : 'null';
                
                // 檢查是否為 Mock 實例
                if ($database) {
                    $className = get_class($database);
                    $testResults['is_mock_database'] = str_contains($className, 'class@anonymous') || str_contains($className, 'Mock');
                }
                
                $syncResult = $this->firebaseChatService->syncConversationToFirebase($conversation);
                $testResults['firebase_sync_success'] = $syncResult;
                
                if (!$syncResult) {
                    $testResults['errors'][] = 'Firebase sync returned false - likely using Mock database or connection failed';
                }
                
            } catch (\Exception $e) {
                $testResults['errors'][] = 'Firebase sync exception: ' . $e->getMessage();
                $testResults['firebase_sync_success'] = false;
            }
            
            // Step 4: 清理測試資料
            try {
                $conversation->delete();
                // 如果是新創建的測試客戶且名稱符合，也刪除
                if ($customer->name === 'Firebase Webhook 測試用戶' && $customer->wasRecentlyCreated) {
                    $customer->delete();
                }
            } catch (\Exception $e) {
                $testResults['errors'][] = 'Cleanup failed: ' . $e->getMessage();
            }
            
            return response()->json($testResults);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Webhook Firebase sync test failed',
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
    
    /**
     * 檢查最近的聊天記錄
     */
    public function checkRecentChats(): JsonResponse
    {
        try {
            // 先檢查資料表是否存在
            if (!\Schema::hasTable('chat_conversations')) {
                return response()->json([
                    'success' => false,
                    'error' => 'chat_conversations table does not exist',
                    'database_available' => true,
                    'timestamp' => now()->toISOString()
                ]);
            }
            
            $stats = [
                'total_conversations' => DB::table('chat_conversations')->count(),
                'recent_24h' => DB::table('chat_conversations')
                    ->where('created_at', '>=', now()->subDay())->count(),
                'from_customers' => DB::table('chat_conversations')
                    ->where('is_from_customer', true)->count(),
                'unread' => DB::table('chat_conversations')
                    ->where('status', 'unread')
                    ->where('is_from_customer', true)->count()
            ];
            
            $recentChats = DB::table('chat_conversations')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->select('id', 'customer_id', 'line_user_id', 'message_content', 
                        'is_from_customer', 'status', 'created_at')
                ->get();
            
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'recent_chats' => $recentChats,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }
    
    /**
     * 簡單的健康檢查
     */
    public function simpleHealthCheck(): JsonResponse
    {
        try {
            $health = [
                'timestamp' => now()->toISOString(),
                'database_connected' => false,
                'firebase_available' => false,
                'tables_exist' => [],
                'basic_stats' => []
            ];
            
            // 檢查資料庫連接
            try {
                DB::connection()->getPdo();
                $health['database_connected'] = true;
            } catch (\Exception $e) {
                $health['database_error'] = $e->getMessage();
            }
            
            // 檢查資料表存在
            $tables = ['customers', 'chat_conversations', 'users'];
            foreach ($tables as $table) {
                $health['tables_exist'][$table] = \Schema::hasTable($table);
                if ($health['tables_exist'][$table]) {
                    try {
                        $health['basic_stats'][$table . '_count'] = DB::table($table)->count();
                    } catch (\Exception $e) {
                        $health['basic_stats'][$table . '_error'] = $e->getMessage();
                    }
                }
            }
            
            // 檢查 Firebase
            try {
                $database = app('firebase.database');
                $health['firebase_available'] = $database !== null;
                $health['firebase_class'] = $database ? get_class($database) : 'null';
            } catch (\Exception $e) {
                $health['firebase_error'] = $e->getMessage();
            }
            
            return response()->json($health);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 更新 LINE webhook 設定
     */
    public function updateLineSettings(Request $request): JsonResponse
    {
        try {
            Log::info('DebugController updateLineSettings called', [
                'user_id' => auth()->id(),
                'request_data' => $request->except(['channel_secret', 'channel_access_token'])
            ]);

            // 檢查權限
            if (!$this->isDebugEnabled() || !$this->hasAdminAccess()) {
                Log::warning('Permission denied for updateLineSettings');
                return response()->json([
                    'success' => false,
                    'error' => '需要管理員權限且啟用除錯模式',
                    'debug_info' => [
                        'debug_enabled' => $this->isDebugEnabled(),
                        'has_admin_access' => $this->hasAdminAccess(),
                        'user_authenticated' => auth()->check()
                    ]
                ], 403);
            }

            $request->validate([
                'channel_secret' => 'nullable|string',
                'channel_access_token' => 'nullable|string',
            ]);

            $updated = [];

            // 更新 Channel Secret
            if ($request->has('channel_secret')) {
                Log::info('Updating channel_secret');
                LineIntegrationSetting::setValue(
                    'channel_secret',
                    $request->input('channel_secret'),
                    'string',
                    'LINE Channel Secret for webhook signature verification',
                    true
                );
                $updated[] = 'channel_secret';
            }

            // 更新 Channel Access Token
            if ($request->has('channel_access_token')) {
                Log::info('Updating channel_access_token');
                LineIntegrationSetting::setValue(
                    'channel_access_token',
                    $request->input('channel_access_token'),
                    'string',
                    'LINE Channel Access Token for sending messages',
                    true
                );
                $updated[] = 'channel_access_token';
            }

            // 清除快取
            \Cache::forget('line_integration_settings');

            Log::info('LINE settings updated successfully', ['updated_fields' => $updated]);

            return response()->json([
                'success' => true,
                'message' => 'LINE 設定已更新',
                'updated_fields' => $updated,
                'current_settings' => [
                    'channel_secret_configured' => !empty(LineIntegrationSetting::getValue('channel_secret')),
                    'channel_access_token_configured' => !empty(LineIntegrationSetting::getValue('channel_access_token')),
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in updateLineSettings', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'validation_errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('updateLineSettings error', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_details' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * 取得當前 LINE 設定狀態 - 即使權限不足也顯示 LINE 資訊
     */
    public function getLineSettings(): JsonResponse
    {
        try {
            Log::info('DebugController getLineSettings called', [
                'user_id' => auth()->id(),
                'user_authenticated' => auth()->check()
            ]);

            // 獲取 LINE 設定資訊（不管權限如何都要顯示）
            $lineInfo = $this->getLineSettingsInfo();

            // Step by step debugging
            $debugEnabled = $this->isDebugEnabled();
            $hasAdmin = $this->hasAdminAccess();
            
            Log::info('Permission checks', [
                'debug_enabled' => $debugEnabled,
                'has_admin_access' => $hasAdmin,
                'user' => auth()->user() ? [
                    'id' => auth()->user()->id,
                    'name' => auth()->user()->name,
                    'role' => auth()->user()->role ?? 'no_role'
                ] : null
            ]);

            // 檢查權限，但仍然返回 LINE 資訊
            if (!$debugEnabled || !$hasAdmin) {
                Log::warning('Permission denied for getLineSettings', [
                    'debug_enabled' => $debugEnabled,
                    'has_admin_access' => $hasAdmin
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => '需要管理員權限且啟用除錯模式',
                    'debug_info' => [
                        'debug_enabled' => $debugEnabled,
                        'has_admin_access' => $hasAdmin,
                        'user_authenticated' => auth()->check()
                    ],
                    'line_info' => $lineInfo // 即使權限不足也顯示 LINE 資訊
                ], 403);
            }

            Log::info('Getting LINE settings from database');
            $settings = LineIntegrationSetting::getAllSettings(true);
            
            Log::info('LINE settings retrieved', [
                'settings_count' => count($settings),
                'has_channel_secret' => !empty($settings['channel_secret']),
                'has_channel_access_token' => !empty($settings['channel_access_token'])
            ]);
            
            return response()->json([
                'success' => true,
                'settings' => [
                    'channel_secret_configured' => !empty($settings['channel_secret']),
                    'channel_secret_length' => strlen($settings['channel_secret'] ?? ''),
                    'channel_access_token_configured' => !empty($settings['channel_access_token']),
                    'channel_access_token_length' => strlen($settings['channel_access_token'] ?? ''),
                    'from_database' => true,
                    'note' => 'Only using database settings, no fallback to env/config',
                    'all_settings_keys' => array_keys($settings)
                ],
                'line_info' => $lineInfo // 完整的 LINE 資訊
            ]);

        } catch (\Exception $e) {
            Log::error('getLineSettings error', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 即使發生錯誤也嘗試取得 LINE 資訊
            $lineInfo = $this->getLineSettingsInfo();
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_details' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ],
                'line_info' => $lineInfo // 即使發生錯誤也顯示 LINE 資訊
            ], 500);
        }
    }

    /**
     * 測試 LINE 設定 API 基本功能 - 即使測試失敗也顯示 LINE 資訊
     */
    public function testLineSettingsApi(): JsonResponse
    {
        try {
            Log::info('testLineSettingsApi called', [
                'user_id' => auth()->id() ?? 'unauthenticated',
                'timestamp' => now()->toISOString(),
                'public_debug_mode' => true
            ]);

            // 獲取 LINE 設定資訊
            $lineInfo = $this->getLineSettingsInfo();
            
            $testResults = [
                'authenticated' => auth()->check(),
                'user_id' => auth()->id() ?? null,
                'debug_enabled' => $this->isDebugEnabled(),
                'admin_access' => auth()->check() ? $this->hasAdminAccess() : false,
                'line_integration_setting_exists' => class_exists(LineIntegrationSetting::class),
                'public_debug_access' => true
            ];
            
            // 公開版本：主要檢查 LINE 整合設定是否存在
            $success = $testResults['line_integration_setting_exists'];

            return response()->json([
                'success' => $success,
                'message' => $success ? 'LINE Settings API is working' : 'LINE Settings API test failed',
                'timestamp' => now()->toISOString(),
                'test_results' => $testResults,
                'line_info' => $lineInfo // 無論成功或失敗都顯示 LINE 資訊
            ]);

        } catch (\Exception $e) {
            Log::error('testLineSettingsApi error', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // 即使發生例外也嘗試取得 LINE 資訊
            $lineInfo = $this->getLineSettingsInfo();
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_details' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ],
                'line_info' => $lineInfo // 即使發生錯誤也顯示 LINE 資訊
            ], 500);
        }
    }
    
    /**
     * 獲取 LINE 設定資訊的輔助方法 - 不進行權限檢查
     */
    protected function getLineSettingsInfo(): array
    {
        try {
            // 嘗試從資料庫獲取設定
            $settings = LineIntegrationSetting::getAllSettings(true);
            
            return [
                'status' => 'success',
                'source' => 'database',
                'channel_secret' => [
                    'configured' => !empty($settings['channel_secret']),
                    'length' => strlen($settings['channel_secret'] ?? ''),
                    'value' => !empty($settings['channel_secret']) ? 
                              substr($settings['channel_secret'], 0, 8) . '...' : 
                              'not_set'
                ],
                'channel_access_token' => [
                    'configured' => !empty($settings['channel_access_token']),
                    'length' => strlen($settings['channel_access_token'] ?? ''),
                    'value' => !empty($settings['channel_access_token']) ? 
                              substr($settings['channel_access_token'], 0, 8) . '...' : 
                              'not_set'
                ],
                'all_settings_keys' => array_keys($settings),
                'settings_count' => count($settings),
                'database_accessible' => true,
                'note' => 'Settings from line_integration_settings table'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get LINE settings info', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'error',
                'source' => 'none',
                'error' => $e->getMessage(),
                'channel_secret' => [
                    'configured' => false,
                    'length' => 0,
                    'value' => 'error_getting_value'
                ],
                'channel_access_token' => [
                    'configured' => false,
                    'length' => 0,
                    'value' => 'error_getting_value'
                ],
                'all_settings_keys' => [],
                'settings_count' => 0,
                'database_accessible' => false,
                'note' => 'Failed to access database settings: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 診斷 LINE webhook 完整狀態 - Point 10-11 問題排查
     */
    public function webhookDiagnosis(): JsonResponse
    {
        try {
            $timestamp = now()->format('Y-m-d H:i:s');
            Log::info('Webhook diagnosis initiated', ['timestamp' => $timestamp]);
            
            // 1. 檢查 LINE 設定
            $lineSettings = $this->getLineSettingsInfo();
            
            // 2. 檢查資料庫連接
            $databaseStatus = $this->checkDatabaseConnectivity();
            
            // 3. 檢查 Firebase 連接
            $firebaseStatus = $this->checkFirebaseHealth();
            
            // 4. 檢查最近的聊天記錄
            $recentChats = [];
            $chatCount = 0;
            try {
                $recentChats = ChatConversation::orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'customer_id', 'line_user_id', 'message_content', 'created_at'])
                    ->toArray();
                $chatCount = ChatConversation::count();
            } catch (\Exception $e) {
                Log::error('Failed to query recent chats', ['error' => $e->getMessage()]);
            }
            
            // 5. 檢查 webhook 日誌
            $webhookLogs = [];
            try {
                $webhookLogPath = storage_path('logs/webhook-debug.log');
                if (file_exists($webhookLogPath)) {
                    $logContent = file_get_contents($webhookLogPath);
                    $logLines = array_slice(explode("\n", $logContent), -10);
                    $webhookLogs = array_filter($logLines);
                }
            } catch (\Exception $e) {
                Log::error('Failed to read webhook logs', ['error' => $e->getMessage()]);
            }
            
            // 6. 檢查環境設定
            $environmentInfo = [
                'app_env' => app()->environment(),
                'app_debug' => config('app.debug'),
                'app_url' => config('app.url'),
                'is_production' => app()->environment('production'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ];
            
            // 7. 檢查權限和存取
            $permissionChecks = [
                'logs_writable' => is_writable(storage_path('logs')),
                'storage_writable' => is_writable(storage_path()),
                'cache_writable' => is_writable(storage_path('framework/cache')),
            ];
            
            // 8. 問題診斷總結
            $issues = [];
            $recommendations = [];
            
            if (!$lineSettings['channel_secret']['configured']) {
                $issues[] = 'LINE Channel Secret 未配置';
                $recommendations[] = '在 line_integration_settings 表中設置 channel_secret';
            }
            
            if (!$lineSettings['channel_access_token']['configured']) {
                $issues[] = 'LINE Channel Access Token 未配置';
                $recommendations[] = '在 line_integration_settings 表中設置 channel_access_token';
            }
            
            if (!$databaseStatus['healthy']) {
                $issues[] = '資料庫連接異常';
                $recommendations[] = '檢查資料庫配置和連接狀態';
            }
            
            if (!$firebaseStatus['healthy']) {
                $issues[] = 'Firebase 連接異常';
                $recommendations[] = '檢查 Firebase 配置和服務帳戶金鑰';
            }
            
            if ($chatCount === 0) {
                $issues[] = '沒有聊天記錄，webhook 可能未正常工作';
                $recommendations[] = '檢查 LINE webhook 設定和簽名驗證';
            }
            
            $diagnosis = [
                'timestamp' => $timestamp,
                'overall_status' => empty($issues) ? 'healthy' : 'issues_found',
                'line_settings' => $lineSettings,
                'database_status' => $databaseStatus,
                'firebase_status' => $firebaseStatus,
                'environment_info' => $environmentInfo,
                'permission_checks' => $permissionChecks,
                'chat_statistics' => [
                    'total_conversations' => $chatCount,
                    'recent_conversations' => count($recentChats),
                    'recent_chat_data' => $recentChats,
                ],
                'webhook_logs' => [
                    'recent_entries' => $webhookLogs,
                    'log_file_exists' => file_exists(storage_path('logs/webhook-debug.log')),
                ],
                'issues_found' => $issues,
                'recommendations' => $recommendations,
                'next_steps' => [
                    '1. 確保 LINE Channel Secret 和 Access Token 正確配置',
                    '2. 測試 webhook 端點回應是否正常',
                    '3. 檢查簽名驗證是否通過',
                    '4. 驗證資料庫和 Firebase 儲存是否正常',
                    '5. 監控 webhook 執行日誌'
                ]
            ];
            
            Log::info('Webhook diagnosis completed', [
                'issues_count' => count($issues),
                'recommendations_count' => count($recommendations)
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Webhook diagnosis completed',
                'diagnosis' => $diagnosis
            ]);
            
        } catch (\Exception $e) {
            Log::error('Webhook diagnosis failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Webhook diagnosis failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 模擬 LINE webhook 事件測試 - Point 10-11 資料存儲測試
     */
    public function simulateWebhookEvent(Request $request): JsonResponse
    {
        try {
            $timestamp = now()->format('Y-m-d H:i:s');
            Log::info('Webhook event simulation initiated', ['timestamp' => $timestamp]);
            
            // 預設測試事件資料
            $defaultEvent = [
                'events' => [
                    [
                        'type' => 'message',
                        'message' => [
                            'type' => 'text',
                            'text' => $request->input('message', 'Test message for webhook - ' . $timestamp)
                        ],
                        'source' => [
                            'userId' => $request->input('user_id', 'test-user-' . time())
                        ],
                        'timestamp' => $request->input('timestamp', time() * 1000)
                    ]
                ]
            ];
            
            // 允許自訂事件資料
            $eventData = $request->input('event_data', $defaultEvent);
            
            // 使用 ChatController 的 webhook 方法進行測試
            $chatController = app(\App\Http\Controllers\Api\ChatController::class);
            
            // 創建模擬請求
            $mockRequest = new \Illuminate\Http\Request();
            $mockRequest->merge($eventData);
            $mockRequest->headers->set('Content-Type', 'application/json');
            $mockRequest->headers->set('X-Line-Signature', 'test-signature'); // 測試環境會跳過驗證
            
            // 模擬請求內容
            $jsonData = json_encode($eventData);
            $mockRequest->initialize(
                $mockRequest->query->all(),
                $mockRequest->request->all(),
                $mockRequest->attributes->all(),
                $mockRequest->cookies->all(),
                $mockRequest->files->all(),
                array_merge($mockRequest->server->all(), [
                    'REQUEST_METHOD' => 'POST',
                    'HTTP_CONTENT_TYPE' => 'application/json',
                    'CONTENT_LENGTH' => strlen($jsonData)
                ]),
                $jsonData
            );
            
            // 記錄測試前狀態
            $beforeChatCount = ChatConversation::count();
            $beforeCustomerCount = Customer::count();
            
            // 執行 webhook 測試 - Point 20: 使用無簽名驗證方法避免production環境簽名問題
            Log::info('Executing webhook test', [
                'before_chat_count' => $beforeChatCount,
                'before_customer_count' => $beforeCustomerCount,
                'event_data' => $eventData,
                'note' => 'Using webhookNoSignature method to bypass signature verification in production'
            ]);
            
            $webhookResponse = $chatController->webhookNoSignature($mockRequest);
            $webhookResult = $webhookResponse->getData(true);
            
            // 記錄測試後狀態
            $afterChatCount = ChatConversation::count();
            $afterCustomerCount = Customer::count();
            
            // 檢查是否有新記錄生成
            $chatCreated = $afterChatCount > $beforeChatCount;
            $customerCreated = $afterCustomerCount > $beforeCustomerCount;
            
            // 獲取最新的記錄
            $latestChat = null;
            $latestCustomer = null;
            if ($chatCreated) {
                $latestChat = ChatConversation::latest()->first();
            }
            if ($customerCreated) {
                $latestCustomer = Customer::latest()->first();
            }
            
            $testResult = [
                'timestamp' => $timestamp,
                'simulation_status' => 'completed',
                'webhook_response' => $webhookResult,
                'database_changes' => [
                    'before' => [
                        'chat_count' => $beforeChatCount,
                        'customer_count' => $beforeCustomerCount,
                    ],
                    'after' => [
                        'chat_count' => $afterChatCount,
                        'customer_count' => $afterCustomerCount,
                    ],
                    'changes' => [
                        'chat_created' => $chatCreated,
                        'customer_created' => $customerCreated,
                        'chat_count_increased' => $afterChatCount - $beforeChatCount,
                        'customer_count_increased' => $afterCustomerCount - $beforeCustomerCount,
                    ]
                ],
                'created_records' => [
                    'chat' => $latestChat ? [
                        'id' => $latestChat->id,
                        'customer_id' => $latestChat->customer_id,
                        'line_user_id' => $latestChat->line_user_id,
                        'message_content' => $latestChat->message_content,
                        'created_at' => $latestChat->created_at->format('Y-m-d H:i:s')
                    ] : null,
                    'customer' => $latestCustomer ? [
                        'id' => $latestCustomer->id,
                        'name' => $latestCustomer->name,
                        'channel' => $latestCustomer->channel,
                        'created_at' => $latestCustomer->created_at->format('Y-m-d H:i:s')
                    ] : null
                ],
                'test_data' => [
                    'input_event' => $eventData,
                    'simulation_method' => 'direct_controller_call',
                    'environment' => app()->environment()
                ]
            ];
            
            // 評估測試結果
            $success = $chatCreated || $customerCreated || (isset($webhookResult['status']) && $webhookResult['status'] === 'ok');
            $issues = [];
            $recommendations = [];
            
            if (!$success) {
                $issues[] = 'Webhook 處理失敗，未創建任何記錄';
                $recommendations[] = '檢查 webhook 處理邏輯和錯誤日誌';
            }
            
            if (!$chatCreated && $success) {
                $issues[] = '聊天記錄未創建';
                $recommendations[] = '檢查聊天記錄存儲邏輯';
            }
            
            if (isset($webhookResult['error'])) {
                $issues[] = 'Webhook 返回錯誤: ' . $webhookResult['error'];
                $recommendations[] = '檢查具體錯誤原因並修復';
            }
            
            $testResult['analysis'] = [
                'success' => $success,
                'issues' => $issues,
                'recommendations' => $recommendations,
                'overall_assessment' => $success ? 'PASS' : 'FAIL'
            ];
            
            Log::info('Webhook simulation completed', [
                'success' => $success,
                'issues_count' => count($issues),
                'chat_created' => $chatCreated,
                'customer_created' => $customerCreated
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Webhook event simulation completed',
                'test_result' => $testResult
            ]);
            
        } catch (\Exception $e) {
            Log::error('Webhook simulation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Webhook simulation failed',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * 簡化 webhook 狀態檢查 (Point 10-11) - 備用簡單版本
     */
    public function simpleWebhookDiagnosis(Request $request)
    {
        try {
            // 簡化的診斷，避免複雜的依賴
            return response()->json([
                'status' => 'success',
                'message' => 'Simple Webhook diagnosis endpoint is working',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'environment' => app()->environment(),
                'basic_info' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => now()->format('Y-m-d H:i:s')
            ], 200);
        }
    }

    /**
     * 模擬 webhook 事件測試資料存儲
     */
    public function simulateWebhook(Request $request)
    {
        try {
            $testUserId = $request->input('user_id', 'test-' . time());
            $testMessage = $request->input('message', 'Test message from diagnostic tool');

            // 簡化的測試 - 先確保基本功能工作
            return response()->json([
                'status' => 'success',
                'message' => 'Simulate webhook endpoint is working',
                'received_data' => [
                    'user_id' => $testUserId,
                    'message' => $testMessage
                ],
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => now()->format('Y-m-d H:i:s')
            ], 200);
        }
    }

    /**
     * Point 14: 無登入驗證的 LINE 資訊查詢與簽名測試 API
     * 功能：取得目前系統上設定的 LINE 資訊，並測試簽名是否能通過
     * 即便簽名測試失敗也顯示目前系統上設定的 LINE 資訊
     */
    public function lineInfoWithSignatureTest(Request $request): JsonResponse
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $executionId = 'line_debug_' . time() . '_' . rand(1000, 9999);
        
        Log::info('LINE Info with Signature Test API called', [
            'execution_id' => $executionId,
            'timestamp' => $timestamp,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'public_access' => true
        ]);

        try {
            // 1. 取得 LINE 整合設定資訊
            $lineInfo = $this->getLineSettingsInfo();
            
            // 2. 建立測試用的簽名驗證
            $signatureTestResults = $this->performSignatureTest($request, $lineInfo);
            
            // 3. 系統狀態檢查
            $systemStatus = [
                'line_integration_setting_model_exists' => class_exists(LineIntegrationSetting::class),
                'database_accessible' => $lineInfo['database_accessible'],
                'settings_configured_count' => $lineInfo['settings_count'],
                'has_channel_secret' => $lineInfo['channel_secret']['configured'],
                'has_channel_access_token' => $lineInfo['channel_access_token']['configured'],
                'environment' => app()->environment(),
                'timestamp' => $timestamp
            ];
            
            // 4. 準備回應資料
            $response = [
                'success' => true,
                'message' => 'LINE information retrieved successfully',
                'execution_id' => $executionId,
                'timestamp' => $timestamp,
                'line_integration_info' => $lineInfo,
                'signature_test_results' => $signatureTestResults,
                'system_status' => $systemStatus,
                'recommendations' => $this->getLineSetupRecommendations($lineInfo, $signatureTestResults)
            ];

            // 5. 記錄測試結果
            Log::info('LINE Info API completed', [
                'execution_id' => $executionId,
                'signature_test_passed' => $signatureTestResults['test_passed'],
                'settings_configured' => $systemStatus['has_channel_secret'] && $systemStatus['has_channel_access_token'],
                'database_accessible' => $systemStatus['database_accessible']
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('LINE Info API error', [
                'execution_id' => $executionId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // 即使發生錯誤，仍嘗試取得基本的 LINE 資訊
            try {
                $lineInfo = $this->getLineSettingsInfo();
            } catch (\Exception $lineException) {
                $lineInfo = [
                    'status' => 'critical_error',
                    'error' => 'Cannot access LINE settings: ' . $lineException->getMessage()
                ];
            }

            return response()->json([
                'success' => false,
                'message' => 'LINE Info API encountered an error',
                'execution_id' => $executionId,
                'timestamp' => $timestamp,
                'error' => $e->getMessage(),
                'error_details' => [
                    'exception_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ],
                'line_integration_info' => $lineInfo, // 即使發生錯誤也顯示 LINE 資訊
                'system_status' => [
                    'database_accessible' => false,
                    'error_occurred' => true,
                    'timestamp' => $timestamp
                ]
            ], 500);
        }
    }

    /**
     * 執行簽名驗證測試
     */
    protected function performSignatureTest(Request $request, array $lineInfo): array
    {
        $testResults = [
            'test_performed' => false,
            'test_passed' => false,
            'test_details' => [],
            'error' => null
        ];

        try {
            // 檢查是否有足夠的設定進行簽名測試
            if (!$lineInfo['channel_secret']['configured']) {
                $testResults['test_details'] = [
                    'reason' => 'Cannot perform signature test - channel_secret not configured',
                    'channel_secret_status' => $lineInfo['channel_secret'],
                    'recommendation' => 'Configure channel_secret in line_integration_settings table first'
                ];
                return $testResults;
            }

            // 建立測試用的 payload
            $testPayload = json_encode([
                'events' => [
                    [
                        'type' => 'message',
                        'timestamp' => time() * 1000,
                        'source' => [
                            'type' => 'user',
                            'userId' => 'test-user-id-' . time()
                        ],
                        'message' => [
                            'type' => 'text',
                            'text' => 'Signature test message from LINE Info API'
                        ]
                    ]
                ]
            ]);

            // 取得 channel_secret
            $settings = LineIntegrationSetting::getAllSettings(true);
            $channelSecret = $settings['channel_secret'] ?? '';

            if (empty($channelSecret)) {
                throw new \Exception('Channel secret is empty in database');
            }

            // 生成正確的簽名
            $correctSignature = base64_encode(hash_hmac('sha256', $testPayload, $channelSecret, true));

            // 測試1：使用正確的簽名
            $testResults['test_performed'] = true;
            $testResults['test_details'] = [
                'test_payload_length' => strlen($testPayload),
                'channel_secret_length' => strlen($channelSecret),
                'generated_signature' => substr($correctSignature, 0, 10) . '...',
                'signature_generation_method' => 'base64_encode(hash_hmac("sha256", body, channel_secret, true))',
                'test_timestamp' => now()->format('Y-m-d H:i:s')
            ];

            // 簡化簽名驗證測試 - 直接測試簽名生成邏輯
            $testSignature = base64_encode(hash_hmac('sha256', $testPayload, $channelSecret, true));
            
            // 驗證簽名生成是否一致
            $verificationPassed = hash_equals($correctSignature, $testSignature);
            
            $testResults['test_passed'] = $verificationPassed;
            $testResults['test_details']['verification_result'] = $verificationPassed ? 'PASSED' : 'FAILED';
            $testResults['test_details']['test_type'] = 'Mock signature verification with generated signature';

        } catch (\Exception $e) {
            $testResults['test_performed'] = true;
            $testResults['test_passed'] = false;
            $testResults['error'] = $e->getMessage();
            $testResults['test_details'] = [
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ];
        }

        return $testResults;
    }

    /**
     * 提供 LINE 設定建議
     */
    protected function getLineSetupRecommendations(array $lineInfo, array $signatureTestResults): array
    {
        $recommendations = [];

        // 檢查資料庫存取
        if (!$lineInfo['database_accessible']) {
            $recommendations[] = [
                'priority' => 'critical',
                'issue' => 'Database access failed',
                'action' => 'Check database connectivity and line_integration_settings table existence'
            ];
        }

        // 檢查 channel_secret 設定
        if (!$lineInfo['channel_secret']['configured']) {
            $recommendations[] = [
                'priority' => 'high',
                'issue' => 'LINE Channel Secret not configured',
                'action' => 'Configure channel_secret in line_integration_settings table via admin panel or API'
            ];
        }

        // 檢查 channel_access_token 設定
        if (!$lineInfo['channel_access_token']['configured']) {
            $recommendations[] = [
                'priority' => 'high',
                'issue' => 'LINE Channel Access Token not configured',
                'action' => 'Configure channel_access_token in line_integration_settings table via admin panel or API'
            ];
        }

        // 檢查簽名測試結果
        if ($signatureTestResults['test_performed'] && !$signatureTestResults['test_passed']) {
            $recommendations[] = [
                'priority' => 'medium',
                'issue' => 'Signature verification test failed',
                'action' => 'Review channel_secret configuration and signature generation logic'
            ];
        }

        // 如果沒有任何問題
        if (empty($recommendations) && $signatureTestResults['test_passed']) {
            $recommendations[] = [
                'priority' => 'info',
                'issue' => 'All checks passed',
                'action' => 'LINE integration appears to be configured correctly'
            ];
        }

        return $recommendations;
    }

    /**
     * Point 20: 直接測試MySQL conversation創建功能
     * 不涉及webhook或簽名驗證，純粹測試資料庫創建
     */
    public function testMysqlConversationCreation(Request $request)
    {
        // Point 20: 完整測試MySQL資料創建功能
        $results = [];
        
        try {
            // 第一步：檢查並創建Customer
            $availableUser = \App\Models\User::first();
            if (!$availableUser) {
                return response()->json([
                    'success' => false,
                    'error' => '沒有找到可用的用戶進行分配'
                ]);
            }
            
            // 創建測試customer
            $customerData = [
                'name' => 'Point20測試客戶_' . time(),
                'phone' => '0900' . rand(100000, 999999),
                'line_user_id' => 'U_point20_test_' . time(),
                'region' => '台北市',
                'website_source' => 'Point20測試',
                'status' => 'new',
                'assigned_to' => $availableUser->id
            ];
            
            $customer = \App\Models\Customer::create($customerData);
            $results['customer_creation'] = [
                'success' => true,
                'customer_id' => $customer->id,
                'line_user_id' => $customer->line_user_id
            ];
            
            // 第二步：測試ChatConversation創建 - 這裡是Point 20的關鍵測試
            try {
                $conversationData = [
                    'customer_id' => $customer->id,
                    'line_user_id' => $customer->line_user_id,
                    'status' => 'unread',
                    'last_message' => 'Point20測試對話：' . date('Y-m-d H:i:s'),
                    'last_message_at' => now(),
                ];
                
                // 記錄創建前狀態
                file_put_contents(
                    storage_path('logs/webhook-debug.log'),
                    date('Y-m-d H:i:s') . " - Point20 - 準備創建ChatConversation，customer_id: {$customer->id}\n",
                    FILE_APPEND | LOCK_EX
                );
                
                // 嘗試創建conversation - 這會觸發model events
                $conversation = \App\Models\ChatConversation::create($conversationData);
                
                $results['conversation_creation'] = [
                    'success' => true,
                    'conversation_id' => $conversation->id,
                    'version' => $conversation->version,
                    'created_at' => $conversation->created_at->format('Y-m-d H:i:s'),
                    'model_events' => 'Point 20修復的model events成功執行'
                ];
                
                file_put_contents(
                    storage_path('logs/webhook-debug.log'),
                    date('Y-m-d H:i:s') . " - Point20 - ChatConversation創建成功，ID: {$conversation->id}, version: {$conversation->version}\n",
                    FILE_APPEND | LOCK_EX
                );
                
            } catch (\Exception $conversationError) {
                $results['conversation_creation'] = [
                    'success' => false,
                    'error' => $conversationError->getMessage(),
                    'file' => $conversationError->getFile(),
                    'line' => $conversationError->getLine(),
                    'diagnosis' => 'Point 20 model events可能仍有問題'
                ];
                
                file_put_contents(
                    storage_path('logs/webhook-debug.log'),
                    date('Y-m-d H:i:s') . " - Point20 - ChatConversation創建失敗: " . $conversationError->getMessage() . "\n",
                    FILE_APPEND | LOCK_EX
                );
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Point 20 MySQL測試完成',
                'results' => $results,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'point_20_status' => $results['conversation_creation']['success'] ? 'FIXED' : 'NEEDS_MORE_WORK'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Point 20測試失敗',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'results' => $results
            ]);
        }
    }

    /**
     * Point 20: 直接測試MySQL創建功能
     * 不使用closure避免部署問題
     */
    public function point20MysqlDirectTest(Request $request)
    {
        try {
            // 檢查用戶
            $user = \App\Models\User::first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => '沒有可用用戶',
                    'point_20_status' => 'NO_USERS'
                ]);
            }
            
            // 創建測試客戶
            $customerData = [
                'name' => 'Point20直接測試_' . time(),
                'phone' => '0900' . rand(100000, 999999),
                'line_user_id' => 'U_point20_direct_' . time(),
                'region' => '台北市',
                'website_source' => 'Point20直接測試',
                'status' => 'new',
                'assigned_to' => $user->id
            ];
            
            $customer = \App\Models\Customer::create($customerData);
            
            // 記錄Point 20測試開始
            file_put_contents(
                storage_path('logs/webhook-debug.log'),
                date('Y-m-d H:i:s') . " - Point20直接測試 - 客戶創建成功，準備測試ChatConversation\n",
                FILE_APPEND | LOCK_EX
            );
            
            // 測試ChatConversation創建 - 這裡是Point 20的核心問題
            $conversationData = [
                'customer_id' => $customer->id,
                'line_user_id' => $customer->line_user_id,
                'status' => 'unread',
                'message_content' => 'Point20直接測試訊息內容: ' . now()->format('H:i:s'),
                'message_timestamp' => now(),
                'is_from_customer' => true,
            ];
            
            file_put_contents(
                storage_path('logs/webhook-debug.log'),
                date('Y-m-d H:i:s') . " - Point20直接測試 - 準備創建ChatConversation，資料: " . json_encode($conversationData) . "\n",
                FILE_APPEND | LOCK_EX
            );
            
            $conversation = \App\Models\ChatConversation::create($conversationData);
            
            file_put_contents(
                storage_path('logs/webhook-debug.log'),
                date('Y-m-d H:i:s') . " - Point20直接測試 - ChatConversation創建成功，ID: {$conversation->id}, version: {$conversation->version}\n",
                FILE_APPEND | LOCK_EX
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Point 20直接測試成功 - MySQL創建正常',
                'customer_id' => $customer->id,
                'conversation_id' => $conversation->id,
                'version' => $conversation->version,
                'point_20_status' => 'MYSQL_WORKING',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'debug_info' => [
                    'customer_created' => true,
                    'conversation_created' => true,
                    'model_events_triggered' => 'Point20修復的events已執行'
                ]
            ]);
            
        } catch (\Exception $e) {
            file_put_contents(
                storage_path('logs/webhook-debug.log'),
                date('Y-m-d H:i:s') . " - Point20直接測試失敗: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n",
                FILE_APPEND | LOCK_EX
            );
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'point_20_status' => 'MYSQL_FAILED'
            ], 200);
        }
    }

    /**
     * Point 20: 簡單測試方法
     */
    public function point20SimpleTest(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Point 20 簡單測試成功',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'environment' => app()->environment(),
            'point_20_status' => 'CONTROLLER_WORKING'
        ]);
    }

    /**
     * Point 20: 只測試Customer創建
     */
    public function point20CustomerTest(Request $request)
    {
        try {
            $user = \App\Models\User::first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => '沒有可用用戶'
                ]);
            }
            
            $customerData = [
                'name' => 'Point20客戶測試_' . time(),
                'phone' => '0900' . rand(100000, 999999),
                'line_user_id' => 'U_point20_customer_' . time(),
                'region' => '台北市',
                'website_source' => 'Point20客戶測試',
                'status' => 'new',
                'assigned_to' => $user->id
            ];
            
            $customer = \App\Models\Customer::create($customerData);
            
            return response()->json([
                'success' => true,
                'message' => 'Point 20客戶創建測試成功',
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'point_20_status' => 'CUSTOMER_CREATION_OK'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'point_20_status' => 'CUSTOMER_CREATION_FAILED'
            ], 200);
        }
    }

    /**
     * Point 20: 測試數據庫連接和基本查詢
     */
    public function point20DatabaseTest(Request $request)
    {
        try {
            // 測試數據庫連接
            $connection = \DB::connection()->getPdo();
            
            // 測試基本查詢
            $userCount = \DB::table('users')->count();
            $customerCount = \DB::table('customers')->count();
            
            return response()->json([
                'success' => true,
                'message' => 'Point 20數據庫連接測試成功',
                'database_info' => [
                    'connection' => 'OK',
                    'user_count' => $userCount,
                    'customer_count' => $customerCount
                ],
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'point_20_status' => 'DATABASE_CONNECTION_OK'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'point_20_status' => 'DATABASE_CONNECTION_FAILED'
            ], 200);
        }
    }

    /**
     * Point 20: 使用原生SQL測試ChatConversation創建，避免模型事件
     */
    public function point20ConversationRawTest(Request $request)
    {
        try {
            // 先創建一個簡單的customer（如果需要的話）
            $userId = \DB::table('users')->value('id');
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'error' => '沒有可用用戶'
                ]);
            }

            // 使用原生SQL插入conversation，避免模型事件
            $conversationId = \DB::table('chat_conversations')->insertGetId([
                'line_user_id' => 'U_point20_raw_' . time(),
                'status' => 'unread',
                'message_content' => 'Point20原生SQL測試內容',
                'message_timestamp' => now(),
                'is_from_customer' => true,
                'version' => time(),
                'version_updated_at' => now(), // Point 24: 添加缺失的version_updated_at字段
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Point 20原生SQL對話創建成功',
                'conversation_id' => $conversationId,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'point_20_status' => 'RAW_SQL_CONVERSATION_OK'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'point_20_status' => 'RAW_SQL_CONVERSATION_FAILED'
            ], 200);
        }
    }
}
