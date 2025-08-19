<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ChatQueryCacheService;
use App\Services\QueryPerformanceMonitor;
use App\Models\ChatConversation;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChatQueryOptimizationTest extends TestCase
{
    use RefreshDatabase;
    
    private $cacheService;
    private $performanceMonitor;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = new ChatQueryCacheService();
        $this->performanceMonitor = new QueryPerformanceMonitor();
    }
    
    public function test_cache_conversation_list()
    {
        $userId = 1;
        
        // 第一次調用 - cache miss
        $result1 = $this->cacheService->getConversationList($userId);
        
        // 第二次調用 - cache hit
        $result2 = $this->cacheService->getConversationList($userId);
        
        // 結果應該相同
        $this->assertEquals($result1, $result2);
        
        // 檢查緩存是否存在
        $cacheKey = 'chat_query_conversations_' . $userId;
        $this->assertTrue(Cache::has($cacheKey));
    }
    
    public function test_bulk_unread_count_performance()
    {
        // 創建測試數據
        $customers = Customer::factory(10)->create();
        $lineUserIds = $customers->pluck('line_user_id')->toArray();
        
        foreach ($lineUserIds as $lineUserId) {
            ChatConversation::factory(5)->create([
                'line_user_id' => $lineUserId,
                'status' => 'unread',
                'is_from_customer' => true
            ]);
        }
        
        $startTime = microtime(true);
        $counts = $this->cacheService->getUnreadCounts($lineUserIds);
        $elapsed = microtime(true) - $startTime;
        
        // 批量查詢應該在合理時間內完成
        $this->assertLessThan(1.0, $elapsed); // 1秒內
        $this->assertCount(10, $counts);
        
        // 檢查結果正確性
        foreach ($counts as $count) {
            $this->assertEquals(5, $count);
        }
    }
    
    public function test_optimized_message_query()
    {
        $customer = Customer::factory()->create();
        $lineUserId = $customer->line_user_id;
        
        // 創建測試訊息
        ChatConversation::factory(20)->create([
            'line_user_id' => $lineUserId,
            'customer_id' => $customer->id
        ]);
        
        $messages = $this->cacheService->getOptimizedMessages($lineUserId, 10, 0);
        
        $this->assertCount(10, $messages);
        $this->assertEquals($lineUserId, $messages->first()->line_user_id);
    }
    
    public function test_cache_clearing_on_conversation_update()
    {
        $customer = Customer::factory()->create();
        $lineUserId = $customer->line_user_id;
        
        // 建立緩存
        $this->cacheService->getUnreadCounts([$lineUserId]);
        $cacheKey = 'chat_query_unread_' . $lineUserId;
        $this->assertTrue(Cache::has($cacheKey));
        
        // 創建新對話（應該清除緩存）
        ChatConversation::factory()->create([
            'line_user_id' => $lineUserId,
            'customer_id' => $customer->id
        ]);
        
        // 緩存應該被清除
        $this->assertFalse(Cache::has($cacheKey));
    }
    
    public function test_query_performance_monitor_setup()
    {
        // 測試性能監控器設置
        $stats = $this->performanceMonitor->getQueryStats();
        
        $this->assertArrayHasKey('slow_query_threshold', $stats);
        $this->assertArrayHasKey('monitoring_enabled', $stats);
        $this->assertIsInt($stats['slow_query_threshold']);
        $this->assertIsBool($stats['monitoring_enabled']);
    }
    
    public function test_cache_service_stats()
    {
        // 測試緩存統計
        $stats = $this->cacheService->getCacheStats();
        
        $this->assertArrayHasKey('total_keys', $stats);
        $this->assertIsInt($stats['total_keys']);
    }
    
    public function test_conversation_list_with_permissions()
    {
        $user = User::factory()->create(['role' => 'staff']);
        $customer = Customer::factory()->create(['assigned_to' => $user->id]);
        
        ChatConversation::factory(3)->create([
            'line_user_id' => $customer->line_user_id,
            'customer_id' => $customer->id
        ]);
        
        // 測試權限過濾
        $conversations = $this->cacheService->getConversationList($user->id);
        
        $this->assertNotEmpty($conversations);
        foreach ($conversations as $conv) {
            $this->assertEquals($customer->line_user_id, $conv->line_user_id);
        }
    }
    
    public function test_cache_force_refresh()
    {
        $userId = 1;
        
        // 建立初始緩存
        $result1 = $this->cacheService->getConversationList($userId);
        
        // 強制刷新
        $result2 = $this->cacheService->getConversationList($userId, true);
        
        // 應該重新查詢數據
        $this->assertTrue(true); // 這裡主要測試沒有拋出異常
    }
    
    public function test_unread_count_cache_partial_hit()
    {
        $lineUserIds = ['user1', 'user2', 'user3'];
        
        // 預先緩存一部分數據
        Cache::put('chat_query_unread_user1', 5, 60);
        
        $counts = $this->cacheService->getUnreadCounts($lineUserIds);
        
        $this->assertArrayHasKey('user1', $counts);
        $this->assertArrayHasKey('user2', $counts);
        $this->assertArrayHasKey('user3', $counts);
        $this->assertEquals(5, $counts['user1']); // 來自緩存
        $this->assertEquals(0, $counts['user2']); // 查詢結果
        $this->assertEquals(0, $counts['user3']); // 查詢結果
    }
}