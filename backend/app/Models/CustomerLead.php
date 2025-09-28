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

        // Point 1: 個人資料 (Personal Information)
        'birth_date',
        'id_number',
        'education_level',

        // Point 1: 聯絡資訊 (Contact Information)
        'contact_time',
        'registered_address',
        'home_phone',
        'mailing_same_as_registered',
        'mailing_address',
        'mailing_phone',
        'residence_duration',
        'residence_owner',
        'telecom_provider',

        // Point 1: 公司資料 (Company Information)
        'company_name',
        'company_phone',
        'company_address',
        'job_title',
        'monthly_income',
        'labor_insurance_transfer',
        'current_job_duration',

        // Point 1: 緊急聯絡人 (Emergency Contacts)
        'emergency_contact_1_name',
        'emergency_contact_1_relationship',
        'emergency_contact_1_phone',
        'emergency_contact_1_available_time',
        'emergency_contact_1_confidential',
        'emergency_contact_2_name',
        'emergency_contact_2_relationship',
        'emergency_contact_2_phone',
        'emergency_contact_2_available_time',
        'emergency_contact_2_confidential',
        'referrer',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_suspected_blacklist' => 'boolean',

        // Point 1: 類型轉換
        'birth_date' => 'date',
        'mailing_same_as_registered' => 'boolean',
        'monthly_income' => 'decimal:2',
        'labor_insurance_transfer' => 'boolean',
        'emergency_contact_1_confidential' => 'boolean',
        'emergency_contact_2_confidential' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}