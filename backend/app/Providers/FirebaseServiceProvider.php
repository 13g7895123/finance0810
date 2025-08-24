<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase\Contract\Firestore;
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
            return $app['firebase.factory']->createFirestore();
        });

        $this->app->singleton(FirebaseAuth::class, function ($app) {
            return $app['firebase.factory']->createAuth();
        });
        
        // 別名綁定
        $this->app->alias(Firestore::class, 'firebase.firestore');
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
    }
}