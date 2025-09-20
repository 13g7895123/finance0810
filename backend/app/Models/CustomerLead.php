<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerLead extends Model
{
    use HasFactory;

    // Case status constants
    public const CASE_STATUS_UNASSIGNED = 'unassigned';
    public const CASE_STATUS_VALID_CUSTOMER = 'valid_customer';
    public const CASE_STATUS_INVALID_CUSTOMER = 'invalid_customer';
    public const CASE_STATUS_CUSTOMER_SERVICE = 'customer_service';
    public const CASE_STATUS_BLACKLIST = 'blacklist';
    public const CASE_STATUS_APPROVED_DISBURSED = 'approved_disbursed';
    public const CASE_STATUS_CONDITIONAL = 'conditional';
    public const CASE_STATUS_DECLINED = 'declined';
    public const CASE_STATUS_FOLLOW_UP = 'follow_up';

    protected $fillable = [
        'customer_id',
        'status',
        'case_status',
        'assigned_to',
        'channel', // wp_form, phone_call, line, email
        'source', // page url / website
        'name',
        'phone',
        'email',
        'line_id',
        'ip_address',
        'user_agent',
        'payload',
        'is_suspected_blacklist',
        'suspected_reason',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_suspected_blacklist' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Point 40: Get the website this lead came from
     */
    public function website()
    {
        return $this->belongsTo(Website::class, 'source', 'domain');
    }

    /**
     * Get case status options with Chinese labels
     */
    public static function getCaseStatusOptions(): array
    {
        return [
            self::CASE_STATUS_UNASSIGNED => '未指派',
            self::CASE_STATUS_VALID_CUSTOMER => '有效客',
            self::CASE_STATUS_INVALID_CUSTOMER => '無效客',
            self::CASE_STATUS_CUSTOMER_SERVICE => '客服',
            self::CASE_STATUS_BLACKLIST => '黑名單',
            self::CASE_STATUS_APPROVED_DISBURSED => '核准撥款',
            self::CASE_STATUS_CONDITIONAL => '附條件',
            self::CASE_STATUS_DECLINED => '婉拒',
            self::CASE_STATUS_FOLLOW_UP => '追蹤管理',
        ];
    }

    /**
     * Get case status label
     */
    public function getCaseStatusLabelAttribute(): ?string
    {
        $options = self::getCaseStatusOptions();
        return $options[$this->case_status] ?? null;
    }
}
