<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'user_id',
        'line_user_id',
        'platform',
        'message_type',
        'message_content',
        'message_timestamp',
        'is_from_customer',
        'reply_content',
        'replied_at',
        'replied_by',
        'status',
        'metadata',
        'version',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'message_timestamp' => 'datetime',
        'replied_at' => 'datetime',
        'is_from_customer' => 'boolean',
        'metadata' => 'array',
        'version' => 'integer',
    ];
    
    /**
     * Boot the model and register event listeners.
     */
    protected static function booted()
    {
        // 當創建新訊息時
        static::creating(function ($conversation) {
            $versionService = app(\App\Services\ChatVersionService::class);
            $newVersion = $versionService->incrementVersion();
            $conversation->version = $newVersion;
        });
        
        // 當更新訊息時
        static::updating(function ($conversation) {
            // 如果 version 字段沒有被明確設置，則自動增加版本號
            if (!$conversation->isDirty('version')) {
                $versionService = app(\App\Services\ChatVersionService::class);
                $newVersion = $versionService->incrementVersion();
                $conversation->version = $newVersion;
            }
        });
    }

    /**
     * Get the customer this conversation belongs to
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user this conversation belongs to
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who replied to this message
     */
    public function replier()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    /**
     * Scope to get unread messages
     */
    public function scopeUnread($query)
    {
        return $query->where('status', 'unread');
    }

    /**
     * Scope to get messages from customers
     */
    public function scopeFromCustomers($query)
    {
        return $query->where('is_from_customer', true);
    }
}