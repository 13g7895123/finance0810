<?php

namespace App\Observers;

use App\Services\VersionTrackingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class VersionedModelObserver
{
    private $versionService;
    
    public function __construct(VersionTrackingService $versionService)
    {
        $this->versionService = $versionService;
    }
    
    /**
     * 監聽模型創建事件
     */
    public function created(Model $model)
    {
        $this->updateVersion($model, 'create');
    }
    
    /**
     * 監聽模型更新事件
     */
    public function updated(Model $model)
    {
        // 只有在實際有變更時才更新版本
        if ($model->wasChanged() && !$model->wasChanged(['version', 'version_updated_at'])) {
            $changes = $this->getChanges($model);
            $this->updateVersion($model, 'update', $changes);
        }
    }
    
    /**
     * 監聽模型刪除事件
     */
    public function deleted(Model $model)
    {
        $this->updateVersion($model, 'delete');
    }
    
    /**
     * 更新模型版本
     */
    private function updateVersion(Model $model, string $operation, array $changes = null)
    {
        try {
            $entityType = $this->getEntityType($model);
            $userId = Auth::id();
            
            $version = $this->versionService->setEntityVersion(
                $entityType,
                $model->id,
                $operation,
                $changes,
                $userId
            );
            
            // 更新模型實例的版本號（避免觸發額外事件）
            if ($operation !== 'delete') {
                $model->setAttribute('version', $version);
                $model->setAttribute('version_updated_at', now());
            }
            
        } catch (\Exception $e) {
            \Log::error('Failed to update model version', [
                'model' => get_class($model),
                'id' => $model->id,
                'operation' => $operation,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 獲取實體類型
     */
    private function getEntityType(Model $model): string
    {
        $mapping = [
            'App\Models\ChatConversation' => 'chat',
            'App\Models\Customer' => 'customer',
            'App\Models\User' => 'user'
        ];
        
        $class = get_class($model);
        
        return $mapping[$class] ?? strtolower(class_basename($model));
    }
    
    /**
     * 獲取變更內容
     */
    private function getChanges(Model $model): array
    {
        $changes = [];
        
        foreach ($model->getChanges() as $key => $newValue) {
            // 跳過版本相關字段
            if (in_array($key, ['version', 'version_updated_at', 'updated_at'])) {
                continue;
            }
            
            $changes[$key] = [
                'old' => $model->getOriginal($key),
                'new' => $newValue
            ];
        }
        
        return $changes;
    }
}