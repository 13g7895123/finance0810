<?php

namespace App\Services;

use App\Services\VersionTrackingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class IncrementalSyncService
{
    private $versionService;
    private $maxIncrementalGap = 1000; // 最大增量間隔
    
    public function __construct(VersionTrackingService $versionService)
    {
        $this->versionService = $versionService;
    }
    
    /**
     * 獲取增量更新
     */
    public function getIncrementalUpdate(string $entityType, int $clientVersion, int $limit = 100): array
    {
        $currentVersion = $this->versionService->getCurrentVersion();
        
        // 檢查是否需要全量同步
        if ($this->shouldUseFullSync($clientVersion, $currentVersion)) {
            return $this->getFullSync($entityType, $limit);
        }
        
        // 獲取增量變更
        $changes = $this->getIncrementalChanges($entityType, $clientVersion, $limit);
        $deletes = $this->getDeletedEntities($entityType, $clientVersion);
        
        return [
            'sync_type' => 'incremental',
            'client_version' => $clientVersion,
            'current_version' => $currentVersion,
            'changes' => $changes,
            'deletes' => $deletes,
            'has_more' => count($changes) === $limit
        ];
    }
    
    /**
     * 判斷是否需要全量同步
     */
    private function shouldUseFullSync(int $clientVersion, int $currentVersion): bool
    {
        // 首次同步
        if ($clientVersion === 0) {
            return true;
        }
        
        // 版本差距過大
        if (($currentVersion - $clientVersion) > $this->maxIncrementalGap) {
            return true;
        }
        
        // 客戶端版本過舊
        if ($clientVersion > $currentVersion) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 獲取全量同步數據
     */
    private function getFullSync(string $entityType, int $limit): array
    {
        $tableName = $this->getTableName($entityType);
        
        $data = DB::table($tableName)
            ->whereNull('deleted_at')
            ->orderBy('version', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
        
        return [
            'sync_type' => 'full',
            'client_version' => 0,
            'current_version' => $this->versionService->getCurrentVersion(),
            'data' => $data,
            'has_more' => count($data) === $limit
        ];
    }
    
    /**
     * 獲取增量變更
     */
    private function getIncrementalChanges(string $entityType, int $sinceVersion, int $limit): array
    {
        $tableName = $this->getTableName($entityType);
        
        return DB::table($tableName)
            ->where('version', '>', $sinceVersion)
            ->whereNull('deleted_at')
            ->orderBy('version', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'operation' => 'upsert',
                    'data' => $item
                ];
            })
            ->toArray();
    }
    
    /**
     * 獲取已刪除的實體
     */
    private function getDeletedEntities(string $entityType, int $sinceVersion): array
    {
        return DB::table('version_tracking')
            ->where('entity_type', $entityType)
            ->where('version', '>', $sinceVersion)
            ->where('operation', 'delete')
            ->select('entity_id', 'version')
            ->get()
            ->map(function ($item) {
                return [
                    'operation' => 'delete',
                    'id' => $item->entity_id,
                    'version' => $item->version
                ];
            })
            ->toArray();
    }
    
    /**
     * 校驗數據完整性
     */
    public function validateDataIntegrity(string $entityType, array $clientData): array
    {
        $errors = [];
        $clientIds = array_column($clientData, 'id');
        $serverData = $this->getServerData($entityType, $clientIds);
        
        foreach ($clientData as $item) {
            $serverId = $item['id'];
            $serverItem = $serverData[$serverId] ?? null;
            
            if (!$serverItem) {
                $errors[] = [
                    'type' => 'missing_on_server',
                    'id' => $serverId,
                    'client_version' => $item['version']
                ];
                continue;
            }
            
            if ($serverItem !== $item['version']) {
                $errors[] = [
                    'type' => 'version_mismatch',
                    'id' => $serverId,
                    'client_version' => $item['version'],
                    'server_version' => $serverItem
                ];
            }
        }
        
        return $errors;
    }
    
    /**
     * 獲取服務器端數據版本
     */
    private function getServerData(string $entityType, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        
        $tableName = $this->getTableName($entityType);
        
        return DB::table($tableName)
            ->whereIn('id', $ids)
            ->pluck('version', 'id')
            ->toArray();
    }
    
    /**
     * 獲取實體類型對應的表名
     */
    private function getTableName(string $entityType): string
    {
        $mapping = [
            'chat' => 'chat_conversations',
            'customer' => 'customers'
        ];
        
        if (!isset($mapping[$entityType])) {
            throw new \InvalidArgumentException("Unknown entity type: {$entityType}");
        }
        
        return $mapping[$entityType];
    }
    
    /**
     * 獲取同步統計信息
     */
    public function getSyncStats(string $entityType): array
    {
        $tableName = $this->getTableName($entityType);
        $currentVersion = $this->versionService->getCurrentVersion();
        
        return [
            'entity_type' => $entityType,
            'current_version' => $currentVersion,
            'total_entities' => DB::table($tableName)->whereNull('deleted_at')->count(),
            'recent_changes' => DB::table('version_tracking')
                ->where('entity_type', $entityType)
                ->where('created_at', '>=', now()->subHours(24))
                ->count(),
            'last_updated' => DB::table($tableName)
                ->whereNull('deleted_at')
                ->max('version_updated_at')
        ];
    }
}