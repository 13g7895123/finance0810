<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\ChatConversation;
use App\Services\ChatVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class ChatPollUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $versionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 運行遷移
        $this->artisan('migrate');
        
        // 創建測試用戶
        $this->user = User::factory()->create();
        $this->versionService = app(ChatVersionService::class);
    }

    public function test_poll_updates_returns_current_version_on_timeout()
    {
        $this->actingAs($this->user, 'api');
        
        // 獲取當前版本
        $initialVersion = $this->versionService->getCurrentVersion();
        
        // 第一次請求，應該超時並返回當前版本
        $response = $this->getJson("/api/chats/poll-updates?version={$initialVersion}&timeout=1");
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [],
            'timeout' => true
        ]);
        
        $responseData = $response->json();
        $this->assertArrayHasKey('version', $responseData);
        $this->assertEquals($initialVersion, $responseData['version']);
    }

    public function test_poll_updates_returns_new_changes()
    {
        $this->actingAs($this->user, 'api');
        
        // 獲取當前版本
        $initialVersion = $this->versionService->getCurrentVersion();
        
        // 創建新訊息
        $conversation = ChatConversation::create([
            'line_user_id' => 'TEST123',
            'message_content' => 'Test message',
            'message_timestamp' => now(),
            'is_from_customer' => true,
            'status' => 'unread',
            'platform' => 'line',
            'message_type' => 'text'
        ]);
        
        // 請求應該返回新訊息
        $response = $this->getJson("/api/chats/poll-updates?version={$initialVersion}&timeout=1");
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('Test message', $data[0]['data']['content']);
        $this->assertEquals($conversation->version, $data[0]['data']['version']);
    }

    public function test_poll_updates_filters_by_line_user_id()
    {
        $this->actingAs($this->user, 'api');
        
        $initialVersion = $this->versionService->getCurrentVersion();
        
        // 創建兩個不同用戶的訊息
        ChatConversation::create([
            'line_user_id' => 'USER1',
            'message_content' => 'Message for user 1',
            'message_timestamp' => now(),
            'is_from_customer' => true,
            'status' => 'unread',
            'platform' => 'line',
            'message_type' => 'text'
        ]);
        
        ChatConversation::create([
            'line_user_id' => 'USER2',
            'message_content' => 'Message for user 2',
            'message_timestamp' => now(),
            'is_from_customer' => true,
            'status' => 'unread',
            'platform' => 'line',
            'message_type' => 'text'
        ]);
        
        // 只請求 USER1 的更新
        $response = $this->getJson("/api/chats/poll-updates?version={$initialVersion}&line_user_id=USER1&timeout=1");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // 應該只返回 USER1 的訊息
        $this->assertCount(1, $data);
        $this->assertEquals('USER1', $data[0]['data']['line_user_id']);
        $this->assertEquals('Message for user 1', $data[0]['data']['content']);
    }

    public function test_version_service_increments_correctly()
    {
        $initialVersion = $this->versionService->getCurrentVersion();
        
        // 創建訊息應該自動增加版本號
        $conversation = ChatConversation::create([
            'line_user_id' => 'TEST123',
            'message_content' => 'Test message',
            'message_timestamp' => now(),
            'is_from_customer' => true,
            'status' => 'unread',
            'platform' => 'line',
            'message_type' => 'text'
        ]);
        
        $newVersion = $this->versionService->getCurrentVersion();
        
        // 版本號應該增加
        $this->assertGreaterThan($initialVersion, $newVersion);
        $this->assertEquals($newVersion, $conversation->version);
    }

    public function test_poll_updates_handles_invalid_version()
    {
        $this->actingAs($this->user, 'api');
        
        // 使用無效的版本號
        $response = $this->getJson("/api/chats/poll-updates?version=-1&timeout=1");
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        
        // 應該返回當前版本
        $responseData = $response->json();
        $this->assertArrayHasKey('version', $responseData);
    }

    public function test_poll_updates_response_time_is_recorded()
    {
        $this->actingAs($this->user, 'api');
        
        $response = $this->getJson("/api/chats/poll-updates?version=0&timeout=1");
        
        $response->assertStatus(200);
        
        $responseData = $response->json();
        $this->assertArrayHasKey('response_time', $responseData);
        $this->assertIsNumeric($responseData['response_time']);
        $this->assertGreaterThan(0, $responseData['response_time']);
    }
}