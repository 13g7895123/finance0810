<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Website;
use App\Models\WebsiteFieldMapping;
use App\Models\Customer;
use App\Models\CustomerLead;

/**
 * Point 61: Webhook與欄位對應整合測試
 * 
 * 測試完整的WordPress表單提交流程：
 * 表單資料 -> 欄位對應 -> 客戶建立 -> Lead記錄
 */
class WebhookFieldMappingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Website $website;

    protected function setUp(): void
    {
        parent::setUp();

        // 建立測試網站
        $this->website = Website::create([
            'name' => '測試網站',
            'domain' => 'test-integration.com',
            'url' => 'https://test-integration.com',
            'status' => 'active',
            'type' => 'wordpress',
            'webhook_enabled' => true
        ]);
    }

    /**
     * 測試使用預設欄位對應的完整流程
     */
    public function test_complete_workflow_with_default_mapping()
    {
        $formData = [
            '姓名' => '預設對應測試客戶',
            '手機號碼' => '0912-345-678',
            'Email' => 'default@example.com',
            'LINE_ID' => 'default_line_id',
            '方便聯絡時間' => '上午9:00-12:00',
            '資金需求' => '50萬以下',
            '貸款需求' => '信貸',
            '頁面 URL' => 'https://test-integration.com/contact/',
            '自訂欄位' => '自訂值'
        ];

        $response = $this->post('/api/webhook/wp', $formData);

        $response->assertStatus(201);

        // 驗證客戶已建立
        $customer = Customer::where('name', '預設對應測試客戶')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('0912345678', $customer->phone); // 電話號碼應該被格式化
        $this->assertEquals('default@example.com', $customer->email);

        // 驗證Lead已建立
        $lead = CustomerLead::where('customer_id', $customer->id)->first();
        $this->assertNotNull($lead);
        $this->assertEquals('預設對應測試客戶', $lead->name);
        $this->assertEquals('0912345678', $lead->phone);
        $this->assertEquals('default_line_id', $lead->line_id);
        $this->assertEquals('wp_form', $lead->channel);
        $this->assertEquals('test-integration.com', $lead->source);

        // 驗證payload中保存了所有原始資料
        $this->assertArrayHasKey('自訂欄位', $lead->payload);
        $this->assertEquals('自訂值', $lead->payload['自訂欄位']);
    }

    /**
     * 測試使用自訂欄位對應的完整流程
     */
    public function test_complete_workflow_with_custom_mapping()
    {
        // 建立自訂欄位對應
        $this->createCustomFieldMappings();

        $formData = [
            '客戶姓名' => '自訂對應測試客戶',
            '行動電話' => '(09) 8765-4321',
            '電子信箱' => 'CUSTOM@EXAMPLE.COM',
            'LINE帳號' => 'custom_line_id',
            '聯絡時段' => '下午2:00-5:00',
            '資金需要' => '100萬以上',
            '來源頁面' => 'https://test-integration.com/form/',
            '備註資訊' => '這是備註'
        ];

        $response = $this->post('/api/webhook/wp', $formData);

        $response->assertStatus(201);

        // 驗證客戶已建立（使用自訂對應）
        $customer = Customer::where('name', '自訂對應測試客戶')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('0987654321', $customer->phone); // 使用自訂對應的電話欄位
        $this->assertEquals('custom@example.com', $customer->email); // 應該被轉為小寫

        // 驗證Lead已建立（使用自訂對應）
        $lead = CustomerLead::where('customer_id', $customer->id)->first();
        $this->assertNotNull($lead);
        $this->assertEquals('自訂對應測試客戶', $lead->name);
        $this->assertEquals('0987654321', $lead->phone);
        $this->assertEquals('custom_line_id', $lead->line_id);

        // 驗證未對應的欄位保存在payload中
        $this->assertArrayHasKey('備註資訊', $lead->payload);
        $this->assertEquals('這是備註', $lead->payload['備註資訊']);
    }

    /**
     * 測試欄位對應失效時的回退機制
     */
    public function test_fallback_to_default_mapping_when_custom_mapping_fails()
    {
        // 建立一個無效的欄位對應（系統欄位名稱錯誤）
        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'invalid_field', // 無效的系統欄位
            'wp_field_name' => '姓名',
            'display_name' => '客戶姓名',
            'field_type' => 'text',
            'is_active' => true
        ]);

        $formData = [
            '姓名' => '回退測試客戶',
            '手機號碼' => '0911111111',
            '頁面 URL' => 'https://test-integration.com/contact/'
        ];

        $response = $this->post('/api/webhook/wp', $formData);

        // 即使自訂對應有問題，webhook仍應該成功處理
        $response->assertStatus(201);

        // 應該回退到預設對應
        $customer = Customer::where('name', '回退測試客戶')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('0911111111', $customer->phone);
    }

    /**
     * 測試相同客戶的重複提交
     */
    public function test_handles_duplicate_submissions_with_field_mapping()
    {
        $this->createCustomFieldMappings();

        $formData = [
            '客戶姓名' => '重複提交測試客戶',
            '行動電話' => '0922222222',
            '電子信箱' => 'duplicate@example.com',
            '來源頁面' => 'https://test-integration.com/form/'
        ];

        // 第一次提交
        $response1 = $this->post('/api/webhook/wp', $formData);
        $response1->assertStatus(201);

        // 第二次提交相同資料
        $response2 = $this->post('/api/webhook/wp', $formData);
        $response2->assertStatus(201);

        // 應該只有一個客戶
        $customerCount = Customer::where('name', '重複提交測試客戶')->count();
        $this->assertEquals(1, $customerCount);

        // 但應該有兩個Lead記錄
        $customer = Customer::where('name', '重複提交測試客戶')->first();
        $leadCount = CustomerLead::where('customer_id', $customer->id)->count();
        $this->assertEquals(2, $leadCount);
    }

    /**
     * 測試不同網站的欄位對應隔離
     */
    public function test_field_mapping_isolation_between_websites()
    {
        // 建立第二個網站
        $website2 = Website::create([
            'name' => '測試網站2',
            'domain' => 'test-site2.com',
            'url' => 'https://test-site2.com',
            'status' => 'active',
            'type' => 'wordpress'
        ]);

        // 為第一個網站建立對應
        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'name',
            'wp_field_name' => '客戶名稱',
            'display_name' => '姓名',
            'field_type' => 'text',
            'is_active' => true
        ]);

        // 為第二個網站建立不同的對應
        WebsiteFieldMapping::create([
            'website_id' => $website2->id,
            'system_field' => 'name',
            'wp_field_name' => '用戶姓名',
            'display_name' => '姓名',
            'field_type' => 'text',
            'is_active' => true
        ]);

        // 測試第一個網站
        $formData1 = [
            '客戶名稱' => '網站1客戶',
            '手機號碼' => '0911111111',
            '頁面 URL' => 'https://test-integration.com/contact/'
        ];

        $response1 = $this->post('/api/webhook/wp', $formData1);
        $response1->assertStatus(201);

        // 測試第二個網站
        $formData2 = [
            '用戶姓名' => '網站2客戶',
            '手機號碼' => '0922222222',
            '頁面 URL' => 'https://test-site2.com/contact/'
        ];

        $response2 = $this->post('/api/webhook/wp', $formData2);
        $response2->assertStatus(201);

        // 驗證兩個客戶都被正確建立
        $this->assertDatabaseHas('customers', ['name' => '網站1客戶']);
        $this->assertDatabaseHas('customers', ['name' => '網站2客戶']);
        
        // 驗證Lead的來源網站正確
        $lead1 = CustomerLead::where('name', '網站1客戶')->first();
        $lead2 = CustomerLead::where('name', '網站2客戶')->first();
        
        $this->assertEquals('test-integration.com', $lead1->source);
        $this->assertEquals('test-site2.com', $lead2->source);
    }

    /**
     * 測試必填欄位驗證
     */
    public function test_handles_missing_required_fields()
    {
        $this->createCustomFieldMappingsWithRequired();

        // 提交缺少必填欄位的資料
        $formData = [
            // 缺少客戶姓名（設定為必填）
            '行動電話' => '0933333333',
            '來源頁面' => 'https://test-integration.com/form/'
        ];

        $response = $this->post('/api/webhook/wp', $formData);

        // webhook仍應該成功（不阻塞流程），但應該記錄警告
        $response->assertStatus(201);

        // 客戶應該以空名稱或預設值建立
        $customer = Customer::where('phone', '0933333333')->first();
        $this->assertNotNull($customer);
    }

    /**
     * 測試欄位類型轉換
     */
    public function test_field_type_transformations()
    {
        // 建立包含不同欄位類型的對應
        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'phone',
            'wp_field_name' => '聯絡電話',
            'display_name' => '電話號碼',
            'field_type' => 'phone',
            'is_active' => true
        ]);

        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'email',
            'wp_field_name' => '電子郵件',
            'display_name' => '郵件地址',
            'field_type' => 'email',
            'is_active' => true
        ]);

        $formData = [
            '姓名' => '類型轉換測試',
            '聯絡電話' => '(02) 1234-5678',  // 應該格式化為數字
            '電子郵件' => '  TEST@DOMAIN.COM  ', // 應該轉為小寫並去空白
            '頁面 URL' => 'https://test-integration.com/contact/'
        ];

        $response = $this->post('/api/webhook/wp', $formData);
        $response->assertStatus(201);

        $customer = Customer::where('name', '類型轉換測試')->first();
        $this->assertNotNull($customer);
        
        // 驗證phone類型轉換（移除非數字字符）
        $this->assertEquals('0212345678', $customer->phone);
        
        // 驗證email類型轉換（小寫並去空白）
        $this->assertEquals('test@domain.com', $customer->email);
    }

    /**
     * 建立自訂欄位對應（測試用）
     */
    protected function createCustomFieldMappings()
    {
        $mappings = [
            ['system_field' => 'name', 'wp_field_name' => '客戶姓名'],
            ['system_field' => 'phone', 'wp_field_name' => '行動電話', 'field_type' => 'phone'],
            ['system_field' => 'email', 'wp_field_name' => '電子信箱', 'field_type' => 'email'],
            ['system_field' => 'line_id', 'wp_field_name' => 'LINE帳號'],
            ['system_field' => 'contact_time', 'wp_field_name' => '聯絡時段'],
            ['system_field' => 'capital_need', 'wp_field_name' => '資金需要'],
            ['system_field' => 'page_url', 'wp_field_name' => '來源頁面'],
        ];

        foreach ($mappings as $index => $mapping) {
            WebsiteFieldMapping::create([
                'website_id' => $this->website->id,
                'system_field' => $mapping['system_field'],
                'wp_field_name' => $mapping['wp_field_name'],
                'display_name' => $mapping['wp_field_name'],
                'field_type' => $mapping['field_type'] ?? 'text',
                'is_required' => false,
                'is_active' => true,
                'sort_order' => $index * 10
            ]);
        }
    }

    /**
     * 建立包含必填欄位的自訂對應（測試用）
     */
    protected function createCustomFieldMappingsWithRequired()
    {
        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'name',
            'wp_field_name' => '客戶姓名',
            'display_name' => '姓名',
            'field_type' => 'text',
            'is_required' => true,
            'is_active' => true
        ]);

        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'phone',
            'wp_field_name' => '行動電話',
            'display_name' => '電話',
            'field_type' => 'phone',
            'is_required' => true,
            'is_active' => true
        ]);
    }
}