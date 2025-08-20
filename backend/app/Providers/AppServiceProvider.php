<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\CustomerCase;
use App\Observers\CustomerCaseObserver;
use App\Models\ChatConversation;
use App\Models\Customer;
use App\Observers\VersionedModelObserver;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CustomerCase::observe(CustomerCaseObserver::class);
        
        // 註冊版本追踪觀察者
        ChatConversation::observe(VersionedModelObserver::class);
        Customer::observe(VersionedModelObserver::class);
        
        // 只在開發環境啟用查詢監控
        if (config('app.debug')) {
            app(QueryPerformanceMonitor::class)->monitor();
        }
        
        // 註冊緩存清除事件
        ChatConversation::created(function ($conversation) {
            app(ChatQueryCacheService::class)->clearConversationCache($conversation->line_user_id);
        });
        
        ChatConversation::updated(function ($conversation) {
            app(ChatQueryCacheService::class)->clearConversationCache($conversation->line_user_id);
        });
        
        ChatConversation::deleted(function ($conversation) {
            app(ChatQueryCacheService::class)->clearConversationCache($conversation->line_user_id);
        });
    }
}