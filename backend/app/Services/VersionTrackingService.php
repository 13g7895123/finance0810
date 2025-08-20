<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VersionTrackingService
{
    private $cachePrefix = 'version_';
    private $cacheTTL = 300; // 5 分鐘
    
    /**
     * 獲取下一個全局版本號
     */
    public function getNextVersion(): int
    {
        return DB::transaction(function () {
            $result = DB::table('global_version_sequence')
                ->lockForUpdate()
                ->first();
            
            $nextVersion = $result->current_version + 1;
            
            DB::table('global_version_sequence')
                ->update([
                    'current_version' => $nextVersion,
                    'updated_at' => now()
                ]);
            
            return $nextVersion;
        });
    }
    
    /**
     * 獲取當前全局版本號
     */
    public function getCurrentVersion(): int
    {
        $cacheKey = $this->cachePrefix . 'current';
        
        return Cache::remember($cacheKey, $this->cacheTTL, function () {
            $result = DB::table('global_version_sequence')->first();
            return $result ? $result->current_version : 1;
        });
    }
    
    /**
     * 為實體設置版本
     */
    public function setEntityVersion(string $entityType, int $entityId, string $operation = 'update', array $changes = null, int $userId = null): int
    {
        $version = $this->getNextVersion();
        
        try {
            // 更新實體版本
            $this->updateEntityVersionField($entityType, $entityId, $version);
            
            // 記錄版本追踪
            $this->recordVersionChange($entityType, $entityId, $version, $operation, $changes, $userId);
            
            // 清除相關緩存
            $this->clearVersionCache($entityType, $entityId);
            
            return $version;
            
        } catch (\Exception $e) {
            Log::error('Failed to set entity version', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'version' => $version,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 更新實體版本字段
     */
    private function updateEntityVersionField(string $entityType, int $entityId, int $version): void
    {
        $tableName = $this->getTableName($entityType);
        
        DB::table($tableName)
            ->where('id', $entityId)
            ->update([
                'version' => $version,
                'version_updated_at' => now()
            ]);
    }
    
    /**
     * 記錄版本變更
     */
    private function recordVersionChange(string $entityType, int $entityId, int $version, string $operation, array $changes = null, int $userId = null): void
    {
        DB::table('version_tracking')->insert([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'version' => $version,
            'operation' => $operation,
            'changes' => $changes ? json_encode($changes) : null,
            'user_id' => $userId,
            'created_at' => now()
        ]);
    }
    
    /**
     * 獲取版本變更歷史
     */
    public function getVersionHistory(string $entityType, int $entityId, int $limit = 10): array
    {
        return DB::table('version_tracking')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('version', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
    
    /**
     * 獲取自指定版本以來的變更
     */
    public function getChangesSinceVersion(string $entityType, int $sinceVersion, int $limit = 100): array
    {
        $tableName = $this->getTableName($entityType);
        
        $cacheKey = $this->cachePrefix . "changes_{$entityType}_{$sinceVersion}_{$limit}";
        
        return Cache::remember($cacheKey, 60, function () use ($tableName, $sinceVersion, $limit) {
            return DB::table($tableName)
                ->where('version', '>', $sinceVersion)
                ->orderBy('version', 'asc')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }
    
    /**
     * 批量獲取實體版本
     */
    public function getEntityVersions(string $entityType, array $entityIds): array
    {
        $tableName = $this->getTableName($entityType);
        
        return DB::table($tableName)
            ->whereIn('id', $entityIds)
            ->pluck('version', 'id')
            ->toArray();
    }
    
    /**
     * 檢測版本衝突
     */
    public function checkVersionConflict(string $entityType, int $entityId, int $expectedVersion): bool
    {
        $tableName = $this->getTableName($entityType);
        
        $currentVersion = DB::table($tableName)
            ->where('id', $entityId)
            ->value('version');
        
        return $currentVersion !== $expectedVersion;
    }
    
    /**
     * 解析實體類型到表名
     */
    private function getTableName(string $entityType): string
    {
        $mapping = [
            'chat' => 'chat_conversations',
            'customer' => 'customers',
            'user' => 'users'
        ];
        
        if (!isset($mapping[$entityType])) {
            throw new \InvalidArgumentException("Unknown entity type: {$entityType}");
        }
        
        return $mapping[$entityType];
    }
    
    /**
     * 清除版本緩存
     */
    private function clearVersionCache(string $entityType, int $entityId): void
    {
        $patterns = [
            $this->cachePrefix . 'current',
            $this->cachePrefix . "changes_{$entityType}_*",
            $this->cachePrefix . "entity_{$entityType}_{$entityId}"
        ];
        
        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
    
    /**
     * 獲取版本統計
     */
    public function getVersionStats(): array
    {
        return [
            'current_version' => $this->getCurrentVersion(),
            'total_changes' => DB::table('version_tracking')->count(),
            'changes_by_type' => DB::table('version_tracking')
                ->select('entity_type', DB::raw('count(*) as count'))
                ->groupBy('entity_type')
                ->pluck('count', 'entity_type')
                ->toArray(),
            'recent_changes' => DB::table('version_tracking')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->toArray()
        ];
    }
    
    /**
     * 清理舊版本記錄
     */
    public function cleanupOldVersions(int $daysToKeep = 30): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        return DB::table('version_tracking')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }
}