<?php

namespace App\Services;

use App\Models\ChatConversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ChatIncrementalService
{
    private $versionService;
    
    public function __construct(ChatVersionService $versionService)
    {
        $this->versionService = $versionService;
    }
    
    /**
     * 獲取對話列表的增量更新
     */
    public function getConversationListChanges($userId, $sinceVersion, $sinceTimestamp = null)
    {
        $currentVersion = $this->versionService->getCurrentVersion();
        
        // 如果版本差距太大，返回部分數據
        $isPartial = ($currentVersion - $sinceVersion) > 100;
        
        // 獲取變更的對話
        $changes = $this->detectConversationChanges($userId, $sinceVersion, $sinceTimestamp);
        
        return [
            'version' => $currentVersion,
            'added' => $changes['added'],
            'updated' => $changes['updated'],
            'removed' => $changes['removed'],
            'partial' => $isPartial,
        ];
    }
    
    /**
     * 獲取單個對話的消息增量更新
     */
    public function getMessageListChanges($lineUserId, $sinceVersion, $sinceTimestamp = null)
    {
        $currentVersion = $this->versionService->getCurrentVersion();
        
        // 查詢新消息
        $newMessages = ChatConversation::where('line_user_id', $lineUserId)
            ->where('version', '>', $sinceVersion)
            ->orderBy('message_timestamp', 'asc')
            ->limit(50)
            ->get();
            
        // 查詢已更新的消息（狀態變更等）
        $updatedMessages = $this->getUpdatedMessages($lineUserId, $sinceVersion);
        
        // 查詢已刪除的消息ID
        $removedIds = $this->getRemovedMessageIds($lineUserId, $sinceVersion);
        
        return [
            'version' => $currentVersion,
            'added' => $newMessages,
            'updated' => $updatedMessages,
            'removed' => $removedIds,
            'partial' => false,
        ];
    }
    
    /**
     * 檢測對話列表的變化
     */
    private function detectConversationChanges($userId, $sinceVersion, $sinceTimestamp)
    {
        // 使用緩存記錄每個用戶的對話列表快照
        $cacheKey = "chat_snapshot_{$userId}_{$sinceVersion}";
        $previousSnapshot = Cache::get($cacheKey, []);
        
        // 獲取當前對話列表
        $currentConversations = $this->getCurrentConversations($userId);
        
        // 比對變化
        $added = [];
        $updated = [];
        $removed = [];
        
        $previousIds = collect($previousSnapshot)->pluck('line_user_id')->toArray();
        $currentIds = $currentConversations->pluck('line_user_id')->toArray();
        
        // 新增的對話
        $addedIds = array_diff($currentIds, $previousIds);
        foreach ($addedIds as $id) {
            $added[] = $currentConversations->firstWhere('line_user_id', $id);
        }
        
        // 已更新的對話
        foreach ($currentConversations as $current) {
            $previous = collect($previousSnapshot)->firstWhere('line_user_id', $current->line_user_id);
            if ($previous && $this->hasConversationChanged($previous, $current)) {
                $updated[] = $current;
            }
        }
        
        // 已移除的對話
        $removed = array_diff($previousIds, $currentIds);
        
        // 更新快照緩存
        $currentVersion = $this->versionService->getCurrentVersion();
        $newCacheKey = "chat_snapshot_{$userId}_{$currentVersion}";
        Cache::put($newCacheKey, $currentConversations->toArray(), 3600);
        
        return compact('added', 'updated', 'removed');
    }
    
    /**
     * 獲取當前對話列表
     */
    private function getCurrentConversations($userId)
    {
        $query = DB::table('chat_conversations')
            ->select([
                'line_user_id',
                'customer_id',
                DB::raw('MAX(message_timestamp) as last_message_time'),
                DB::raw('MAX(version) as last_version'),
                DB::raw('COUNT(CASE WHEN status = "unread" AND is_from_customer = 1 THEN 1 END) as unread_count')
            ])
            ->groupBy('line_user_id', 'customer_id')
            ->orderBy('last_message_time', 'desc');
        
        // 根據用戶權限過濾（如果提供了userId）
        if ($userId) {
            // 這裡可以根據用戶權限添加過濾條件
            // 例如：業務人員只能看到分配給自己的客戶
            // $query->whereExists(function ($subquery) use ($userId) {
            //     $subquery->select(DB::raw(1))
            //         ->from('customers')
            //         ->whereColumn('customers.id', 'chat_conversations.customer_id')
            //         ->where('customers.assigned_to', $userId);
            // });
        }
        
        return $query->get();
    }
    
    /**
     * 檢查對話是否有變化
     */
    private function hasConversationChanged($previous, $current)
    {
        // 處理陣列和物件的差異
        $prevTime = is_array($previous) ? $previous['last_message_time'] : $previous->last_message_time;
        $prevCount = is_array($previous) ? $previous['unread_count'] : $previous->unread_count;
        $prevVersion = is_array($previous) ? $previous['last_version'] : $previous->last_version;
        
        return $prevTime !== $current->last_message_time ||
               $prevCount !== $current->unread_count ||
               $prevVersion !== $current->last_version;
    }
    
    /**
     * 獲取已更新的消息
     */
    private function getUpdatedMessages($lineUserId, $sinceVersion)
    {
        // 檢查變更追蹤表是否存在
        if (!$this->tableExists('chat_conversation_updates')) {
            // 如果表不存在，暫時返回空集合
            return collect();
        }
        
        // 查詢狀態變更記錄
        return DB::table('chat_conversation_updates')
            ->where('line_user_id', $lineUserId)
            ->where('update_version', '>', $sinceVersion)
            ->where('update_type', 'status_change')
            ->get()
            ->map(function ($update) {
                return ChatConversation::find($update->conversation_id);
            })
            ->filter();
    }
    
    /**
     * 獲取已刪除的消息ID
     */
    private function getRemovedMessageIds($lineUserId, $sinceVersion)
    {
        // 檢查刪除追蹤表是否存在
        if (!$this->tableExists('chat_conversation_deletes')) {
            // 如果表不存在，暫時返回空陣列
            return [];
        }
        
        return DB::table('chat_conversation_deletes')
            ->where('line_user_id', $lineUserId)
            ->where('delete_version', '>', $sinceVersion)
            ->pluck('conversation_id')
            ->toArray();
    }
    
    /**
     * 檢查表是否存在
     */
    private function tableExists($tableName)
    {
        try {
            return DB::getSchemaBuilder()->hasTable($tableName);
        } catch (\Exception $e) {
            return false;
        }
    }
}