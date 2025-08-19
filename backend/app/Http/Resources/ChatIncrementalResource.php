<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatIncrementalResource extends JsonResource
{
    /**
     * 轉換資源為陣列
     */
    public function toArray($request)
    {
        return [
            'version' => $this->resource['version'],
            'timestamp' => now()->toISOString(),
            'changes' => [
                'added' => $this->formatItems($this->resource['added'] ?? []),
                'updated' => $this->formatItems($this->resource['updated'] ?? []),
                'removed' => $this->resource['removed'] ?? [],
            ],
            'metadata' => [
                'total_changes' => $this->getTotalChanges(),
                'checksum' => $this->generateChecksum(),
                'partial' => $this->resource['partial'] ?? false,
            ]
        ];
    }
    
    /**
     * 格式化項目列表
     */
    private function formatItems($items)
    {
        return collect($items)->map(function ($item) {
            // 處理 Eloquent 模型和陣列兩種情況
            if (is_object($item)) {
                return [
                    'id' => $item->id,
                    'line_user_id' => $item->line_user_id,
                    'content' => $item->message_content ?? null,
                    'timestamp' => $item->message_timestamp,
                    'is_from_customer' => $item->is_from_customer ?? true,
                    'status' => $item->status ?? 'unread',
                    'message_type' => $item->message_type ?? 'text',
                    'metadata' => $item->metadata ?? [],
                    'version' => $item->version ?? 0,
                    'customer_id' => $item->customer_id ?? null,
                    'unread_count' => $item->unread_count ?? 0,
                    'last_message_time' => $item->last_message_time ?? $item->message_timestamp ?? null,
                ];
            } else {
                // 處理陣列格式
                return [
                    'id' => $item['id'] ?? null,
                    'line_user_id' => $item['line_user_id'] ?? null,
                    'content' => $item['content'] ?? $item['message_content'] ?? null,
                    'timestamp' => $item['timestamp'] ?? $item['message_timestamp'] ?? null,
                    'is_from_customer' => $item['is_from_customer'] ?? true,
                    'status' => $item['status'] ?? 'unread',
                    'message_type' => $item['message_type'] ?? 'text',
                    'metadata' => $item['metadata'] ?? [],
                    'version' => $item['version'] ?? 0,
                    'customer_id' => $item['customer_id'] ?? null,
                    'unread_count' => $item['unread_count'] ?? 0,
                    'last_message_time' => $item['last_message_time'] ?? null,
                ];
            }
        })->toArray();
    }
    
    /**
     * 計算總變更數
     */
    private function getTotalChanges()
    {
        $added = count($this->resource['added'] ?? []);
        $updated = count($this->resource['updated'] ?? []);
        $removed = count($this->resource['removed'] ?? []);
        return $added + $updated + $removed;
    }
    
    /**
     * 生成數據校驗碼
     */
    private function generateChecksum()
    {
        $data = [
            'added' => $this->resource['added'] ?? [],
            'updated' => $this->resource['updated'] ?? [],
            'removed' => $this->resource['removed'] ?? [],
        ];
        
        // 確保一致的序列化
        $jsonString = json_encode($data, JSON_SORT_KEYS | JSON_UNESCAPED_UNICODE);
        return md5($jsonString);
    }
}