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
                return $app['firebase.factory']->createDatabase();
            } catch (\Exception $e) {
                // 在開發環境或缺少依賴時，記錄錯誤但不中斷應用啟動
                \Log::warning('Failed to create Firebase Database client: ' . $e->getMessage());
                return null;
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
        
        // 別名綁定
        $this->app->alias(Firestore::class, 'firebase.firestore');
        $this->app->alias(Database::class, 'firebase.database');
        $this->app->alias(FirebaseAuth::class, 'firebase.auth');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 檢查 Firebase 配置
        if (!config('services.firebase.project_id')) {
            \Log::warning('Firebase Project ID not configured. Firebase services may not work properly.');
        }

        if (!config('services.firebase.credentials') || !file_exists(config('services.firebase.credentials'))) {
            \Log::warning('Firebase credentials file not found. Firebase services may not work properly.');
        }

        if (!config('services.firebase.database_url')) {
            \Log::warning('Firebase Database URL not configured. Realtime Database services may not work properly.');
        }
    }
}