<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
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
}
