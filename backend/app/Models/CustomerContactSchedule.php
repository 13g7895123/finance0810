<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\CustomerActivity;

class CustomerContactSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'scheduled_date',
        'scheduled_time',
        'status',
        'contact_type',
        'notes',
        'priority',
        'actual_contact_at',
        'follow_up_date',
        'reminder_sent',
        'reminder_sent_at',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime:H:i:s',
        'actual_contact_at' => 'datetime',
        'follow_up_date' => 'date',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
    ];

    // 聯絡狀態常數
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_CONTACTED = 'contacted';
    const STATUS_RESCHEDULED = 'rescheduled';
    const STATUS_MISSED = 'missed';
    const STATUS_COMPLETED = 'completed';

    // 聯絡類型常數
    const CONTACT_PHONE = 'phone';
    const CONTACT_LINE = 'line';
    const CONTACT_EMAIL = 'email';
    const CONTACT_MEETING = 'meeting';
    const CONTACT_OTHER = 'other';

    // 優先級常數
    const PRIORITY_HIGH = 'high';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_LOW = 'low';

    /**
     * 關聯到客戶
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * 關聯到業務人員
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: 取得特定使用者的聯絡計畫
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: 取得特定日期的聯絡計畫
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('scheduled_date', $date);
    }

    /**
     * Scope: 取得特定日期範圍的聯絡計畫
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('scheduled_date', [$startDate, $endDate]);
    }

    /**
     * Scope: 取得需要提醒的聯絡計畫
     */
    public function scopeNeedingReminder($query)
    {
        return $query->where('reminder_sent', false)
            ->where('status', self::STATUS_SCHEDULED)
            ->whereDate('scheduled_date', '<=', now()->addDay()); // 明天之前的計畫
    }

    /**
     * Scope: 取得逾期未聯絡的計畫
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->whereDate('scheduled_date', '<', now());
    }

    /**
     * Scope: 取得今日的聯絡計畫
     */
    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_date', now()->toDateString());
    }

    /**
     * 取得狀態選項
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_SCHEDULED => '已排程',
            self::STATUS_CONTACTED => '已聯絡',
            self::STATUS_RESCHEDULED => '已改約',
            self::STATUS_MISSED => '未聯絡',
            self::STATUS_COMPLETED => '已完成',
        ];
    }

    /**
     * 取得聯絡類型選項
     */
    public static function getContactTypeOptions(): array
    {
        return [
            self::CONTACT_PHONE => '電話',
            self::CONTACT_LINE => 'LINE',
            self::CONTACT_EMAIL => 'Email',
            self::CONTACT_MEETING => '會面',
            self::CONTACT_OTHER => '其他',
        ];
    }

    /**
     * 取得優先級選項
     */
    public static function getPriorityOptions(): array
    {
        return [
            self::PRIORITY_HIGH => '高',
            self::PRIORITY_MEDIUM => '中',
            self::PRIORITY_LOW => '低',
        ];
    }

    /**
     * 檢查是否逾期
     */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && 
               $this->scheduled_date->isPast();
    }

    /**
     * 檢查是否需要提醒
     */
    public function needsReminder(): bool
    {
        return !$this->reminder_sent && 
               $this->status === self::STATUS_SCHEDULED && 
               $this->scheduled_date->lte(now()->addDay());
    }

    /**
     * 標記為已聯絡
     */
    public function markAsContacted($notes = null)
    {
        $this->update([
            'status' => self::STATUS_CONTACTED,
            'actual_contact_at' => now(),
            'notes' => $notes ?: $this->notes,
        ]);

        // 記錄客戶活動
        CustomerActivity::create([
            'customer_id' => $this->customer_id,
            'user_id' => $this->user_id,
            'activity_type' => CustomerActivity::TYPE_CONTACTED,
            'description' => "客戶聯絡完成 - {$this->contact_type}",
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * 改約到新日期
     */
    public function reschedule($newDate, $newTime = null, $notes = null)
    {
        $this->update([
            'status' => self::STATUS_RESCHEDULED,
            'notes' => $notes ?: $this->notes,
        ]);

        // 建立新的排程
        self::create([
            'customer_id' => $this->customer_id,
            'user_id' => $this->user_id,
            'scheduled_date' => $newDate,
            'scheduled_time' => $newTime,
            'status' => self::STATUS_SCHEDULED,
            'contact_type' => $this->contact_type,
            'priority' => $this->priority,
            'notes' => "改約自 " . $this->scheduled_date->format('Y-m-d'),
        ]);

        // 記錄客戶活動
        CustomerActivity::create([
            'customer_id' => $this->customer_id,
            'user_id' => $this->user_id,
            'activity_type' => CustomerActivity::TYPE_UPDATED,
            'description' => "聯絡時間改約至 {$newDate}",
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * 發送提醒
     */
    public function sendReminder()
    {
        $this->update([
            'reminder_sent' => true,
            'reminder_sent_at' => now(),
        ]);
    }
}