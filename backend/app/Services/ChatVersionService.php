<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class ChatVersionService
{
    private const CACHE_KEY = 'chat_global_version';
    private const CACHE_TTL = 60; // 60 秒
    
    /**
     * 獲取當前全域版本號
     */
    public function getCurrentVersion(): int
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            try {
                $version = DB::table('chat_versions')
                    ->where('key', 'global')
                    ->value('version');
                return $version ?? 0;
            } catch (\Exception $e) {
                Log::warning('Failed to get current version from chat_versions table, using fallback', [
                    'error' => $e->getMessage()
                ]);
                // 回退到檢查是否有其他版本追蹤機制
                return $this->getFallbackVersion();
            }
        });
    }
    
    /**
     * 回退版本獲取方法
     */
    private function getFallbackVersion(): int
    {
        try {
            // 嘗試從 global_version_sequence 表獲取
            if (Schema::hasTable('global_version_sequence')) {
                $version = DB::table('global_version_sequence')
                    ->value('current_version');
                return $version ?? 0;
            }
            
            // 如果都沒有，返回 0
            return 0;
        } catch (\Exception $e) {
            Log::error('All version tracking fallbacks failed', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * 增加版本號（當有新訊息或更新時）
     */
    public function incrementVersion(): int
    {
        try {
            Cache::forget(self::CACHE_KEY);
            
            // 檢查 chat_versions 表是否存在
            if (!Schema::hasTable('chat_versions')) {
                Log::warning('chat_versions table does not exist, using fallback increment method');
                return $this->incrementVersionFallback();
            }
            
            // 使用原子操作確保版本號正確遞增
            DB::transaction(function () {
                DB::table('chat_versions')
                    ->where('key', 'global')
                    ->increment('version', 1, ['updated_at' => now()]);
            });
            
            $newVersion = $this->getCurrentVersion();
            
            Log::info('Chat version incremented', ['new_version' => $newVersion]);
            
            return $newVersion;
            
        } catch (\Exception $e) {
            Log::error('Failed to increment chat version', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 如果增加失敗，返回當前版本
            return $this->getCurrentVersion();
        }
    }
    
    /**
     * 回退版本遞增方法
     */
    private function incrementVersionFallback(): int
    {
        try {
            // 嘗試使用 global_version_sequence 表
            if (Schema::hasTable('global_version_sequence')) {
                DB::transaction(function () {
                    DB::table('global_version_sequence')
                        ->increment('current_version', 1, ['updated_at' => now()]);
                });
                
                return $this->getFallbackVersion();
            }
            
            // 如果都沒有，返回時間戳作為版本號
            $version = time();
            Log::info('Using timestamp as version fallback', ['version' => $version]);
            return $version;
            
        } catch (\Exception $e) {
            Log::error('Fallback version increment failed', [
                'error' => $e->getMessage()
            ]);
            return time();
        }
    }
    
    /**
     * 檢查客戶端版本是否需要更新
     */
    public function needsUpdate(int $clientVersion): bool
    {
        try {
            $currentVersion = $this->getCurrentVersion();
            return $clientVersion < $currentVersion;
        } catch (\Exception $e) {
            Log::warning('Failed to check version update need', [
                'client_version' => $clientVersion,
                'error' => $e->getMessage()
            ]);
            // 如果無法檢查版本，假設需要更新以確保數據同步
            return true;
        }
    }
    
    /**
     * 獲取版本號之後的變化數據
     */
    public function getChangesSince(int $sinceVersion, $lineUserId = null, int $limit = 100)
    {
        try {
            // 檢查 chat_conversations 表是否有 version 欄位
            if (!Schema::hasColumn('chat_conversations', 'version')) {
                Log::warning('chat_conversations table does not have version column, returning empty changes');
                return collect();
            }
            
            $query = DB::table('chat_conversations')
                ->where('version', '>', $sinceVersion)
                ->orderBy('version', 'asc')
                ->limit($limit);
                
            if ($lineUserId) {
                $query->where('line_user_id', $lineUserId);
            }
            
            $changes = $query->get();
            
            Log::info('Retrieved changes since version', [
                'since_version' => $sinceVersion,
                'line_user_id' => $lineUserId,
                'changes_count' => $changes->count()
            ]);
            
            return $changes;
            
        } catch (\Exception $e) {
            Log::error('Failed to get changes since version', [
                'since_version' => $sinceVersion,
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }
    
    /**
     * 為特定對話設置新版本號
     */
    public function setVersionForConversation(int $conversationId): int
    {
        try {
            $newVersion = $this->incrementVersion();
            
            DB::table('chat_conversations')
                ->where('id', $conversationId)
                ->update(['version' => $newVersion]);
            
            return $newVersion;
            
        } catch (\Exception $e) {
            Log::error('Failed to set version for conversation', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            
            return $this->getCurrentVersion();
        }
    }
    
    /**
     * 重置版本號（僅用於開發和測試）
     */
    public function resetVersion(): void
    {
        if (app()->environment('production')) {
            throw new \Exception('Cannot reset version in production environment');
        }
        
        Cache::forget(self::CACHE_KEY);
        
        DB::table('chat_versions')
            ->where('key', 'global')
            ->update(['version' => 0, 'updated_at' => now()]);
        
        DB::table('chat_conversations')
            ->update(['version' => 0]);
            
        Log::warning('Chat version has been reset');
    }
    
    /**
     * 獲取版本統計信息
     */
    public function getVersionStats(): array
    {
        try {
            $currentVersion = $this->getCurrentVersion();
            
            $totalConversations = 0;
            $versionedConversations = 0;
            
            if (Schema::hasTable('chat_conversations')) {
                $totalConversations = DB::table('chat_conversations')->count();
                
                if (Schema::hasColumn('chat_conversations', 'version')) {
                    $versionedConversations = DB::table('chat_conversations')
                        ->where('version', '>', 0)
                        ->count();
                }
            }
            
            return [
                'current_version' => $currentVersion,
                'total_conversations' => $totalConversations,
                'versioned_conversations' => $versionedConversations,
                'versioning_coverage' => $totalConversations > 0 
                    ? round(($versionedConversations / $totalConversations) * 100, 2) 
                    : 0,
                'system_health' => $this->checkSystemHealth()
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get version stats', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'current_version' => 0,
                'total_conversations' => 0,
                'versioned_conversations' => 0,
                'versioning_coverage' => 0,
                'system_health' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 檢查系統健康狀態
     */
    public function checkSystemHealth(): string
    {
        try {
            // 檢查必要的資料表是否存在
            $requiredTables = ['chat_conversations'];
            $optionalTables = ['chat_versions', 'global_version_sequence'];
            
            foreach ($requiredTables as $table) {
                if (!Schema::hasTable($table)) {
                    return 'critical_error';
                }
            }
            
            // 檢查是否有版本追蹤能力
            $hasVersionTracking = false;
            foreach ($optionalTables as $table) {
                if (Schema::hasTable($table)) {
                    $hasVersionTracking = true;
                    break;
                }
            }
            
            if (!$hasVersionTracking) {
                return 'degraded';
            }
            
            // 檢查版本欄位
            if (!Schema::hasColumn('chat_conversations', 'version')) {
                return 'partial';
            }
            
            return 'healthy';
            
        } catch (\Exception $e) {
            Log::error('System health check failed', [
                'error' => $e->getMessage()
            ]);
            return 'error';
        }
    }
    
    /**
     * 初始化版本系統（安全模式）
     */
    public function initializeVersionSystem(): bool
    {
        try {
            // 如果 chat_versions 表不存在，嘗試創建
            if (!Schema::hasTable('chat_versions')) {
                Log::info('Creating chat_versions table');
                Schema::create('chat_versions', function (Blueprint $table) {
                    $table->id();
                    $table->string('key')->unique()->index();
                    $table->unsignedBigInteger('version')->default(0)->index();
                    $table->timestamps();
                });
                
                // 插入初始記錄
                DB::table('chat_versions')->insert([
                    'key' => 'global',
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to initialize version system', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}