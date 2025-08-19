<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\ChatConversation;
use App\Models\Customer;
use App\Services\ChatVersionService;
use App\Services\ChatIncrementalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class ChatIncrementalTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 運行必要的遷移
        $this->artisan('migrate');
    }

    public function test_incremental_format_structure()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $this->actingAs($user, 'api');
        
        // 初始化版本服務
        $versionService = app(ChatVersionService::class);
        $initialVersion = $versionService->getCurrentVersion();
        
        // 創建測試數據
        $conversation = ChatConversation::factory()->create([
            'customer_id' => $customer->id,
            'line_user_id' => 'test_user_123',
            'version' => $initialVersion + 1
        ]);
        
        $response = $this->getJson('/api/chats/incremental?version=' . $initialVersion . '&type=conversations');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'version',
                'timestamp',
                'changes' => [
                    'added',
                    'updated',
                    'removed'
                ],
                'metadata' => [
                    'total_changes',
                    'checksum',
                    'partial'
                ]
            ]);
    }
    
    public function test_checksum_validation()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        
        $data = ['test' => 'data'];
        $checksum = md5(json_encode($data, JSON_SORT_KEYS | JSON_UNESCAPED_UNICODE));
        
        $response = $this->postJson('/api/chats/validate-checksum', [
            'checksum' => $checksum,
            'data' => $data
        ]);
        
        $response->assertStatus(200)
            ->assertJson(['valid' => true]);
    }

    public function test_incremental_message_updates()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $this->actingAs($user, 'api');
        
        $lineUserId = 'test_user_456';
        
        // 獲取初始版本
        $versionService = app(ChatVersionService::class);
        $initialVersion = $versionService->getCurrentVersion();
        
        // 創建新訊息
        $conversation = ChatConversation::factory()->create([
            'customer_id' => $customer->id,
            'line_user_id' => $lineUserId,
            'message_content' => 'Test message',
            'is_from_customer' => true,
            'status' => 'unread'
        ]);
        
        // 測試訊息列表的增量更新
        $response = $this->getJson("/api/chats/incremental?version={$initialVersion}&type=messages&line_user_id={$lineUserId}");
        
        $response->assertStatus(200);
        $responseData = $response->json();
        
        $this->assertArrayHasKey('changes', $responseData);
        $this->assertArrayHasKey('added', $responseData['changes']);
        $this->assertNotEmpty($responseData['changes']['added']);
    }

    public function test_conversation_list_incremental_updates()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $this->actingAs($user, 'api');
        
        // 獲取初始版本
        $versionService = app(ChatVersionService::class);
        $initialVersion = $versionService->getCurrentVersion();
        
        // 創建新對話
        $conversation = ChatConversation::factory()->create([
            'customer_id' => $customer->id,
            'line_user_id' => 'test_user_789',
            'message_content' => 'New conversation',
            'is_from_customer' => true,
            'status' => 'unread'
        ]);
        
        // 測試對話列表的增量更新
        $response = $this->getJson("/api/chats/incremental?version={$initialVersion}&type=conversations");
        
        $response->assertStatus(200);
        $responseData = $response->json();
        
        $this->assertArrayHasKey('changes', $responseData);
        $this->assertArrayHasKey('metadata', $responseData);
        $this->assertIsInt($responseData['metadata']['total_changes']);
        $this->assertIsString($responseData['metadata']['checksum']);
    }

    public function test_invalid_checksum_validation()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        
        $data = ['test' => 'data'];
        $invalidChecksum = 'invalid_checksum';
        
        $response = $this->postJson('/api/chats/validate-checksum', [
            'checksum' => $invalidChecksum,
            'data' => $data
        ]);
        
        $response->assertStatus(200)
            ->assertJson(['valid' => false]);
    }

    public function test_incremental_api_validation()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        
        // 測試缺少必要參數
        $response = $this->getJson('/api/chats/incremental');
        $response->assertStatus(422);
        
        // 測試無效的 type 參數
        $response = $this->getJson('/api/chats/incremental?version=0&type=invalid');
        $response->assertStatus(422);
        
        // 測試 messages 類型缺少 line_user_id
        $response = $this->getJson('/api/chats/incremental?version=0&type=messages');
        $response->assertStatus(422);
    }

    public function test_version_service_integration()
    {
        $versionService = app(ChatVersionService::class);
        
        $initialVersion = $versionService->getCurrentVersion();
        $this->assertIsInt($initialVersion);
        
        // 測試版本遞增
        $newVersion = $versionService->incrementVersion();
        $this->assertGreaterThan($initialVersion, $newVersion);
        
        // 測試需要更新檢查
        $this->assertTrue($versionService->needsUpdate($initialVersion));
        $this->assertFalse($versionService->needsUpdate($newVersion));
    }
}