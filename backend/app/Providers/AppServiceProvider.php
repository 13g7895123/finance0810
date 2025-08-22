<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\CustomerCase;
use App\Observers\CustomerCaseObserver;
use App\Models\ChatConversation;
use App\Models\Customer;
use App\Observers\VersionedModelObserver;
use App\Observers\CustomerObserver;
use App\Services\QueryPerformanceMonitor;
use App\Services\ChatQueryCacheService;
use App\Services\VersionTrackingService;
use App\Services\IncrementalSyncService;
use App\Services\ChatVersionService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 註冊版本追踪服務
        $this->app->singleton(VersionTrackingService::class);
        
        // 註冊增量同步服務
        $this->app->singleton(IncrementalSyncService::class);
        
        // 註冊聊天版本服務
        $this->app->singleton(ChatVersionService::class);
        
        // 註冊聊天查詢緩存服務
        $this->app->singleton(ChatQueryCacheService::class);
        
        // 註冊查詢性能監控服務
        $this->app->singleton(QueryPerformanceMonitor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            CustomerCase::observe(CustomerCaseObserver::class);
            
            // 註冊版本追踪觀察者（僅當模型存在時）
            if (class_exists(ChatConversation::class)) {
                ChatConversation::observe(VersionedModelObserver::class);
            }
            
            if (class_exists(Customer::class)) {
                Customer::observe(VersionedModelObserver::class);
                Customer::observe(CustomerObserver::class);
            }
            
            // 只在開發環境啟用查詢監控
            if (config('app.debug')) {
                try {
                    app(QueryPerformanceMonitor::class)->monitor();
                } catch (\Exception $e) {
                    \Log::warning('Failed to initialize query performance monitor: ' . $e->getMessage());
                }
            }
            
            // 註冊緩存清除事件（安全模式）
            if (class_exists(ChatConversation::class)) {
                ChatConversation::created(function ($conversation) {
                    try {
                        app(ChatQueryCacheService::class)->clearConversationCache($conversation->line_user_id);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to clear conversation cache on create: ' . $e->getMessage());
                    }
                });
                
                ChatConversation::updated(function ($conversation) {
                    try {
                        app(ChatQueryCacheService::class)->clearConversationCache($conversation->line_user_id);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to clear conversation cache on update: ' . $e->getMessage());
                    }
                });
                
                ChatConversation::deleted(function ($conversation) {
                    try {
                        app(ChatQueryCacheService::class)->clearConversationCache($conversation->line_user_id);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to clear conversation cache on delete: ' . $e->getMessage());
                    }
                });
            }
            
        } catch (\Exception $e) {
            \Log::error('AppServiceProvider boot error: ' . $e->getMessage());
            // 不要讓 provider 啟動錯誤導致整個應用程式崩潰
        }
    }
}