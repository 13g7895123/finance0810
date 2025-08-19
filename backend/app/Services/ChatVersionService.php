<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
            $version = DB::table('chat_versions')
                ->where('key', 'global')
                ->value('version');
            return $version ?? 0;
        });
    }
    
    /**
     * 增加版本號（當有新訊息或更新時）
     */
    public function incrementVersion(): int
    {
        try {
            Cache::forget(self::CACHE_KEY);
            
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
     * 檢查客戶端版本是否需要更新
     */
    public function needsUpdate(int $clientVersion): bool
    {
        $currentVersion = $this->getCurrentVersion();
        return $clientVersion < $currentVersion;
    }
    
    /**
     * 獲取版本號之後的變化數據
     */
    public function getChangesSince(int $sinceVersion, $lineUserId = null, int $limit = 100)
    {
        try {
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
        $currentVersion = $this->getCurrentVersion();
        $totalConversations = DB::table('chat_conversations')->count();
        $versionedConversations = DB::table('chat_conversations')
            ->where('version', '>', 0)
            ->count();
        
        return [
            'current_version' => $currentVersion,
            'total_conversations' => $totalConversations,
            'versioned_conversations' => $versionedConversations,
            'versioning_coverage' => $totalConversations > 0 
                ? round(($versionedConversations / $totalConversations) * 100, 2) 
                : 0
        ];
    }
}