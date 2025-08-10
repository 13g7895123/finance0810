<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankRecord extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'case_id',
        'bank_name',
        'contact_person',
        'contact_phone',
        'contact_email',
        'communication_type',
        'communication_date',
        'content',
        'result',
        'next_action',
        'next_contact_date',
        'status',
        'created_by',
        'attachments',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'communication_date' => 'datetime',
        'next_contact_date' => 'datetime',
        'attachments' => 'array',
    ];

    /**
     * Get the customer this record belongs to
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the case this record belongs to
     */
    public function case()
    {
        return $this->belongsTo(CustomerCase::class, 'case_id');
    }

    /**
     * Get the user who created this record
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}