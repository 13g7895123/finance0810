<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\IncrementalSyncService;
use App\Services\VersionTrackingService;
use App\Models\ChatConversation;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class IncrementalSyncTest extends TestCase
{
    use RefreshDatabase;
    
    private $syncService;
    private $versionService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->versionService = app(VersionTrackingService::class);
        $this->syncService = app(IncrementalSyncService::class);
        
        // 設置初始版本序列
        DB::table('global_version_sequence')->insert([
            'current_version' => 1,
            'updated_at' => now()
        ]);
    }
    
    public function test_can_get_full_sync_for_first_time()
    {
        // 創建一些測試數據
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        
        // 設置版本
        $this->versionService->setEntityVersion('customer', $customer1->id, 'create');
        $this->versionService->setEntityVersion('customer', $customer2->id, 'create');
        
        // 客戶端版本為 0，應該獲取全量同步
        $result = $this->syncService->getIncrementalUpdate('customer', 0, 10);
        
        $this->assertEquals('full', $result['sync_type']);
        $this->assertEquals(0, $result['client_version']);
        $this->assertGreaterThan(0, $result['current_version']);
        $this->assertCount(2, $result['data']);
    }
    
    public function test_can_get_incremental_changes()
    {
        // 創建初始數據
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        
        $version1 = $this->versionService->setEntityVersion('customer', $customer1->id, 'create');
        
        // 模擬客戶端已同步到 version1
        $clientVersion = $version1;
        
        // 創建新數據
        $version2 = $this->versionService->setEntityVersion('customer', $customer2->id, 'create');
        
        // 獲取增量更新
        $result = $this->syncService->getIncrementalUpdate('customer', $clientVersion, 10);
        
        $this->assertEquals('incremental', $result['sync_type']);
        $this->assertEquals($clientVersion, $result['client_version']);
        $this->assertGreaterThan($clientVersion, $result['current_version']);
        $this->assertCount(1, $result['changes']);
        $this->assertEquals('upsert', $result['changes'][0]['operation']);
        $this->assertEquals($customer2->id, $result['changes'][0]['data']->id);
    }
    
    public function test_can_detect_deleted_entities()
    {
        // 創建測試數據
        $customer = Customer::factory()->create();
        $version1 = $this->versionService->setEntityVersion('customer', $customer->id, 'create');
        
        // 模擬客戶端同步到 version1
        $clientVersion = $version1;
        
        // 刪除實體
        $customer->delete();
        $this->versionService->setEntityVersion('customer', $customer->id, 'delete');
        
        // 獲取增量更新
        $result = $this->syncService->getIncrementalUpdate('customer', $clientVersion, 10);
        
        $this->assertEquals('incremental', $result['sync_type']);
        $this->assertCount(1, $result['deletes']);
        $this->assertEquals('delete', $result['deletes'][0]['operation']);
        $this->assertEquals($customer->id, $result['deletes'][0]['id']);
    }
    
    public function test_should_use_full_sync_when_gap_too_large()
    {
        $customer = Customer::factory()->create();
        $currentVersion = $this->versionService->getCurrentVersion();
        
        // 模擬客戶端版本遠落後於服務器
        $oldClientVersion = $currentVersion - 2000; // 超過 maxIncrementalGap (1000)
        
        $result = $this->syncService->getIncrementalUpdate('customer', $oldClientVersion, 10);
        
        $this->assertEquals('full', $result['sync_type']);
    }
    
    public function test_should_use_full_sync_when_client_version_ahead()
    {
        $customer = Customer::factory()->create();
        $currentVersion = $this->versionService->getCurrentVersion();
        
        // 客戶端版本比服務器還新（異常情況）
        $futureClientVersion = $currentVersion + 100;
        
        $result = $this->syncService->getIncrementalUpdate('customer', $futureClientVersion, 10);
        
        $this->assertEquals('full', $result['sync_type']);
    }
    
    public function test_can_validate_data_integrity()
    {
        // 創建測試數據
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        
        $version1 = $this->versionService->setEntityVersion('customer', $customer1->id, 'create');
        $version2 = $this->versionService->setEntityVersion('customer', $customer2->id, 'create');
        
        // 模擬客戶端數據
        $clientData = [
            ['id' => $customer1->id, 'version' => $version1], // 正確版本
            ['id' => $customer2->id, 'version' => $version2 - 1], // 版本不匹配
            ['id' => 999, 'version' => 1] // 服務器不存在
        ];
        
        $errors = $this->syncService->validateDataIntegrity('customer', $clientData);
        
        $this->assertCount(2, $errors);
        
        // 檢查版本不匹配錯誤
        $versionMismatch = collect($errors)->firstWhere('type', 'version_mismatch');
        $this->assertEquals($customer2->id, $versionMismatch['id']);
        $this->assertEquals($version2 - 1, $versionMismatch['client_version']);
        $this->assertEquals($version2, $versionMismatch['server_version']);
        
        // 檢查服務器不存在錯誤
        $missingOnServer = collect($errors)->firstWhere('type', 'missing_on_server');
        $this->assertEquals(999, $missingOnServer['id']);
    }
    
    public function test_can_get_sync_stats()
    {
        // 創建測試數據
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        
        $this->versionService->setEntityVersion('customer', $customer1->id, 'create');
        $this->versionService->setEntityVersion('customer', $customer2->id, 'create');
        
        $stats = $this->syncService->getSyncStats('customer');
        
        $this->assertEquals('customer', $stats['entity_type']);
        $this->assertGreaterThan(0, $stats['current_version']);
        $this->assertEquals(2, $stats['total_entities']);
        $this->assertArrayHasKey('recent_changes', $stats);
        $this->assertArrayHasKey('last_updated', $stats);
    }
    
    public function test_throws_exception_for_invalid_entity_type()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown entity type: invalid_type');
        
        $this->syncService->getIncrementalUpdate('invalid_type', 0);
    }
    
    public function test_handles_empty_client_data_in_validation()
    {
        $errors = $this->syncService->validateDataIntegrity('customer', []);
        
        $this->assertEmpty($errors);
    }
    
    public function test_has_more_flag_works_correctly()
    {
        // 創建超過限制數量的數據
        $customers = Customer::factory()->count(5)->create();
        
        foreach ($customers as $customer) {
            $this->versionService->setEntityVersion('customer', $customer->id, 'create');
        }
        
        // 使用較小的限制
        $result = $this->syncService->getIncrementalUpdate('customer', 0, 3);
        
        $this->assertTrue($result['has_more']);
        $this->assertCount(3, $result['data']);
    }
}