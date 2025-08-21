<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ChatQueryCacheService
{
    private $cachePrefix = 'chat_query_';
    private $defaultTTL = 60; // 預設緩存 60 秒
    
    /**
     * 獲取對話列表（帶緩存）
     */
    public function getConversationList($userId, $forceRefresh = false)
    {
        $cacheKey = $this->cachePrefix . 'conversations_' . $userId;
        
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }
        
        return Cache::remember($cacheKey, $this->defaultTTL, function () use ($userId) {
            return $this->queryConversationList($userId);
        });
    }
    
    /**
     * 獲取未讀計數（帶緩存）
     */
    public function getUnreadCounts($lineUserIds, $forceRefresh = false)
    {
        $results = [];
        $uncachedIds = [];
        
        // 先從緩存獲取
        foreach ($lineUserIds as $lineUserId) {
            $cacheKey = $this->cachePrefix . 'unread_' . $lineUserId;
            
            if (!$forceRefresh && Cache::has($cacheKey)) {
                $results[$lineUserId] = Cache::get($cacheKey);
            } else {
                $uncachedIds[] = $lineUserId;
            }
        }
        
        // 批量查詢未緩存的數據
        if (!empty($uncachedIds)) {
            $counts = $this->queryUnreadCounts($uncachedIds);
            foreach ($counts as $lineUserId => $count) {
                $cacheKey = $this->cachePrefix . 'unread_' . $lineUserId;
                Cache::put($cacheKey, $count, $this->defaultTTL);
                $results[$lineUserId] = $count;
            }
        }
        
        return $results;
    }
    
    /**
     * 實際查詢對話列表（優化版）
     */
    private function queryConversationList($userId)
    {
        // 構建基礎查詢，包含客戶資訊
        $query = DB::table('chat_conversations as cc')
            ->leftJoin('customers as c', 'cc.line_user_id', '=', 'c.line_user_id')
            ->select([
                'cc.line_user_id',
                'cc.customer_id',
                DB::raw('MAX(cc.message_timestamp) as last_message_time'),
                DB::raw('MAX(cc.version) as max_version'),
                DB::raw('COUNT(CASE WHEN cc.status = "unread" AND cc.is_from_customer = 1 THEN 1 END) as unread_count'),
                DB::raw('MAX(CASE WHEN cc.is_from_customer = 1 THEN cc.message_content END) as last_customer_message'),
                DB::raw('MAX(CASE WHEN cc.is_from_customer = 0 THEN cc.message_content END) as last_system_message'),
                // 客戶資訊
                DB::raw('MAX(c.name) as customer_name'),
                DB::raw('MAX(c.phone) as customer_phone'),
                DB::raw('MAX(c.region) as customer_region'),
                DB::raw('MAX(c.website_source) as customer_source'),
                DB::raw('MAX(c.status) as customer_status')
            ])
            ->whereNotNull('cc.line_user_id')
            ->whereNotNull('cc.customer_id')
            ->groupBy('cc.line_user_id', 'cc.customer_id');
        
        // 優化權限過濾
        if ($userId) {
            $query->where('c.assigned_to', $userId);
        }
        
        // 使用索引排序
        $query->orderBy('last_message_time', 'desc')
            ->limit(100); // 限制結果數量
        
        // 執行查詢並格式化結果
        $results = $query->get();
        
        // 將客戶資訊包裝成 customer 對象
        return $results->map(function ($row) {
            $row->customer = (object) [
                'name' => $row->customer_name,
                'phone' => $row->customer_phone,
                'region' => $row->customer_region,
                'source' => $row->customer_source,
                'status' => $row->customer_status
            ];
            
            // 移除重複的欄位
            unset($row->customer_name, $row->customer_phone, $row->customer_region, $row->customer_source, $row->customer_status);
            
            return $row;
        });
    }
    
    /**
     * 批量查詢未讀計數（優化版）
     */
    private function queryUnreadCounts($lineUserIds)
    {
        // 使用覆蓋索引進行計數
        $counts = DB::table('chat_conversations')
            ->select('line_user_id', DB::raw('COUNT(*) as unread_count'))
            ->whereIn('line_user_id', $lineUserIds)
            ->where('status', 'unread')
            ->where('is_from_customer', 1)
            ->groupBy('line_user_id')
            ->pluck('unread_count', 'line_user_id')
            ->toArray();
        
        // 補充沒有未讀的用戶
        foreach ($lineUserIds as $id) {
            if (!isset($counts[$id])) {
                $counts[$id] = 0;
            }
        }
        
        return $counts;
    }
    
    /**
     * 獲取優化的消息列表
     */
    public function getOptimizedMessages($lineUserId, $limit = 50, $offset = 0)
    {
        // 使用查詢構建器而不是 Eloquent，減少內存使用
        return DB::table('chat_conversations')
            ->select([
                'id',
                'line_user_id',
                'message_content',
                'message_timestamp',
                'is_from_customer',
                'status',
                'version',
                'customer_id',
                'message_type',
                'metadata'
            ])
            ->where('line_user_id', $lineUserId)
            ->orderBy('message_timestamp', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function ($message) {
                // 只在需要時轉換數據格式
                $message->message_timestamp = Carbon::parse($message->message_timestamp);
                $message->metadata = is_string($message->metadata) ? json_decode($message->metadata, true) : $message->metadata;
                return $message;
            });
    }
    
    /**
     * 清除特定用戶的緩存
     */
    public function clearUserCache($userId)
    {
        try {
            // 如果使用 Redis
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $pattern = $this->cachePrefix . '*_' . $userId;
                $redis = Cache::getRedis();
                $keys = $redis->keys($pattern);
                
                foreach ($keys as $key) {
                    Cache::forget(str_replace($redis->getOptions()->prefix->getPrefix(), '', $key));
                }
            } else {
                // 回退到手動清除已知的緩存鍵
                Cache::forget($this->cachePrefix . 'conversations_' . $userId);
            }
        } catch (\Exception $e) {
            // 如果緩存清除失敗，記錄但不拋出異常
            \Log::warning('Failed to clear user cache', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 清除特定對話的緩存
     */
    public function clearConversationCache($lineUserId)
    {
        Cache::forget($this->cachePrefix . 'unread_' . $lineUserId);
        // 觸發相關用戶的列表緩存清除
        $this->clearRelatedListCache($lineUserId);
    }
    
    /**
     * 清除相關列表緩存
     */
    private function clearRelatedListCache($lineUserId)
    {
        try {
            // 找出所有相關用戶
            $relatedUsers = DB::table('customers')
                ->where('line_user_id', $lineUserId)
                ->pluck('assigned_to')
                ->unique()
                ->filter();
            
            foreach ($relatedUsers as $userId) {
                Cache::forget($this->cachePrefix . 'conversations_' . $userId);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to clear related list cache', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 獲取緩存統計信息
     */
    public function getCacheStats()
    {
        try {
            $stats = [
                'total_keys' => 0,
                'conversation_caches' => 0,
                'unread_caches' => 0,
                'cache_size' => 0
            ];
            
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $redis = Cache::getRedis();
                $keys = $redis->keys($this->cachePrefix . '*');
                $stats['total_keys'] = count($keys);
                
                foreach ($keys as $key) {
                    $keyName = str_replace($redis->getOptions()->prefix->getPrefix(), '', $key);
                    if (strpos($keyName, 'conversations_') !== false) {
                        $stats['conversation_caches']++;
                    } elseif (strpos($keyName, 'unread_') !== false) {
                        $stats['unread_caches']++;
                    }
                }
            }
            
            return $stats;
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to get cache stats: ' . $e->getMessage()
            ];
        }
    }
}