<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\VersionTrackingService;
use App\Models\ChatConversation;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class VersionTrackingTest extends TestCase
{
    use RefreshDatabase;
    
    private $versionService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->versionService = app(VersionTrackingService::class);
        
        // 設置初始版本序列
        DB::table('global_version_sequence')->insert([
            'current_version' => 1,
            'updated_at' => now()
        ]);
    }
    
    public function test_can_get_next_version()
    {
        $version1 = $this->versionService->getNextVersion();
        $version2 = $this->versionService->getNextVersion();
        
        $this->assertEquals($version1 + 1, $version2);
        $this->assertGreaterThan(1, $version1);
    }
    
    public function test_can_get_current_version()
    {
        $currentVersion = $this->versionService->getCurrentVersion();
        $this->assertGreaterThan(0, $currentVersion);
        
        // 獲取新版本後，當前版本應該更新
        $this->versionService->getNextVersion();
        $newCurrentVersion = $this->versionService->getCurrentVersion();
        $this->assertGreaterThan($currentVersion, $newCurrentVersion);
    }
    
    public function test_can_set_entity_version()
    {
        $customer = Customer::factory()->create();
        
        $version = $this->versionService->setEntityVersion(
            'customer',
            $customer->id,
            'update',
            ['name' => ['old' => 'Old Name', 'new' => 'New Name']]
        );
        
        $this->assertGreaterThan(0, $version);
        
        // 檢查實體版本是否更新
        $customer->refresh();
        $this->assertEquals($version, $customer->version);
        $this->assertNotNull($customer->version_updated_at);
        
        // 檢查版本追踪記錄
        $tracking = DB::table('version_tracking')
            ->where('entity_type', 'customer')
            ->where('entity_id', $customer->id)
            ->where('version', $version)
            ->first();
            
        $this->assertNotNull($tracking);
        $this->assertEquals('update', $tracking->operation);
        $this->assertNotNull($tracking->changes);
    }
    
    public function test_can_get_changes_since_version()
    {
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        
        $version1 = $this->versionService->setEntityVersion('customer', $customer1->id);
        $version2 = $this->versionService->setEntityVersion('customer', $customer2->id);
        
        $changes = $this->versionService->getChangesSinceVersion('customer', $version1);
        
        $this->assertCount(1, $changes);
        $this->assertEquals($customer2->id, $changes[0]->id);
        $this->assertEquals($version2, $changes[0]->version);
    }
    
    public function test_can_get_entity_versions()
    {
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        
        $version1 = $this->versionService->setEntityVersion('customer', $customer1->id);
        $version2 = $this->versionService->setEntityVersion('customer', $customer2->id);
        
        $versions = $this->versionService->getEntityVersions(
            'customer', 
            [$customer1->id, $customer2->id]
        );
        
        $this->assertCount(2, $versions);
        $this->assertEquals($version1, $versions[$customer1->id]);
        $this->assertEquals($version2, $versions[$customer2->id]);
    }
    
    public function test_can_detect_version_conflict()
    {
        $customer = Customer::factory()->create();
        $initialVersion = 1; // 假設初始版本
        
        // 模擬另一個進程更新了版本
        $newVersion = $this->versionService->setEntityVersion('customer', $customer->id);
        
        $hasConflict = $this->versionService->checkVersionConflict(
            'customer',
            $customer->id,
            $initialVersion
        );
        
        $this->assertTrue($hasConflict);
        
        // 使用正確版本應該沒有衝突
        $noConflict = $this->versionService->checkVersionConflict(
            'customer',
            $customer->id,
            $newVersion
        );
        
        $this->assertFalse($noConflict);
    }
    
    public function test_can_get_version_history()
    {
        $customer = Customer::factory()->create();
        
        // 創建多個版本變更
        $this->versionService->setEntityVersion('customer', $customer->id, 'create');
        $this->versionService->setEntityVersion('customer', $customer->id, 'update', ['field1' => 'change1']);
        $this->versionService->setEntityVersion('customer', $customer->id, 'update', ['field2' => 'change2']);
        
        $history = $this->versionService->getVersionHistory('customer', $customer->id, 5);
        
        $this->assertCount(3, $history);
        
        // 歷史應該按版本號降序排列
        $this->assertGreaterThan($history[1]->version, $history[0]->version);
        $this->assertGreaterThan($history[2]->version, $history[1]->version);
    }
    
    public function test_can_get_version_stats()
    {
        $customer = Customer::factory()->create();
        $this->versionService->setEntityVersion('customer', $customer->id, 'create');
        $this->versionService->setEntityVersion('customer', $customer->id, 'update');
        
        $stats = $this->versionService->getVersionStats();
        
        $this->assertArrayHasKey('current_version', $stats);
        $this->assertArrayHasKey('total_changes', $stats);
        $this->assertArrayHasKey('changes_by_type', $stats);
        $this->assertArrayHasKey('recent_changes', $stats);
        
        $this->assertGreaterThan(0, $stats['current_version']);
        $this->assertGreaterThan(0, $stats['total_changes']);
        $this->assertArrayHasKey('customer', $stats['changes_by_type']);
    }
    
    public function test_invalid_entity_type_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown entity type: invalid_type');
        
        $this->versionService->setEntityVersion('invalid_type', 1);
    }
    
    public function test_cleanup_old_versions()
    {
        $customer = Customer::factory()->create();
        
        // 創建一些變更記錄
        $this->versionService->setEntityVersion('customer', $customer->id, 'create');
        $this->versionService->setEntityVersion('customer', $customer->id, 'update');
        
        // 模擬舊記錄（手動插入過去的時間戳）
        DB::table('version_tracking')->insert([
            'entity_type' => 'customer',
            'entity_id' => $customer->id,
            'version' => 999,
            'operation' => 'old_update',
            'changes' => null,
            'user_id' => null,
            'created_at' => now()->subDays(60) // 60天前
        ]);
        
        $totalBefore = DB::table('version_tracking')->count();
        $deletedCount = $this->versionService->cleanupOldVersions(30); // 清理30天前的記錄
        $totalAfter = DB::table('version_tracking')->count();
        
        $this->assertEquals(1, $deletedCount);
        $this->assertEquals($totalBefore - 1, $totalAfter);
    }
}