<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase\Contract\Firestore;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('firebase.factory', function ($app) {
            $factory = (new Factory());
            
            // 載入服務帳戶金鑰
            $credentialsPath = config('services.firebase.credentials');
            if ($credentialsPath && file_exists($credentialsPath)) {
                $factory = $factory->withServiceAccount($credentialsPath);
            }
            
            // 設定專案 ID
            $projectId = config('services.firebase.project_id');
            if ($projectId) {
                $factory = $factory->withProjectId($projectId);
            }
            
            // 設定資料庫 URL (如果有)
            $databaseUrl = config('services.firebase.database_url');
            if ($databaseUrl) {
                $factory = $factory->withDatabaseUri($databaseUrl);
            }
            
            return $factory;
        });

        $this->app->singleton(Firestore::class, function ($app) {
            try {
                return $app['firebase.factory']->createFirestore();
            } catch (\Exception $e) {
                // 在開發環境或缺少依賴時，記錄錯誤但不中斷應用啟動
                \Log::warning('Failed to create Firestore client: ' . $e->getMessage());
                
                // 返回一個假的 Firestore 實例或 null
                // 具體的服務類別應該檢查是否為 null 並優雅地處理
                return null;
            }
        });

        $this->app->singleton(Database::class, function ($app) {
            try {
                $database = $app['firebase.factory']->createDatabase();
                if ($database === null) {
                    throw new \RuntimeException('Firebase Database instance is null');
                }
                return $database;
            } catch (\Exception $e) {
                // 記錄詳細錯誤信息
                \Log::error('Failed to create Firebase Database client', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'config' => [
                        'project_id' => config('services.firebase.project_id'),
                        'database_url' => config('services.firebase.database_url'),
                        'credentials_exist' => file_exists(config('services.firebase.credentials') ?: '')
                    ]
                ]);
                
                // 返回一個 Mock 實例而不是 null，避免綁定錯誤
                return $this->createMockDatabase();
            }
        });

        $this->app->singleton(FirebaseAuth::class, function ($app) {
            try {
                return $app['firebase.factory']->createAuth();
            } catch (\Exception $e) {
                // 在開發環境或缺少依賴時，記錄錯誤但不中斷應用啟動
                \Log::warning('Failed to create Firebase Auth client: ' . $e->getMessage());
                return null;
            }
        });
        
        // 直接建立別名 - 服務已經註冊，無論成功或失敗都建立別名
        $this->app->alias(Firestore::class, 'firebase.firestore');
        $this->app->alias(Database::class, 'firebase.database');
        $this->app->alias(FirebaseAuth::class, 'firebase.auth');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 詳細的 Firebase 配置檢查
        $this->validateFirebaseConfiguration();
        
        // 測試 Firebase 服務可用性
        $this->testFirebaseServices();
    }
    
    /**
     * 驗證 Firebase 配置
     */
    protected function validateFirebaseConfiguration(): void
    {
        $issues = [];
        
        if (!config('services.firebase.project_id')) {
            $issues[] = 'Firebase Project ID not configured';
        }
        
        $credentialsPath = config('services.firebase.credentials');
        if (!$credentialsPath) {
            $issues[] = 'Firebase credentials path not configured';
        } elseif (!file_exists($credentialsPath)) {
            $issues[] = "Firebase credentials file not found at: {$credentialsPath}";
        }
        
        if (!config('services.firebase.database_url')) {
            $issues[] = 'Firebase Database URL not configured';
        }
        
        if (!empty($issues)) {
            \Log::warning('Firebase configuration issues detected', [
                'issues' => $issues,
                'config' => [
                    'project_id' => config('services.firebase.project_id'),
                    'database_url' => config('services.firebase.database_url'),
                    'credentials_path' => $credentialsPath
                ]
            ]);
        }
    }
    
    /**
     * 測試 Firebase 服務可用性
     */
    protected function testFirebaseServices(): void
    {
        try {
            // 測試 Database 服務
            if ($this->app->bound('firebase.database')) {
                \Log::info('Firebase Database service is bound and available');
            } else {
                \Log::warning('Firebase Database service is not bound');
            }
            
            // 測試實際連接（僅在非生產環境）
            if (!app()->isProduction()) {
                $this->testDatabaseConnection();
            }
            
        } catch (\Exception $e) {
            \Log::error('Firebase service test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 測試資料庫連接
     */
    protected function testDatabaseConnection(): void
    {
        try {
            $database = $this->app->make('firebase.database');
            // 嘗試獲取根引用來測試連接
            $database->getReference('.info/connected');
            \Log::info('Firebase Database connection test passed');
        } catch (\Exception $e) {
            \Log::warning('Firebase Database connection test failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 創建 Mock Firebase Database 實例
     */
    protected function createMockDatabase()
    {
        return new class implements \Kreait\Firebase\Contract\Database 
        {
            public function getReference(string $path = null): \Kreait\Firebase\Database\Reference 
            {
                throw new \RuntimeException('Firebase Database not available. Path: ' . ($path ?? 'root'));
            }
            
            public function getReferenceFromUrl(string $url): \Kreait\Firebase\Database\Reference 
            {
                throw new \RuntimeException('Firebase Database not available. URL: ' . $url);
            }
        };
    }
}