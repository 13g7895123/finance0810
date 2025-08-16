<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\ChatConversation;
use App\Models\CustomerIdentifier;
use App\Models\CustomerActivity;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

/**
 * 聊天室控制器功能測試
 * 
 * 測試範圍:
 * 1. 對話列表 API 測試
 * 2. 特定對話 API 測試
 * 3. 訊息回覆 API 測試
 * 4. 權限控制測試
 * 5. 搜尋功能測試
 * 6. 統計數據測試
 * 7. 訊息標記測試
 * 8. LINE Webhook 測試
 */
class ChatControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $staff;
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // 創建測試用戶
        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'role' => 'admin'
        ]);

        $this->staff = User::factory()->create([
            'name' => 'Staff User', 
            'email' => 'staff@test.com',
            'role' => 'staff'
        ]);

        // 創建測試客戶
        $this->customer = Customer::factory()->create([
            'name' => '測試客戶',
            'phone' => '0912345678',
            'line_user_id' => 'U123456789',
            'assigned_to' => $this->staff->id,
            'created_by' => $this->admin->id
        ]);

        // 創建測試對話記錄
        ChatConversation::factory()->count(5)->create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->staff->id,
            'line_user_id' => $this->customer->line_user_id,
            'is_from_customer' => true,
            'status' => 'unread'
        ]);
    }

    /**
     * 測試管理員可以獲取所有對話列表
     */
    public function test_admin_can_get_all_conversations()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/chats');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'line_user_id',
                            'customer_id',
                            'last_message_time',
                            'unread_count',
                            'customer' => [
                                'name',
                                'phone'
                            ]
                        ]
                    ],
                    'current_page',
                    'per_page',
                    'total'
                ]);
    }

    /**
     * 測試業務人員只能看到自己負責的客戶對話
     */
    public function test_staff_can_only_see_assigned_conversations()
    {
        Sanctum::actingAs($this->staff);

        $response = $this->getJson('/api/chats');

        $response->assertStatus(200);
        
        $conversations = $response->json('data');
        foreach ($conversations as $conversation) {
            $this->assertEquals($this->staff->id, $conversation['customer']['assigned_to']);
        }
    }

    /**
     * 測試獲取特定對話內容
     */
    public function test_can_get_specific_conversation()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/chats/{$this->customer->line_user_id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'message_content',
                            'message_timestamp',
                            'is_from_customer',
                            'status',
                            'customer' => [
                                'name',
                                'phone'
                            ]
                        ]
                    ]
                ]);
    }

    /**
     * 測試訊息自動標記為已讀
     */
    public function test_messages_marked_as_read_when_viewing_conversation()
    {
        Sanctum::actingAs($this->admin);

        // 確認有未讀訊息
        $unreadCount = ChatConversation::where('line_user_id', $this->customer->line_user_id)
                                       ->where('status', 'unread')
                                       ->count();
        $this->assertGreaterThan(0, $unreadCount);

        // 獲取對話
        $response = $this->getJson("/api/chats/{$this->customer->line_user_id}");
        $response->assertStatus(200);

        // 確認訊息已標記為已讀
        $unreadCountAfter = ChatConversation::where('line_user_id', $this->customer->line_user_id)
                                           ->where('status', 'unread')
                                           ->count();
        $this->assertEquals(0, $unreadCountAfter);
    }

    /**
     * 測試回覆訊息功能
     */
    public function test_can_reply_to_message()
    {
        Sanctum::actingAs($this->staff);

        $replyContent = '感謝您的詢問，我們會盡快回覆您。';

        $response = $this->postJson("/api/chats/{$this->customer->line_user_id}/reply", [
            'message' => $replyContent
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => '訊息已送出'
                ])
                ->assertJsonStructure([
                    'conversation' => [
                        'id',
                        'message_content',
                        'is_from_customer',
                        'replied_by'
                    ]
                ]);

        // 確認資料庫中有新的回覆記錄
        $this->assertDatabaseHas('chat_conversations', [
            'line_user_id' => $this->customer->line_user_id,
            'message_content' => $replyContent,
            'is_from_customer' => false,
            'replied_by' => $this->staff->id
        ]);
    }

    /**
     * 測試業務人員權限控制
     */
    public function test_staff_cannot_reply_to_unassigned_customer()
    {
        // 創建另一個業務人員
        $otherStaff = User::factory()->create(['role' => 'staff']);
        
        // 創建不屬於當前業務人員的客戶
        $otherCustomer = Customer::factory()->create([
            'line_user_id' => 'U987654321',
            'assigned_to' => $otherStaff->id
        ]);

        Sanctum::actingAs($this->staff);

        $response = $this->postJson("/api/chats/{$otherCustomer->line_user_id}/reply", [
            'message' => '測試訊息'
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'error' => '您沒有權限回覆此對話'
                ]);
    }

    /**
     * 測試獲取未讀訊息數量
     */
    public function test_can_get_unread_count()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/chats/unread/count');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'unread_count'
                ]);

        $this->assertIsInt($response->json('unread_count'));
    }

    /**
     * 測試搜尋對話功能
     */
    public function test_can_search_conversations()
    {
        Sanctum::actingAs($this->admin);

        // 搜尋客戶名稱
        $response = $this->getJson('/api/chats/search?q=測試客戶');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'line_user_id',
                            'customer_id',
                            'customer' => [
                                'name'
                            ]
                        ]
                    ]
                ]);

        $conversations = $response->json('data');
        $this->assertNotEmpty($conversations);
    }

    /**
     * 測試搜尋電話號碼
     */
    public function test_can_search_by_phone_number()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/chats/search?q=0912345678');

        $response->assertStatus(200);
        
        $conversations = $response->json('data');
        foreach ($conversations as $conversation) {
            $this->assertStringContainsString('0912345678', $conversation['customer']['phone']);
        }
    }

    /**
     * 測試獲取聊天統計數據
     */
    public function test_can_get_chat_stats()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/chats/stats');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'total_conversations',
                    'unread_messages',
                    'today_messages',
                    'active_customers'
                ]);

        $stats = $response->json();
        $this->assertIsInt($stats['total_conversations']);
        $this->assertIsInt($stats['unread_messages']);
        $this->assertIsInt($stats['today_messages']);
        $this->assertIsInt($stats['active_customers']);
    }

    /**
     * 測試標記訊息為已讀
     */
    public function test_can_mark_messages_as_read()
    {
        Sanctum::actingAs($this->admin);

        // 確保有未讀訊息
        ChatConversation::where('line_user_id', $this->customer->line_user_id)
                        ->update(['status' => 'unread']);

        $response = $this->postJson("/api/chats/{$this->customer->line_user_id}/read");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'updated_count'
                ]);

        // 確認訊息已標記為已讀
        $unreadCount = ChatConversation::where('line_user_id', $this->customer->line_user_id)
                                       ->where('status', 'unread')
                                       ->count();
        $this->assertEquals(0, $unreadCount);
    }

    /**
     * 測試刪除對話
     */
    public function test_can_delete_conversation()
    {
        Sanctum::actingAs($this->admin);

        $initialCount = ChatConversation::where('line_user_id', $this->customer->line_user_id)->count();

        $response = $this->deleteJson("/api/chats/{$this->customer->line_user_id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'deleted_count'
                ]);

        $finalCount = ChatConversation::where('line_user_id', $this->customer->line_user_id)->count();
        $this->assertEquals(0, $finalCount);
        $this->assertGreaterThan(0, $response->json('deleted_count'));
    }

    /**
     * 測試 LINE Webhook 接收訊息
     */
    public function test_line_webhook_can_receive_messages()
    {
        $webhookData = [
            'events' => [
                [
                    'type' => 'message',
                    'message' => [
                        'type' => 'text',
                        'text' => '您好，我想詢問汽車貸款相關資訊',
                        'id' => 'message123'
                    ],
                    'source' => [
                        'userId' => 'U999888777'
                    ],
                    'timestamp' => now()->timestamp * 1000
                ]
            ]
        ];

        $response = $this->postJson('/api/line/webhook', $webhookData);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'ok'
                ]);

        // 確認訊息已儲存到資料庫
        $this->assertDatabaseHas('chat_conversations', [
            'line_user_id' => 'U999888777',
            'message_content' => '您好，我想詢問汽車貸款相關資訊',
            'is_from_customer' => true
        ]);
    }

    /**
     * 測試無效的回覆請求
     */
    public function test_reply_validation()
    {
        Sanctum::actingAs($this->staff);

        // 測試空訊息
        $response = $this->postJson("/api/chats/{$this->customer->line_user_id}/reply", [
            'message' => ''
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['message']);

        // 測試過長訊息
        $longMessage = str_repeat('A', 1001);
        $response = $this->postJson("/api/chats/{$this->customer->line_user_id}/reply", [
            'message' => $longMessage
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['message']);
    }

    /**
     * 測試不存在的客戶回覆
     */
    public function test_reply_to_non_existent_customer()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/chats/non-existent-user/reply', [
            'message' => '測試訊息'
        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'error' => '找不到對應的客戶'
                ]);
    }

    /**
     * 測試未認證用戶訪問
     */
    public function test_unauthenticated_access()
    {
        $response = $this->getJson('/api/chats');
        $response->assertStatus(401);

        $response = $this->getJson('/api/chats/U123456789');
        $response->assertStatus(401);

        $response = $this->postJson('/api/chats/U123456789/reply', [
            'message' => '測試訊息'
        ]);
        $response->assertStatus(401);
    }

    /**
     * 測試分頁功能
     */
    public function test_conversation_pagination()
    {
        // 創建更多測試數據
        $customers = Customer::factory()->count(25)->create([
            'assigned_to' => $this->staff->id
        ]);

        foreach ($customers as $customer) {
            ChatConversation::factory()->create([
                'customer_id' => $customer->id,
                'line_user_id' => 'U' . $customer->id,
                'user_id' => $this->staff->id
            ]);
        }

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/chats');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'current_page',
                    'data',
                    'per_page',
                    'total'
                ]);

        $this->assertEquals(20, $response->json('per_page'));
        $this->assertLessThanOrEqual(20, count($response->json('data')));
    }

    /**
     * 測試訊息時間排序
     */
    public function test_conversations_ordered_by_time()
    {
        // 創建不同時間的對話
        $now = Carbon::now();
        
        $oldConversation = ChatConversation::factory()->create([
            'message_timestamp' => $now->copy()->subHours(2),
            'line_user_id' => 'U_old'
        ]);
        
        $newConversation = ChatConversation::factory()->create([
            'message_timestamp' => $now->copy()->subMinutes(10),
            'line_user_id' => 'U_new'
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/chats');

        $response->assertStatus(200);
        
        $conversations = $response->json('data');
        $this->assertNotEmpty($conversations);
        
        // 檢查排序（最新的在前面）
        $timestamps = array_column($conversations, 'last_message_time');
        $sortedTimestamps = $timestamps;
        rsort($sortedTimestamps);
        
        $this->assertEquals($sortedTimestamps, $timestamps);
    }

    /**
     * 測試客戶統一功能 - 當LINE用戶與現有網站客戶為同一人時
     */
    public function test_customer_unification_via_identifier_system()
    {
        // 創建一個已存在的網站客戶
        $existingCustomer = Customer::factory()->create([
            'name' => '王小明',
            'phone' => '0912345678',
            'email' => 'wang@example.com',
            'channel' => 'web_form',
            'assigned_to' => $this->staff->id
        ]);

        // 為客戶創建識別符
        CustomerIdentifier::create([
            'customer_id' => $existingCustomer->id,
            'type' => 'phone',
            'value' => '0912345678'
        ]);

        CustomerIdentifier::create([
            'customer_id' => $existingCustomer->id,
            'type' => 'email',
            'value' => 'wang@example.com'
        ]);

        // 模擬LINE Webhook事件 - 同一個客戶加入LINE好友
        $webhookData = [
            'events' => [
                [
                    'type' => 'follow',
                    'source' => [
                        'userId' => 'U_wang_line_id'
                    ],
                    'timestamp' => now()->timestamp * 1000
                ]
            ]
        ];

        $response = $this->postJson('/api/line/webhook', $webhookData);

        $response->assertStatus(200)
                ->assertJson(['status' => 'ok']);

        // 重新載入客戶資料
        $existingCustomer->refresh();

        // 驗證客戶資料已更新，但沒有創建新客戶
        $this->assertEquals('U_wang_line_id', $existingCustomer->line_user_id);
        $this->assertEquals('multi_channel', $existingCustomer->channel);
        $this->assertStringContainsString('加入LINE好友，帳戶已整合', $existingCustomer->notes);

        // 驗證LINE識別符已添加
        $this->assertDatabaseHas('customer_identifiers', [
            'customer_id' => $existingCustomer->id,
            'type' => 'line',
            'value' => 'U_wang_line_id'
        ]);

        // 驗證統一事件已記錄
        $this->assertDatabaseHas('customer_activities', [
            'customer_id' => $existingCustomer->id,
            'activity_type' => CustomerActivity::TYPE_UNIFIED,
            'description' => 'LINE 客戶與現有客戶統一整合'
        ]);

        // 確保沒有創建重複的客戶記錄
        $this->assertEquals(1, Customer::where('phone', '0912345678')->count());
    }

    /**
     * 測試完全新的LINE用戶（無現有記錄）
     */
    public function test_new_line_customer_creation()
    {
        $initialCustomerCount = Customer::count();

        // 模擬新LINE用戶加入好友事件
        $webhookData = [
            'events' => [
                [
                    'type' => 'follow',
                    'source' => [
                        'userId' => 'U_completely_new_user'
                    ],
                    'timestamp' => now()->timestamp * 1000
                ]
            ]
        ];

        $response = $this->postJson('/api/line/webhook', $webhookData);

        $response->assertStatus(200)
                ->assertJson(['status' => 'ok']);

        // 驗證新客戶已創建
        $this->assertEquals($initialCustomerCount + 1, Customer::count());

        // 檢查新客戶的屬性
        $newCustomer = Customer::where('line_user_id', 'U_completely_new_user')->first();
        $this->assertNotNull($newCustomer);
        $this->assertEquals('line', $newCustomer->channel);
        $this->assertNull($newCustomer->assigned_to); // 未分配業務
        $this->assertEquals('未知', $newCustomer->region);

        // 驗證LINE識別符已創建
        $this->assertDatabaseHas('customer_identifiers', [
            'customer_id' => $newCustomer->id,
            'type' => 'line',
            'value' => 'U_completely_new_user'
        ]);

        // 驗證創建事件已記錄
        $this->assertDatabaseHas('customer_activities', [
            'customer_id' => $newCustomer->id,
            'activity_type' => CustomerActivity::TYPE_CREATED,
            'description' => '由 LINE Bot 建立客戶'
        ]);
    }

    /**
     * 測試客戶重新加入LINE好友（軟刪除恢復）
     */
    public function test_soft_deleted_customer_restoration()
    {
        // 創建一個已軟刪除的客戶
        $deletedCustomer = Customer::factory()->create([
            'line_user_id' => 'U_deleted_customer',
            'name' => '已刪除客戶',
            'channel' => 'line',
            'deleted_at' => now()->subDays(1)
        ]);

        $initialActiveCustomerCount = Customer::count();

        // 模擬客戶重新加入好友
        $webhookData = [
            'events' => [
                [
                    'type' => 'follow',
                    'source' => [
                        'userId' => 'U_deleted_customer'
                    ],
                    'timestamp' => now()->timestamp * 1000
                ]
            ]
        ];

        $response = $this->postJson('/api/line/webhook', $webhookData);

        $response->assertStatus(200);

        // 驗證客戶已恢復
        $this->assertEquals($initialActiveCustomerCount + 1, Customer::count());
        
        $restoredCustomer = Customer::where('line_user_id', 'U_deleted_customer')->first();
        $this->assertNotNull($restoredCustomer);
        $this->assertNull($restoredCustomer->deleted_at);
        $this->assertEquals(Customer::STATUS_NEW, $restoredCustomer->status);
        $this->assertStringContainsString('重新加入LINE好友', $restoredCustomer->notes);
    }

    /**
     * 測試LINE用戶發送文字訊息並觸發客戶統一
     */
    public function test_customer_unification_via_message_event()
    {
        // 創建現有網站客戶
        $existingCustomer = Customer::factory()->create([
            'name' => '李大華',
            'phone' => '0987654321',
            'channel' => 'web_form'
        ]);

        CustomerIdentifier::create([
            'customer_id' => $existingCustomer->id,
            'type' => 'phone',
            'value' => '0987654321'
        ]);

        // 模擬LINE訊息事件（來自未知的LINE用戶）
        $webhookData = [
            'events' => [
                [
                    'type' => 'message',
                    'message' => [
                        'type' => 'text',
                        'text' => '我想了解貸款方案',
                        'id' => 'msg123'
                    ],
                    'source' => [
                        'userId' => 'U_li_dahua_line'
                    ],
                    'timestamp' => now()->timestamp * 1000
                ]
            ]
        ];

        $response = $this->postJson('/api/line/webhook', $webhookData);

        $response->assertStatus(200);

        // 驗證訊息已保存到現有客戶
        $this->assertDatabaseHas('chat_conversations', [
            'customer_id' => $existingCustomer->id,
            'line_user_id' => 'U_li_dahua_line',
            'message_content' => '我想了解貸款方案',
            'is_from_customer' => true
        ]);

        // 重新載入客戶並檢查LINE資料已整合
        $existingCustomer->refresh();
        $this->assertEquals('U_li_dahua_line', $existingCustomer->line_user_id);
        
        // 確認沒有創建重複客戶
        $this->assertEquals(1, Customer::where('phone', '0987654321')->count());
    }

    /**
     * 測試同時擁有多個識別符的客戶統一
     */
    public function test_customer_unification_with_multiple_identifiers()
    {
        // 創建有多個識別符的現有客戶
        $existingCustomer = Customer::factory()->create([
            'name' => '陳美麗',
            'phone' => '0955123456',
            'email' => 'chen@example.com',
            'channel' => 'web_form'
        ]);

        // 創建多個識別符
        CustomerIdentifier::create([
            'customer_id' => $existingCustomer->id,
            'type' => 'phone',
            'value' => '0955123456'
        ]);

        CustomerIdentifier::create([
            'customer_id' => $existingCustomer->id,
            'type' => 'email',
            'value' => 'chen@example.com'
        ]);

        // 模擬LINE事件
        $webhookData = [
            'events' => [
                [
                    'type' => 'follow',
                    'source' => [
                        'userId' => 'U_chen_meili'
                    ],
                    'timestamp' => now()->timestamp * 1000
                ]
            ]
        ];

        $response = $this->postJson('/api/line/webhook', $webhookData);

        $response->assertStatus(200);

        // 驗證所有識別符都關聯到同一客戶
        $customerIdentifiers = CustomerIdentifier::where('customer_id', $existingCustomer->id)->get();
        $this->assertCount(3, $customerIdentifiers); // phone, email, line

        $identifierTypes = $customerIdentifiers->pluck('type')->toArray();
        $this->assertContains('phone', $identifierTypes);
        $this->assertContains('email', $identifierTypes);
        $this->assertContains('line', $identifierTypes);

        // 驗證LINE識別符值正確
        $lineIdentifier = $customerIdentifiers->where('type', 'line')->first();
        $this->assertEquals('U_chen_meili', $lineIdentifier->value);
    }
}