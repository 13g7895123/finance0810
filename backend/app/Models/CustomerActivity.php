<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerActivity extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'user_id',
        'activity_type',
        'description',
        'old_data',
        'new_data',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    /**
     * Get the customer this activity belongs to
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who performed this activity
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Activity types
     */
    const TYPE_CREATED = 'created';
    const TYPE_UPDATED = 'updated';
    const TYPE_STATUS_CHANGED = 'status_changed';
    const TYPE_ASSIGNED = 'assigned';
    const TYPE_CONTACTED = 'contacted';
    const TYPE_CASE_SUBMITTED = 'case_submitted';
    const TYPE_CASE_APPROVED = 'case_approved';
    const TYPE_CASE_REJECTED = 'case_rejected';
    const TYPE_DISBURSED = 'disbursed';
}