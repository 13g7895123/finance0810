<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Point 36: LINE User Model for centralized LINE user management
 * Supports both LINE Messaging API and LINE Login API integration
 */
class LineUser extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Core identifiers
        'line_user_id',
        'display_name',
        'display_name_original',
        
        // Point 39: Business-editable name system
        'business_display_name',
        'business_name_updated_by',
        'business_name_updated_at',
        
        // Messaging API data
        'picture_url',
        'status_message',
        'language',
        
        // Login API data
        'email',
        'email_verified',
        'phone',
        'phone_verified',
        
        // Profile+ extended data
        'real_name',
        'gender',
        'birth_date',
        'address',
        
        // API access tracking
        'has_login_access',
        'has_profile_plus_access',
        'granted_scopes',
        'login_last_used_at',
        
        // Sync metadata
        'messaging_api_synced_at',
        'login_api_synced_at',
        'profile_sync_failed',
        'profile_sync_error',
        
        // Extended data
        'inferred_phone',
        'inferred_email',
        'interaction_data',
        
        // Relationship tracking
        'has_customer_record',
        'first_interaction_at',
        'last_interaction_at',
        
        // Status
        'status',
        'is_friend',
        'friend_added_at',
        'friend_removed_at',
        
        // Statistics
        'message_count',
        'conversation_count',
        'last_message_at',
    ];

    protected $casts = [
        'email_verified' => 'boolean',
        'phone_verified' => 'boolean',
        'birth_date' => 'date',
        'has_login_access' => 'boolean',
        'has_profile_plus_access' => 'boolean',
        'granted_scopes' => 'array',
        'login_last_used_at' => 'datetime',
        'messaging_api_synced_at' => 'datetime',
        'login_api_synced_at' => 'datetime',
        'profile_sync_failed' => 'boolean',
        'interaction_data' => 'array',
        'has_customer_record' => 'boolean',
        'first_interaction_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'is_friend' => 'boolean',
        'friend_added_at' => 'datetime',
        'friend_removed_at' => 'datetime',
        'last_message_at' => 'datetime',
        // Point 39: Business name tracking
        'business_name_updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_BLOCKED = 'blocked';
    const STATUS_DELETED = 'deleted';
    const STATUS_UNKNOWN = 'unknown';

    /**
     * Get associated customer record
     */
    public function customer()
    {
        return $this->hasOne(Customer::class, 'line_user_id', 'line_user_id');
    }

    /**
     * Get chat conversations
     */
    public function chatConversations()
    {
        return $this->hasMany(ChatConversation::class, 'line_user_id', 'line_user_id');
    }

    /**
     * Get customer identifiers
     */
    public function identifiers()
    {
        return $this->hasMany(CustomerIdentifier::class, 'value', 'line_user_id')
                    ->where('type', 'line');
    }

    /**
     * Get customer leads
     */
    public function leads()
    {
        return $this->hasMany(CustomerLead::class, 'line_id', 'line_user_id');
    }
    
    /**
     * Point 39: Get user who last updated business name
     */
    public function businessNameUpdatedBy()
    {
        return $this->belongsTo(User::class, 'business_name_updated_by');
    }

    /**
     * Find or create LINE user by LINE User ID
     */
    public static function findOrCreateByLineUserId($lineUserId, $initialData = [])
    {
        $lineUser = self::where('line_user_id', $lineUserId)->first();
        
        if (!$lineUser) {
            $lineUser = self::create(array_merge([
                'line_user_id' => $lineUserId,
                'first_interaction_at' => now(),
                'status' => self::STATUS_ACTIVE,
                'is_friend' => true,
                'friend_added_at' => now(),
            ], $initialData));
        }
        
        // Update last interaction
        $lineUser->update([
            'last_interaction_at' => now(),
        ]);
        
        return $lineUser;
    }

    /**
     * Sync profile from LINE Messaging API
     */
    public function syncMessagingApiProfile($profileData)
    {
        $updates = [
            'messaging_api_synced_at' => now(),
            'profile_sync_failed' => false,
            'profile_sync_error' => null,
        ];

        if (isset($profileData['displayName'])) {
            $updates['display_name'] = $profileData['displayName'];
            if (!$this->display_name_original) {
                $updates['display_name_original'] = $profileData['displayName'];
            }
        }

        if (isset($profileData['pictureUrl'])) {
            $updates['picture_url'] = $profileData['pictureUrl'];
        }

        if (isset($profileData['statusMessage'])) {
            $updates['status_message'] = $profileData['statusMessage'];
        }

        if (isset($profileData['language'])) {
            $updates['language'] = $profileData['language'];
        }

        $this->update($updates);
        return $this;
    }

    /**
     * Sync profile from LINE Login API
     */
    public function syncLoginApiProfile($profileData, $grantedScopes = [])
    {
        $updates = [
            'login_api_synced_at' => now(),
            'has_login_access' => true,
            'granted_scopes' => $grantedScopes,
            'login_last_used_at' => now(),
        ];

        // Basic profile data
        if (isset($profileData['displayName'])) {
            $updates['display_name'] = $profileData['displayName'];
        }

        if (isset($profileData['pictureUrl'])) {
            $updates['picture_url'] = $profileData['pictureUrl'];
        }

        // Email from Login API (requires email scope)
        if (isset($profileData['email'])) {
            $updates['email'] = $profileData['email'];
            $updates['email_verified'] = $profileData['email_verified'] ?? false;
        }

        // Profile+ data (corporate features)
        if (isset($profileData['name'])) {
            $updates['real_name'] = $profileData['name'];
        }

        if (isset($profileData['gender'])) {
            $updates['gender'] = $profileData['gender'];
        }

        if (isset($profileData['birthdate'])) {
            $updates['birth_date'] = $profileData['birthdate'];
        }

        if (isset($profileData['phone_number'])) {
            $updates['phone'] = $profileData['phone_number'];
            $updates['phone_verified'] = $profileData['phone_number_verified'] ?? false;
            $updates['has_profile_plus_access'] = true;
        }

        if (isset($profileData['address'])) {
            $updates['address'] = is_array($profileData['address']) 
                ? json_encode($profileData['address']) 
                : $profileData['address'];
        }

        $this->update($updates);
        return $this;
    }

    /**
     * Mark sync as failed
     */
    public function markSyncFailed($error, $apiType = 'messaging')
    {
        $updates = [
            'profile_sync_failed' => true,
            'profile_sync_error' => $error,
        ];

        if ($apiType === 'messaging') {
            $updates['messaging_api_synced_at'] = now();
        } else {
            $updates['login_api_synced_at'] = now();
        }

        $this->update($updates);
        return $this;
    }

    /**
     * Update friend status
     */
    public function updateFriendStatus($isFriend)
    {
        $updates = ['is_friend' => $isFriend];
        
        if ($isFriend && !$this->is_friend) {
            // Re-added as friend
            $updates['friend_added_at'] = now();
            $updates['friend_removed_at'] = null;
        } elseif (!$isFriend && $this->is_friend) {
            // Removed as friend
            $updates['friend_removed_at'] = now();
        }

        $this->update($updates);
        return $this;
    }

    /**
     * Increment message count
     */
    public function incrementMessageCount()
    {
        $this->increment('message_count');
        $this->update(['last_message_at' => now()]);
        return $this;
    }

    /**
     * Increment conversation count
     */
    public function incrementConversationCount()
    {
        $this->increment('conversation_count');
        return $this;
    }

    /**
     * Get best available email
     */
    public function getBestEmail()
    {
        return $this->email ?: $this->inferred_email;
    }

    /**
     * Get best available phone
     */
    public function getBestPhone()
    {
        return $this->phone ?: $this->inferred_phone;
    }

    /**
     * Point 39: Get display name for business use (prioritizes business-edited name)
     */
    public function getDisplayName()
    {
        return $this->business_display_name ?: $this->display_name;
    }
    
    /**
     * Point 39: Get original LINE API display name (read-only for business)
     */
    public function getApiDisplayName()
    {
        return $this->display_name;
    }
    
    /**
     * Point 39: Update business display name with tracking
     */
    public function updateBusinessDisplayName($newName, $userId = null)
    {
        $this->update([
            'business_display_name' => $newName,
            'business_name_updated_by' => $userId,
            'business_name_updated_at' => now(),
        ]);
        
        return $this;
    }
    
    /**
     * Point 39: Check if business name has been customized
     */
    public function hasCustomBusinessName()
    {
        return !empty($this->business_display_name);
    }
    
    /**
     * Get best available name (backwards compatibility)
     */
    public function getBestName()
    {
        return $this->real_name ?: $this->getDisplayName();
    }

    /**
     * Check if profile needs sync (older than 24 hours)
     */
    public function needsProfileSync($apiType = 'messaging')
    {
        $lastSynced = $apiType === 'messaging' 
            ? $this->messaging_api_synced_at 
            : $this->login_api_synced_at;
            
        return !$lastSynced || $lastSynced->lt(now()->subDay());
    }

    /**
     * Get profile completeness score (0-100)
     */
    public function getProfileCompletenessScore()
    {
        $score = 0;
        $maxScore = 10;
        
        // Basic data (4 points)
        if ($this->display_name) $score++;
        if ($this->picture_url) $score++;
        if ($this->language) $score++;
        if ($this->status_message) $score++;
        
        // Contact data (3 points)
        if ($this->getBestEmail()) $score += 1.5;
        if ($this->getBestPhone()) $score += 1.5;
        
        // Extended data (3 points)
        if ($this->real_name) $score++;
        if ($this->gender) $score++;
        if ($this->birth_date) $score++;
        
        return round(($score / $maxScore) * 100);
    }

    /**
     * Link to customer record
     */
    public function linkToCustomer()
    {
        if (!$this->has_customer_record && $this->customer) {
            $this->update(['has_customer_record' => true]);
        }
        return $this;
    }

    /**
     * Scope: Active users only
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: Friend users only
     */
    public function scopeFriends($query)
    {
        return $query->where('is_friend', true);
    }

    /**
     * Scope: Users with email access
     */
    public function scopeWithEmail($query)
    {
        return $query->whereNotNull('email')
                    ->orWhereNotNull('inferred_email');
    }

    /**
     * Scope: Users with phone access
     */
    public function scopeWithPhone($query)
    {
        return $query->whereNotNull('phone')
                    ->orWhereNotNull('inferred_phone');
    }

    /**
     * Scope: Users needing profile sync
     */
    public function scopeNeedsSync($query, $apiType = 'messaging')
    {
        $column = $apiType === 'messaging' ? 'messaging_api_synced_at' : 'login_api_synced_at';
        
        return $query->where(function ($q) use ($column) {
            $q->whereNull($column)
              ->orWhere($column, '<', now()->subDay());
        });
    }
}