<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteFieldMapping;
use Laravel\Sanctum\Sanctum;

/**
 * Point 61: WordPress網站欄位對應功能測試
 */
class WebsiteFieldMappingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $website;

    protected function setUp(): void
    {
        parent::setUp();

        // 創建測試管理員用戶
        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'role' => 'admin'
        ]);

        // 創建測試網站
        $this->website = Website::create([
            'name' => '測試網站',
            'domain' => 'test-site.com',
            'url' => 'https://test-site.com',
            'status' => 'active',
            'type' => 'wordpress',
            'webhook_enabled' => true
        ]);

        // 認證管理員用戶
        Sanctum::actingAs($this->admin);
    }

    /**
     * 測試取得網站欄位對應列表
     */
    public function test_can_get_website_field_mappings()
    {
        // 建立測試欄位對應
        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'name',
            'wp_field_name' => '姓名',
            'display_name' => '客戶姓名',
            'field_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 10
        ]);

        $response = $this->get("/api/websites/{$this->website->id}/field-mappings");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id',
                             'website_id',
                             'system_field',
                             'wp_field_name',
                             'display_name',
                             'field_type',
                             'is_required',
                             'is_active'
                         ]
                     ],
                     'system_fields',
                     'field_types'
                 ]);

        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals('name', $response->json('data.0.system_field'));
    }

    /**
     * 測試儲存欄位對應設定
     */
    public function test_can_save_field_mappings()
    {
        $mappingData = [
            'mappings' => [
                [
                    'system_field' => 'name',
                    'wp_field_name' => '姓名',
                    'display_name' => '客戶姓名',
                    'field_type' => 'text',
                    'is_required' => true,
                    'sort_order' => 10
                ],
                [
                    'system_field' => 'phone',
                    'wp_field_name' => '手機號碼',
                    'display_name' => '聯絡電話',
                    'field_type' => 'phone',
                    'is_required' => true,
                    'sort_order' => 20
                ]
            ]
        ];

        $response = $this->post("/api/websites/{$this->website->id}/field-mappings", $mappingData);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => '欄位對應設定已更新'
                 ]);

        // 驗證資料庫中的記錄
        $this->assertDatabaseHas('website_field_mappings', [
            'website_id' => $this->website->id,
            'system_field' => 'name',
            'wp_field_name' => '姓名'
        ]);

        $this->assertDatabaseHas('website_field_mappings', [
            'website_id' => $this->website->id,
            'system_field' => 'phone',
            'wp_field_name' => '手機號碼'
        ]);

        // 確認總共建立了2個對應
        $this->assertEquals(2, WebsiteFieldMapping::where('website_id', $this->website->id)->count());
    }

    /**
     * 測試欄位對應驗證失敗
     */
    public function test_field_mapping_validation_fails_with_invalid_data()
    {
        $invalidData = [
            'mappings' => [
                [
                    'system_field' => '', // 必填欄位留空
                    'wp_field_name' => '姓名',
                    'display_name' => '客戶姓名',
                    'field_type' => 'text'
                ]
            ]
        ];

        $response = $this->post("/api/websites/{$this->website->id}/field-mappings", $invalidData);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'error',
                     'errors'
                 ]);
    }

    /**
     * 測試重複系統欄位驗證
     */
    public function test_prevents_duplicate_system_fields()
    {
        $duplicateData = [
            'mappings' => [
                [
                    'system_field' => 'name',
                    'wp_field_name' => '姓名',
                    'display_name' => '客戶姓名',
                    'field_type' => 'text',
                    'is_required' => true
                ],
                [
                    'system_field' => 'name', // 重複的系統欄位
                    'wp_field_name' => '客戶名稱',
                    'display_name' => '客戶名稱',
                    'field_type' => 'text',
                    'is_required' => false
                ]
            ]
        ];

        $response = $this->post("/api/websites/{$this->website->id}/field-mappings", $duplicateData);

        $response->assertStatus(422)
                 ->assertJsonPath('error', '欄位對應設定驗證失敗');
    }

    /**
     * 測試建立預設欄位對應
     */
    public function test_can_create_default_field_mappings()
    {
        $response = $this->post("/api/websites/{$this->website->id}/field-mappings/defaults");

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => '預設欄位對應已建立'
                 ])
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'system_field',
                             'wp_field_name',
                             'display_name',
                             'field_type'
                         ]
                     ]
                 ]);

        // 驗證預設對應已建立
        $this->assertDatabaseHas('website_field_mappings', [
            'website_id' => $this->website->id,
            'system_field' => 'name',
            'wp_field_name' => '姓名'
        ]);

        $this->assertDatabaseHas('website_field_mappings', [
            'website_id' => $this->website->id,
            'system_field' => 'phone',
            'wp_field_name' => '手機號碼'
        ]);
    }

    /**
     * 測試預防重複建立預設對應
     */
    public function test_prevents_duplicate_default_mappings()
    {
        // 先建立一個對應
        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'name',
            'wp_field_name' => '姓名',
            'display_name' => '客戶姓名',
            'field_type' => 'text',
            'is_active' => true
        ]);

        $response = $this->post("/api/websites/{$this->website->id}/field-mappings/defaults");

        $response->assertStatus(409)
                 ->assertJson([
                     'error' => '該網站已有欄位對應設定'
                 ]);
    }

    /**
     * 測試欄位對應測試功能
     */
    public function test_can_test_field_mappings()
    {
        // 建立測試對應
        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'name',
            'wp_field_name' => '姓名',
            'display_name' => '客戶姓名',
            'field_type' => 'text',
            'is_active' => true
        ]);

        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'phone',
            'wp_field_name' => '手機號碼',
            'display_name' => '聯絡電話',
            'field_type' => 'phone',
            'is_active' => true
        ]);

        $testData = [
            'test_data' => [
                '姓名' => '測試客戶',
                '手機號碼' => '0912345678',
                '未對應欄位' => '測試值'
            ]
        ];

        $response = $this->post("/api/websites/{$this->website->id}/field-mappings/test", $testData);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'original_data',
                     'mapped_result',
                     'mapped_fields_count',
                     'unmapped_fields_count'
                 ]);

        $mappedResult = $response->json('mapped_result');
        $this->assertEquals('測試客戶', $mappedResult['name']);
        $this->assertEquals('0912345678', $mappedResult['phone']);
        $this->assertEquals(1, $response->json('unmapped_fields_count'));
    }

    /**
     * 測試取得系統欄位清單
     */
    public function test_can_get_system_fields()
    {
        $response = $this->get('/api/field-mappings/system-fields');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'system_fields' => [
                         'name' => [
                             'label',
                             'type',
                             'required',
                             'description'
                         ]
                     ],
                     'field_types'
                 ]);

        $systemFields = $response->json('system_fields');
        $this->assertArrayHasKey('name', $systemFields);
        $this->assertArrayHasKey('phone', $systemFields);
        $this->assertArrayHasKey('email', $systemFields);
    }

    /**
     * 測試未授權用戶無法存取
     */
    public function test_unauthorized_user_cannot_access_field_mappings()
    {
        // 移除認證
        auth()->logout();

        $response = $this->get("/api/websites/{$this->website->id}/field-mappings");

        $response->assertStatus(401);
    }

    /**
     * 測試不存在的網站
     */
    public function test_returns_error_for_non_existent_website()
    {
        $response = $this->get("/api/websites/99999/field-mappings");

        $response->assertStatus(404);
    }

    /**
     * 測試更新現有對應會刪除舊資料
     */
    public function test_updating_mappings_replaces_existing_data()
    {
        // 建立初始對應
        WebsiteFieldMapping::create([
            'website_id' => $this->website->id,
            'system_field' => 'name',
            'wp_field_name' => '姓名',
            'display_name' => '客戶姓名',
            'field_type' => 'text',
            'is_active' => true
        ]);

        $this->assertEquals(1, WebsiteFieldMapping::where('website_id', $this->website->id)->count());

        // 更新為不同的對應
        $newMappingData = [
            'mappings' => [
                [
                    'system_field' => 'phone',
                    'wp_field_name' => '電話',
                    'display_name' => '聯絡電話',
                    'field_type' => 'phone',
                    'is_required' => true
                ]
            ]
        ];

        $response = $this->post("/api/websites/{$this->website->id}/field-mappings", $newMappingData);

        $response->assertStatus(200);

        // 驗證舊對應已刪除，新對應已建立
        $this->assertEquals(1, WebsiteFieldMapping::where('website_id', $this->website->id)->count());
        $this->assertDatabaseMissing('website_field_mappings', [
            'website_id' => $this->website->id,
            'system_field' => 'name'
        ]);
        $this->assertDatabaseHas('website_field_mappings', [
            'website_id' => $this->website->id,
            'system_field' => 'phone',
            'wp_field_name' => '電話'
        ]);
    }
}