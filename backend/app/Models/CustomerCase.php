<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCase extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'case_number',
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
}