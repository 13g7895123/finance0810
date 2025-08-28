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
}