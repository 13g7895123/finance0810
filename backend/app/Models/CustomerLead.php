<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'status',
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
}
