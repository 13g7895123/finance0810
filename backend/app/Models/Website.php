<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Point 40: Website Model for WordPress site management
 */
class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'domain',
        'url',
        'status',
        'type',
        'webhook_enabled',
        'webhook_url',
        'webhook_secret',
        'lead_count',
        'customer_count',
        'conversion_rate',
        'last_lead_at',
        'form_settings',
        'tracking_settings',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'webhook_enabled' => 'boolean',
        'lead_count' => 'integer',
        'customer_count' => 'integer',
        'conversion_rate' => 'decimal:2',
        'last_lead_at' => 'datetime',
        'form_settings' => 'array',
        'tracking_settings' => 'array',
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_MAINTENANCE = 'maintenance';

    // Type constants
    const TYPE_WORDPRESS = 'wordpress';
    const TYPE_OTHER = 'other';

    /**
     * Boot method to automatically set created_by and updated_by
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    /**
     * Get the user who created this website
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this website
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get customers from this website
     */
    public function customers()
    {
        return $this->hasMany(Customer::class, 'website_source', 'domain');
    }

    /**
     * Get customer leads from this website
     */
    public function customerLeads()
    {
        return $this->hasMany(CustomerLead::class, 'source', 'domain');
    }

    /**
     * Scope: Only active websites
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: Only WordPress websites
     */
    public function scopeWordPress($query)
    {
        return $query->where('type', self::TYPE_WORDPRESS);
    }

    /**
     * Scope: Websites with webhook enabled
     */
    public function scopeWebhookEnabled($query)
    {
        return $query->where('webhook_enabled', true);
    }

    /**
     * Update statistics from related records
     */
    public function updateStatistics()
    {
        $leadCount = $this->customerLeads()->count();
        $customerCount = $this->customers()->count();
        $conversionRate = $leadCount > 0 ? ($customerCount / $leadCount) * 100 : 0;
        $lastLead = $this->customerLeads()->latest('created_at')->first();

        $this->update([
            'lead_count' => $leadCount,
            'customer_count' => $customerCount,
            'conversion_rate' => $conversionRate,
            'last_lead_at' => $lastLead ? $lastLead->created_at : null,
        ]);

        return $this;
    }

    /**
     * Get website statistics
     */
    public function getStatistics()
    {
        return [
            'total_leads' => $this->lead_count,
            'total_customers' => $this->customer_count,
            'conversion_rate' => $this->conversion_rate,
            'last_lead_at' => $this->last_lead_at,
            'daily_leads' => $this->customerLeads()
                ->whereDate('created_at', today())
                ->count(),
            'monthly_leads' => $this->customerLeads()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];
    }

    /**
     * Check if website is healthy (recent activity)
     */
    public function isHealthy()
    {
        return $this->status === self::STATUS_ACTIVE && 
               $this->last_lead_at && 
               $this->last_lead_at->gt(now()->subWeek());
    }

    /**
     * Get display name with status indicator
     */
    public function getDisplayNameAttribute()
    {
        $statusIcon = match($this->status) {
            self::STATUS_ACTIVE => '🟢',
            self::STATUS_INACTIVE => '🔴',
            self::STATUS_MAINTENANCE => '🟡',
            default => '⚪',
        };

        return $statusIcon . ' ' . $this->name;
    }

    /**
     * Extract domain from URL
     */
    public static function extractDomain($url)
    {
        $parsedUrl = parse_url($url);
        return $parsedUrl['host'] ?? $url;
    }

    /**
     * Find or create website by domain
     */
    public static function findOrCreateByDomain($domain, $attributes = [])
    {
        return self::firstOrCreate(
            ['domain' => $domain],
            array_merge([
                'name' => $domain,
                'url' => 'https://' . $domain,
                'status' => self::STATUS_ACTIVE,
                'type' => self::TYPE_WORDPRESS,
            ], $attributes)
        );
    }
}