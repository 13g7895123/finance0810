<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Website;
use App\Models\WebsiteFieldMapping;
use App\Services\FormFieldMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Point 61: FormFieldMapper服務單元測試
 */
class FormFieldMapperTest extends TestCase
{
    use RefreshDatabase;

    protected FormFieldMapper $fieldMapper;
    protected Website $website;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fieldMapper = app(FormFieldMapper::class);
        
        // 建立測試網站
        $this->website = Website::create([
            'name' => '測試網站',
            'domain' => 'test-site.com',
            'url' => 'https://test-site.com',
            'status' => 'active',
            'type' => 'wordpress'
        ]);
    }

    /**
     * 測試使用預設對應
     */
    public function test_uses_default_mapping_when_no_custom_mappings_exist()
    {
        $rawFormData = [
            '姓名' => '測試客戶',
            '手機號碼' => '0912-345-678',
            'Email' => 'TEST@EXAMPLE.COM',
            'LINE_ID' => 'test_line_id',
            '未知欄位' => '未知值'
        ];

        $result = $this->fieldMapper->mapFields('test-site.com', $rawFormData);

        // 驗證標準欄位對應
        $this->assertEquals('測試客戶', $result['name']);
        $this->assertEquals('0912345678', $result['phone']); // 電話號碼應該被格式化
        $this->assertEquals('test@example.com', $result['email']); // 電子郵件應該轉為小寫
        $this->assertEquals('test_line_id', $result['line_id']);

        // 驗證未對應欄位
        $this->assertArrayHasKey('_unmapped_fields', $result);
        $this->assertEquals('未知值', $result['_unmapped_fields']['未知欄位']);

        // 驗證原始資料保存
        $this->assertArrayHasKey('_original_payload', $result);
        $this->assertEquals($rawFormData, $result['_original_payload']);
    }

    /**
     * 測試使用自訂欄位對應
     */
    public function test_uses_custom_field_mappings_when_available()
    {
        // 建立自訂欄位對應
        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'name',
            'wp_field_name' => '客戶名稱',
            'display_name' => '客戶姓名',
            'field_type' => 'text',
            'is_active' => true
        ]);

        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'phone',
            'wp_field_name' => '聯絡電話',
            'display_name' => '手機號碼',
            'field_type' => 'phone',
            'is_active' => true
        ]);

        $rawFormData = [
            '客戶名稱' => '自訂測試客戶',
            '聯絡電話' => '09-1234-5678',
            '姓名' => '這個不應該被對應', // 使用預設的欄位名稱，但現在有自訂對應
            '其他欄位' => '其他值'
        ];

        $result = $this->fieldMapper->mapFields('test-site.com', $rawFormData);

        // 驗證使用了自訂對應
        $this->assertEquals('自訂測試客戶', $result['name']);
        $this->assertEquals('0912345678', $result['phone']);

        // 驗證預設欄位名稱沒有被對應（因為現在使用自訂對應）
        $this->assertArrayHasKey('_unmapped_fields', $result);
        $this->assertEquals('這個不應該被對應', $result['_unmapped_fields']['姓名']);
        $this->assertEquals('其他值', $result['_unmapped_fields']['其他欄位']);
    }

    /**
     * 測試欄位轉換功能
     */
    public function test_field_value_transformation()
    {
        $rawFormData = [
            '手機號碼' => '(09) 1234-5678',
            'Email' => '  TEST@EXAMPLE.COM  '
        ];

        $result = $this->fieldMapper->mapFields('test-site.com', $rawFormData);

        // 驗證電話號碼格式化（只保留數字）
        $this->assertEquals('0912345678', $result['phone']);

        // 驗證電子郵件格式化（小寫且去空白）
        $this->assertEquals('test@example.com', $result['email']);
    }

    /**
     * 測試從URL提取域名
     */
    public function test_extracts_domain_from_url()
    {
        $testCases = [
            'https://example.com/contact/' => 'example.com',
            'http://sub.example.com/form' => 'sub.example.com',
            'https://example.com:8080/path' => 'example.com',
            '' => null,
            'invalid-url' => null,
        ];

        foreach ($testCases as $url => $expectedDomain) {
            $result = $this->fieldMapper->extractDomainFromUrl($url);
            $this->assertEquals($expectedDomain, $result, "Failed for URL: {$url}");
        }
    }

    /**
     * 測試取得系統標準欄位
     */
    public function test_get_standard_fields()
    {
        $standardFields = $this->fieldMapper->getStandardFields();

        // 驗證包含基本欄位
        $this->assertArrayHasKey('name', $standardFields);
        $this->assertArrayHasKey('phone', $standardFields);
        $this->assertArrayHasKey('email', $standardFields);
        $this->assertArrayHasKey('line_id', $standardFields);

        // 驗證欄位結構
        $nameField = $standardFields['name'];
        $this->assertArrayHasKey('label', $nameField);
        $this->assertArrayHasKey('type', $nameField);
        $this->assertArrayHasKey('required', $nameField);
        $this->assertArrayHasKey('description', $nameField);
    }

    /**
     * 測試非存在網站使用預設對應
     */
    public function test_uses_default_mapping_for_non_existent_website()
    {
        $rawFormData = [
            '姓名' => '測試客戶',
            '手機號碼' => '0912345678'
        ];

        $result = $this->fieldMapper->mapFields('non-existent-site.com', $rawFormData);

        // 應該使用預設對應
        $this->assertEquals('測試客戶', $result['name']);
        $this->assertEquals('0912345678', $result['phone']);
    }

    /**
     * 測試建立預設對應
     */
    public function test_create_default_mappings()
    {
        $this->assertEquals(0, WebsiteFieldMapping::where('website_id', $this->website->id)->count());

        $this->fieldMapper->createDefaultMappings($this->website->id);

        $mappings = WebsiteFieldMapping::where('website_id', $this->website->id)->get();
        
        // 驗證建立了多個預設對應
        $this->assertGreaterThan(0, $mappings->count());

        // 驗證包含基本必填欄位
        $systemFields = $mappings->pluck('system_field')->toArray();
        $this->assertContains('name', $systemFields);
        $this->assertContains('phone', $systemFields);
    }

    /**
     * 測試欄位驗證
     */
    public function test_field_validation()
    {
        $errors = $this->fieldMapper->validateMapping($this->website->id);

        // 新網站應該有缺少必填欄位的錯誤
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('必填欄位', implode(' ', $errors));
    }

    /**
     * 測試空資料處理
     */
    public function test_handles_empty_form_data()
    {
        $result = $this->fieldMapper->mapFields('test-site.com', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('_original_payload', $result);
        $this->assertArrayHasKey('_unmapped_fields', $result);
        $this->assertEquals([], $result['_original_payload']);
        $this->assertEquals([], $result['_unmapped_fields']);
    }

    /**
     * 測試只有啟用的對應被使用
     */
    public function test_only_active_mappings_are_used()
    {
        // 建立啟用的對應
        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'name',
            'wp_field_name' => '客戶名稱',
            'display_name' => '客戶姓名',
            'field_type' => 'text',
            'is_active' => true
        ]);

        // 建立停用的對應
        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'phone',
            'wp_field_name' => '聯絡電話',
            'display_name' => '手機號碼',
            'field_type' => 'phone',
            'is_active' => false // 停用
        ]);

        $rawFormData = [
            '客戶名稱' => '測試客戶',
            '聯絡電話' => '0912345678'
        ];

        $result = $this->fieldMapper->mapFields('test-site.com', $rawFormData);

        // 啟用的對應應該生效
        $this->assertEquals('測試客戶', $result['name']);

        // 停用的對應不應該生效，該欄位應該在未對應清單中
        $this->assertArrayNotHasKey('phone', $result);
        $this->assertEquals('0912345678', $result['_unmapped_fields']['聯絡電話']);
    }

    /**
     * 測試相同WordPress欄位名稱的處理
     */
    public function test_handles_multiple_wordpress_field_names()
    {
        // 建立對應：系統的email欄位可以接受多個WordPress欄位名稱
        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'email',
            'wp_field_name' => 'Email',
            'display_name' => '電子郵件',
            'field_type' => 'email',
            'is_active' => true
        ]);

        $rawFormData = [
            'Email' => 'test@example.com',
            'email' => 'another@example.com' // 預設對應中也有這個
        ];

        $result = $this->fieldMapper->mapFields('test-site.com', $rawFormData);

        // 自訂對應應該優先
        $this->assertEquals('test@example.com', $result['email']);
        
        // 另一個email應該在未對應清單中（因為預設對應被自訂對應覆蓋）
        $this->assertEquals('another@example.com', $result['_unmapped_fields']['email']);
    }
}