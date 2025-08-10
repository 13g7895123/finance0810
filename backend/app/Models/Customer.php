<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'region',
        'website_source',
        'channel',
        'status',
        'tracking_status',
        'tracking_date',
        'notes',
        'assigned_to',
        'created_by',
        'line_user_id',
        'line_display_name',
        'source_data',
        'case_status',
        'approved_amount',
        'disbursed_amount',
        'disbursement_status',
        'next_contact_date',
        'priority_level',
        'invalid_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tracking_date' => 'datetime',
        'next_contact_date' => 'datetime',
        'source_data' => 'array',
        'approved_amount' => 'decimal:2',
        'disbursed_amount' => 'decimal:2',
    ];

    /**
     * Customer status constants
     */
    const STATUS_NEW = 'new';
    const STATUS_CONTACTED = 'contacted';
    const STATUS_INTERESTED = 'interested';
    const STATUS_NOT_INTERESTED = 'not_interested';
    const STATUS_INVALID = 'invalid';
    const STATUS_CONVERTED = 'converted';

    /**
     * Tracking status constants
     */
    const TRACKING_PENDING = 'pending';
    const TRACKING_SCHEDULED = 'scheduled';
    const TRACKING_CONTACTED = 'contacted';
    const TRACKING_FOLLOW_UP = 'follow_up';
    const TRACKING_COMPLETED = 'completed';

    /**
     * Case status constants
     */
    const CASE_NONE = null;
    const CASE_SUBMITTED = 'submitted';
    const CASE_APPROVED = 'approved';
    const CASE_REJECTED = 'rejected';
    const CASE_DISBURSED = 'disbursed';

    /**
     * Get the user assigned to this customer
     */
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who created this customer
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get customer's cases
     */
    public function cases()
    {
        return $this->hasMany(CustomerCase::class);
    }

    /**
     * Get customer's bank negotiation records
     */
    public function bankRecords()
    {
        return $this->hasMany(BankRecord::class);
    }

    /**
     * Get customer's chat conversations
     */
    public function chatConversations()
    {
        return $this->hasMany(ChatConversation::class);
    }

    /**
     * Get customer's activity history
     */
    public function activities()
    {
        return $this->hasMany(CustomerActivity::class)->orderBy('created_at', 'desc');
    }

    /**
     * Scope to filter by assigned user
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by region
     */
    public function scopeByRegion($query, $region)
    {
        return $query->where('region', $region);
    }

    /**
     * Scope to filter customers needing follow-up
     */
    public function scopeNeedingFollowUp($query)
    {
        return $query->where('next_contact_date', '<=', now())
            ->whereNotIn('status', [self::STATUS_CONVERTED, self::STATUS_INVALID]);
    }

    /**
     * Scope to filter customers by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ]);
    }

    /**
     * Get status options with Chinese labels
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_NEW => '新客戶',
            self::STATUS_CONTACTED => '已聯絡',
            self::STATUS_INTERESTED => '有興趣',
            self::STATUS_NOT_INTERESTED => '無興趣',
            self::STATUS_INVALID => '無效客戶',
            self::STATUS_CONVERTED => '已成交',
        ];
    }

    /**
     * Get tracking status options with Chinese labels
     */
    public static function getTrackingStatusOptions(): array
    {
        return [
            self::TRACKING_PENDING => '待處理',
            self::TRACKING_SCHEDULED => '已排程',
            self::TRACKING_CONTACTED => '已聯絡',
            self::TRACKING_FOLLOW_UP => '需追蹤',
            self::TRACKING_COMPLETED => '已完成',
        ];
    }

    /**
     * Get case status options with Chinese labels
     */
    public static function getCaseStatusOptions(): array
    {
        return [
            self::CASE_NONE => '無案件',
            self::CASE_SUBMITTED => '已送件',
            self::CASE_APPROVED => '已核准',
            self::CASE_REJECTED => '已婉拒',
            self::CASE_DISBURSED => '已撥款',
        ];
    }

    /**
     * Check if customer needs follow-up
     */
    public function needsFollowUp(): bool
    {
        return $this->next_contact_date && 
               $this->next_contact_date->isPast() &&
               !in_array($this->status, [self::STATUS_CONVERTED, self::STATUS_INVALID]);
    }

    /**
     * Get customer's conversion rate (for staff performance)
     */
    public function getConversionRate(): float
    {
        $totalCustomers = static::where('assigned_to', $this->assigned_to)->count();
        $convertedCustomers = static::where('assigned_to', $this->assigned_to)
            ->where('status', self::STATUS_CONVERTED)
            ->count();

        return $totalCustomers > 0 ? ($convertedCustomers / $totalCustomers) * 100 : 0;
    }
}