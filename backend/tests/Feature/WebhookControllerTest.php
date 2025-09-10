<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Customer;
use App\Models\CustomerLead;
use App\Models\CustomerIdentifier;
use App\Models\Website;

/**
 * Point 61: WordPress表單Webhook控制器功能測試
 * 
 * 測試範圍:
 * 1. WordPress表單webhook接收測試
 * 2. 欄位解析與客戶建立測試
 * 3. 表單資料驗證測試
 * 4. 黑名單偵測測試
 * 5. 網站欄位對應測試
 */
class WebhookControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * 測試基本WordPress表單webhook接收
     */
    public function test_wp_webhook_receives_form_data()
    {
        // 模擬標準的WordPress表單提交
        $payload = [
            '姓名' => '測試用戶',
            '手機號碼' => '0912345678',
            'Email' => 'test@example.com',
            'LINE_ID' => 'test_line_id',
            '方便聯絡時間' => '上午9:00-12:00',
            '資金需求' => '50萬以下',
            '貸款需求' => '信貸',
            '房屋區域' => '台北市',
            '房屋地址' => '測試地址123號',
            '日期' => date('j n 月, Y'),
            '時間' => date('g:i A'),
            '頁面 URL' => 'https://test-site.com/contact/'
        ];

        $response = $this->post('/api/webhook/wp', $payload);

        $response->assertStatus(200);
        
        // 驗證資料庫中是否建立了CustomerLead
        $this->assertDatabaseHas('customer_leads', [
            'name' => '測試用戶',
            'phone' => '0912345678',
            'email' => 'test@example.com',
            'line_id' => 'test_line_id',
            'channel' => 'wp_form'
        ]);

        // 驗證是否建立了Customer
        $customer = Customer::where('name', '測試用戶')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('0912345678', $customer->phone);
    }

    /**
     * 測試必填欄位驗證
     */
    public function test_wp_webhook_validates_required_fields()
    {
        // 只提供姓名，其他必填欄位留空
        $payload = [
            '姓名' => '測試用戶',
            // 故意不提供手機號碼
        ];

        $response = $this->post('/api/webhook/wp', $payload);
        
        $response->assertStatus(200); // webhook應該仍然成功接收
        
        // 但應該記錄警告或特殊處理
        $this->assertDatabaseHas('customer_leads', [
            'name' => '測試用戶',
            'phone' => null
        ]);
    }

    /**
     * 測試相同客戶資料不重複建立
     */
    public function test_wp_webhook_prevents_duplicate_customers()
    {
        $payload = [
            '姓名' => '重複測試用戶',
            '手機號碼' => '0987654321',
            'Email' => 'duplicate@example.com',
            '頁面 URL' => 'https://test-site.com/contact/'
        ];

        // 第一次提交
        $response1 = $this->post('/api/webhook/wp', $payload);
        $response1->assertStatus(200);

        // 第二次提交相同資料
        $response2 = $this->post('/api/webhook/wp', $payload);
        $response2->assertStatus(200);

        // 驗證只建立了一個Customer但有兩個CustomerLead
        $customerCount = Customer::where('name', '重複測試用戶')->count();
        $leadCount = CustomerLead::where('name', '重複測試用戶')->count();
        
        $this->assertEquals(1, $customerCount, '應該只建立一個Customer');
        $this->assertEquals(2, $leadCount, '應該建立兩個CustomerLead');
    }

    /**
     * 測試黑名單偵測功能（目前被停用）
     */
    public function test_wp_webhook_blacklist_detection()
    {
        $ip = '192.168.1.1';
        
        // 先建立一筆記錄
        CustomerLead::create([
            'name' => '正常用戶',
            'phone' => '0911111111',
            'channel' => 'wp_form',
            'source' => 'test-site.com',
            'ip_address' => $ip,
            'payload' => []
        ]);

        // 用相同IP但不同姓名提交
        $payload = [
            '姓名' => '可疑用戶',
            '手機號碼' => '0922222222',
            'LINE_ID' => 'suspicious_user',
            '頁面 URL' => 'https://test-site.com/contact/'
        ];

        $_SERVER['REMOTE_ADDR'] = $ip;
        
        $response = $this->post('/api/webhook/wp', $payload);
        $response->assertStatus(200);

        // 檢查是否正確標記（目前黑名單偵測被停用，所以應該是false）
        $lead = CustomerLead::where('name', '可疑用戶')->first();
        $this->assertFalse($lead->is_suspected_blacklist);
    }

    /**
     * 測試不同來源網站的處理
     */
    public function test_wp_webhook_handles_different_source_websites()
    {
        // 建立測試網站
        $website = Website::create([
            'name' => '測試網站',
            'domain' => 'test-site.com',
            'url' => 'https://test-site.com',
            'status' => 'active',
            'type' => 'wordpress',
            'webhook_enabled' => true
        ]);

        $payload = [
            '姓名' => '來源測試用戶',
            '手機號碼' => '0933333333',
            '頁面 URL' => 'https://test-site.com/form/'
        ];

        $response = $this->post('/api/webhook/wp', $payload);
        $response->assertStatus(200);

        // 驗證Lead與Website的關聯
        $lead = CustomerLead::where('name', '來源測試用戶')->first();
        $this->assertNotNull($lead);
        $this->assertEquals('test-site.com', $lead->source);
    }

    /**
     * 測試payload資料保存
     */
    public function test_wp_webhook_saves_complete_payload()
    {
        $payload = [
            '姓名' => 'Payload測試用戶',
            '手機號碼' => '0944444444',
            '自訂欄位1' => '自訂值1',
            '自訂欄位2' => '自訂值2',
            '特殊符號欄位' => '!@#$%^&*()',
            '頁面 URL' => 'https://test-site.com/custom-form/'
        ];

        $response = $this->post('/api/webhook/wp', $payload);
        $response->assertStatus(200);

        $lead = CustomerLead::where('name', 'Payload測試用戶')->first();
        $this->assertNotNull($lead);
        
        // 驗證完整payload是否被保存
        $savedPayload = $lead->payload;
        $this->assertArrayHasKey('自訂欄位1', $savedPayload);
        $this->assertEquals('自訂值1', $savedPayload['自訂欄位1']);
        $this->assertArrayHasKey('特殊符號欄位', $savedPayload);
    }

    /**
     * 測試特殊字符處理
     */
    public function test_wp_webhook_handles_special_characters()
    {
        $payload = [
            '姓名' => '測試 & 特殊字符 < > " \'',
            '手機號碼' => '0955555555',
            '備註' => 'HTML <script>alert("test")</script> 內容',
            '頁面 URL' => 'https://test-site.com/contact/'
        ];

        $response = $this->post('/api/webhook/wp', $payload);
        $response->assertStatus(200);

        $customer = Customer::where('phone', '0955555555')->first();
        $this->assertNotNull($customer);
        
        // 驗證特殊字符是否被正確處理
        $this->assertStringContainsString('測試 & 特殊字符', $customer->name);
    }

    /**
     * 測試空資料提交
     */
    public function test_wp_webhook_handles_empty_data()
    {
        $payload = [];

        $response = $this->post('/api/webhook/wp', $payload);
        
        // 空資料應該也要能處理，不應該造成錯誤
        $response->assertStatus(200);
    }

    /**
     * 清理測試環境
     */
    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }
}