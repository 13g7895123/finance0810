<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\CustomerCase;
use App\Observers\CustomerCaseObserver;
use App\Models\ChatConversation;
use App\Services\QueryPerformanceMonitor;
use App\Services\ChatQueryCacheService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CustomerCase::observe(CustomerCaseObserver::class);
        
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