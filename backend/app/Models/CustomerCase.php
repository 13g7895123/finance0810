<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCase extends Model
{
    use HasFactory, SoftDeletes;

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

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'lead_id',
        'case_number',
        'case_status',
        'loan_amount',
        'loan_type',
        'loan_term',
        'interest_rate',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'disbursed_at',
        'approved_amount',
        'disbursed_amount',
        'rejection_reason',
        'notes',
        'documents',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'disbursed_at' => 'datetime',
        'loan_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'disbursed_amount' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'documents' => 'array',
    ];

    /**
     * Get the customer that owns this case
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the lead this case originated from (optional)
     */
    public function lead()
    {
        return $this->belongsTo(\App\Models\CustomerLead::class, 'lead_id');
    }

    /**
     * Get the user who created this case
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generate unique case number
     */
    public static function generateCaseNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $sequence = static::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count() + 1;

        return sprintf('%s%s%04d', $year, $month, $sequence);
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