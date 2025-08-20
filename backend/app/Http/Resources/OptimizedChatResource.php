<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OptimizedChatResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'i' => $this->id,                    // id -> i (縮短字段名)
            'u' => $this->line_user_id,          // line_user_id -> u
            'c' => $this->message_content,       // content -> c
            't' => $this->message_timestamp,     // timestamp -> t
            'f' => $this->is_from_customer,      // is_from_customer -> f
            's' => $this->status,                // status -> s
            'v' => $this->version,               // version -> v
            'n' => $this->customer_name,         // customer_name -> n (可選)
            'p' => $this->customer_phone,        // customer_phone -> p (可選)
            'r' => $this->unread_count ?? 0     // unread_count -> r
        ];
    }
    
    /**
     * 創建優化的對話列表資源
     */
    public static function conversations($conversations)
    {
        return $conversations->map(function ($conversation) {
            return [
                'i' => $conversation->id,
                'u' => $conversation->line_user_id,
                'n' => $conversation->customer_name,
                'p' => $conversation->customer_phone,
                'lm' => $conversation->last_message,           // last_message -> lm
                'lt' => $conversation->last_message_time,      // last_message_time -> lt
                'r' => $conversation->unread_count ?? 0,       // unread_count -> r
                's' => $conversation->status,                  // status -> s
                'v' => $conversation->version,                 // version -> v
                'vt' => $conversation->version_updated_at      // version_updated_at -> vt
            ];
        });
    }
    
    /**
     * 創建優化的消息列表資源
     */
    public static function messages($messages)
    {
        return $messages->map(function ($message) {
            return [
                'i' => $message->id,
                'u' => $message->line_user_id,
                'c' => $message->message_content,
                't' => $message->message_timestamp,
                'f' => $message->is_from_customer,
                's' => $message->status,
                'v' => $message->version,
                'mt' => $message->message_type ?? 'text',      // message_type -> mt
                'at' => $message->attachments ?? null         // attachments -> at
            ];
        });
    }
}