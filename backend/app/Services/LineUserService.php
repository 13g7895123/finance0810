<?php

namespace App\Services;

use App\Models\LineUser;
use App\Models\Customer;
use App\Models\CustomerIdentifier;
use App\Models\ChatConversation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Point 36: LINE User Service for centralized LINE user management
 * Handles LINE API integration, data synchronization, and backward compatibility
 */
class LineUserService
{
    /**
     * Find or create LINE user and sync with existing data
     */
    public function findOrCreateLineUser($lineUserId, $profileData = [], $source = 'messaging_api')
    {
        Log::info('Point 36 - LineUserService: Finding or creating LINE user', [
            'line_user_id' => $lineUserId,
            'source' => $source,
            'has_profile_data' => !empty($profileData)
        ]);

        DB::beginTransaction();
        
        try {
            // Find or create LINE user
            $lineUser = LineUser::findOrCreateByLineUserId($lineUserId);
            
            // Sync profile data if provided
            if (!empty($profileData)) {
                if ($source === 'messaging_api') {
                    $lineUser->syncMessagingApiProfile($profileData);
                } elseif ($source === 'login_api') {
                    $lineUser->syncLoginApiProfile($profileData);
                }
            }
            
            // Check and link to existing customer records
            $this->linkToExistingCustomer($lineUser);
            
            // Sync statistics from existing chat conversations
            $this->syncStatisticsFromConversations($lineUser);
            
            DB::commit();
            
            Log::info('Point 36 - LineUserService: LINE user processed successfully', [
                'line_user_id' => $lineUserId,
                'line_user_table_id' => $lineUser->id,
                'has_customer_record' => $lineUser->has_customer_record,
                'profile_completeness' => $lineUser->getProfileCompletenessScore()
            ]);
            
            return $lineUser;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Point 36 - LineUserService: Failed to process LINE user', [
                'line_user_id' => $lineUserId,
                'source' => $source,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Link LINE user to existing customer record
     */
    protected function linkToExistingCustomer($lineUser)
    {
        // Check if already linked
        if ($lineUser->has_customer_record) {
            return;
        }

        $customer = Customer::where('line_user_id', $lineUser->line_user_id)->first();
        
        if ($customer) {
            $lineUser->update(['has_customer_record' => true]);
            
            // Update customer with LINE user data if missing
            $this->syncLineUserToCustomer($lineUser, $customer);
            
            Log::info('Point 36 - LineUserService: Linked to existing customer', [
                'line_user_id' => $lineUser->line_user_id,
                'customer_id' => $customer->id
            ]);
        }
    }

    /**
     * Sync LINE user data to customer record
     */
    protected function syncLineUserToCustomer($lineUser, $customer)
    {
        $updates = [];
        
        // Update display name if customer doesn't have one
        if (!$customer->line_display_name && $lineUser->display_name) {
            $updates['line_display_name'] = $lineUser->display_name;
        }
        
        if (!$customer->line_display_name_original && $lineUser->display_name_original) {
            $updates['line_display_name_original'] = $lineUser->display_name_original;
        }
        
        // Update contact info if available and customer doesn't have it
        if (!$customer->email && $lineUser->getBestEmail()) {
            $updates['email'] = $lineUser->getBestEmail();
        }
        
        if (!$customer->phone && $lineUser->getBestPhone()) {
            $updates['phone'] = $lineUser->getBestPhone();
        }
        
        // Update name if available
        if (!$customer->name && $lineUser->getBestName()) {
            $updates['name'] = $lineUser->getBestName();
        }
        
        if (!empty($updates)) {
            $customer->update($updates);
            
            Log::info('Point 36 - LineUserService: Updated customer with LINE user data', [
                'customer_id' => $customer->id,
                'updates' => array_keys($updates)
            ]);
        }
    }

    /**
     * Sync statistics from existing chat conversations
     */
    protected function syncStatisticsFromConversations($lineUser)
    {
        $stats = ChatConversation::where('line_user_id', $lineUser->line_user_id)
            ->selectRaw('
                COUNT(*) as total_messages,
                COUNT(DISTINCT DATE(message_timestamp)) as conversation_days,
                MIN(message_timestamp) as first_message,
                MAX(message_timestamp) as last_message
            ')
            ->first();
            
        if ($stats && $stats->total_messages > 0) {
            $lineUser->update([
                'message_count' => $stats->total_messages,
                'conversation_count' => $stats->conversation_days,
                'last_message_at' => $stats->last_message,
                'first_interaction_at' => $lineUser->first_interaction_at ?: $stats->first_message,
            ]);
            
            Log::info('Point 36 - LineUserService: Synced statistics from conversations', [
                'line_user_id' => $lineUser->line_user_id,
                'message_count' => $stats->total_messages,
                'conversation_count' => $stats->conversation_days
            ]);
        }
    }

    /**
     * Batch sync LINE users from existing customers
     */
    public function migrateExistingCustomers($batchSize = 100)
    {
        Log::info('Point 36 - LineUserService: Starting migration of existing customers');
        
        $processedCount = 0;
        $errorCount = 0;
        
        // Process customers with LINE user IDs in batches
        Customer::whereNotNull('line_user_id')
            ->chunk($batchSize, function ($customers) use (&$processedCount, &$errorCount) {
                foreach ($customers as $customer) {
                    try {
                        $profileData = [
                            'displayName' => $customer->line_display_name,
                        ];
                        
                        $lineUser = $this->findOrCreateLineUser(
                            $customer->line_user_id, 
                            $profileData,
                            'migration'
                        );
                        
                        $processedCount++;
                        
                    } catch (\Exception $e) {
                        $errorCount++;
                        Log::error('Point 36 - LineUserService: Migration error', [
                            'customer_id' => $customer->id,
                            'line_user_id' => $customer->line_user_id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });
            
        Log::info('Point 36 - LineUserService: Migration completed', [
            'processed_count' => $processedCount,
            'error_count' => $errorCount
        ]);
        
        return ['processed' => $processedCount, 'errors' => $errorCount];
    }

    /**
     * Get LINE user profile from API and update record
     */
    public function refreshUserProfile($lineUserId, $forceRefresh = false)
    {
        $lineUser = LineUser::where('line_user_id', $lineUserId)->first();
        
        if (!$lineUser) {
            return null;
        }
        
        // Check if sync is needed
        if (!$forceRefresh && !$lineUser->needsProfileSync('messaging')) {
            return $lineUser;
        }
        
        try {
            // Get profile from LINE API (using existing ChatController method)
            $chatController = new \App\Http\Controllers\Api\ChatController();
            $profileData = $chatController->getLineUserProfile($lineUserId);
            
            if (!empty($profileData)) {
                $lineUser->syncMessagingApiProfile($profileData);
                
                // Also update associated customer
                if ($lineUser->customer) {
                    $this->syncLineUserToCustomer($lineUser, $lineUser->customer);
                }
                
                Log::info('Point 36 - LineUserService: Profile refreshed successfully', [
                    'line_user_id' => $lineUserId,
                    'profile_data' => $profileData
                ]);
            } else {
                $lineUser->markSyncFailed('Empty profile data from LINE API', 'messaging');
            }
            
        } catch (\Exception $e) {
            $lineUser->markSyncFailed($e->getMessage(), 'messaging');
            
            Log::error('Point 36 - LineUserService: Profile refresh failed', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage()
            ]);
        }
        
        return $lineUser;
    }

    /**
     * Handle friend add event
     * Point 38: Enhanced to handle re-adding friends with profile updates
     */
    public function handleFriendAdd($lineUserId, $profileData = [])
    {
        // Point 38: Check if user already exists to detect re-adding
        $existingUser = LineUser::where('line_user_id', $lineUserId)->first();
        $isReAdding = !is_null($existingUser);
        
        if ($isReAdding) {
            Log::info('Point 38 - LineUserService: Detected re-adding friend', [
                'line_user_id' => $lineUserId,
                'existing_user_id' => $existingUser->id,
                'previous_friend_status' => $existingUser->is_friend,
                'last_updated' => $existingUser->updated_at
            ]);
        }
        
        // Process user (create or update)
        $lineUser = $this->findOrCreateLineUser($lineUserId, $profileData, 'messaging_api');
        
        // Point 38: Force profile refresh for re-adding friends
        if ($isReAdding && !empty($profileData)) {
            $oldData = [
                'display_name' => $existingUser->display_name,
                'picture_url' => $existingUser->picture_url,
                'email' => $existingUser->email,
                'status_message' => $existingUser->status_message
            ];
            
            // Force sync messaging API profile data
            $lineUser->syncMessagingApiProfile($profileData);
            
            // Log what was updated
            $newData = [
                'display_name' => $lineUser->display_name,
                'picture_url' => $lineUser->picture_url,
                'email' => $lineUser->email,
                'status_message' => $lineUser->status_message
            ];
            
            $changes = [];
            foreach ($oldData as $field => $oldValue) {
                if ($oldValue !== $newData[$field]) {
                    $changes[$field] = [
                        'old' => $oldValue,
                        'new' => $newData[$field]
                    ];
                }
            }
            
            if (!empty($changes)) {
                Log::info('Point 38 - LineUserService: Profile data updated on re-adding', [
                    'line_user_id' => $lineUserId,
                    'line_user_table_id' => $lineUser->id,
                    'changes' => $changes
                ]);
            }
        }
        
        // Update friend status
        $lineUser->updateFriendStatus(true);
        
        Log::info('Point 38 - LineUserService: Friend add handled', [
            'line_user_id' => $lineUserId,
            'line_user_table_id' => $lineUser->id,
            'is_re_adding' => $isReAdding,
            'profile_completeness' => $lineUser->getProfileCompletenessScore()
        ]);
        
        return $lineUser;
    }

    /**
     * Handle friend remove event
     */
    public function handleFriendRemove($lineUserId)
    {
        $lineUser = LineUser::where('line_user_id', $lineUserId)->first();
        
        if ($lineUser) {
            $lineUser->updateFriendStatus(false);
            
            Log::info('Point 36 - LineUserService: Friend remove handled', [
                'line_user_id' => $lineUserId,
                'line_user_id_db' => $lineUser->id
            ]);
        }
        
        return $lineUser;
    }

    /**
     * Handle message event
     */
    public function handleMessage($lineUserId, $messageData = [])
    {
        $lineUser = LineUser::where('line_user_id', $lineUserId)->first();
        
        if (!$lineUser) {
            // Create LINE user if doesn't exist
            $lineUser = $this->findOrCreateLineUser($lineUserId, [], 'message');
        }
        
        $lineUser->incrementMessageCount();
        
        return $lineUser;
    }

    /**
     * Get LINE user with full profile
     */
    public function getLineUserProfile($lineUserId, $includeCustomer = true)
    {
        $query = LineUser::where('line_user_id', $lineUserId);
        
        if ($includeCustomer) {
            $query->with(['customer', 'chatConversations' => function($q) {
                $q->latest()->limit(5);
            }]);
        }
        
        return $query->first();
    }

    /**
     * Search LINE users
     */
    public function searchLineUsers($query, $filters = [])
    {
        $builder = LineUser::query();
        
        // Text search
        if ($query) {
            $builder->where(function($q) use ($query) {
                $q->where('display_name', 'like', "%{$query}%")
                  ->orWhere('real_name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
            });
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            $builder->where('status', $filters['status']);
        }
        
        // Friend status filter
        if (isset($filters['is_friend'])) {
            $builder->where('is_friend', $filters['is_friend']);
        }
        
        // Has customer record filter
        if (isset($filters['has_customer'])) {
            $builder->where('has_customer_record', $filters['has_customer']);
        }
        
        // Has email filter
        if (isset($filters['has_email']) && $filters['has_email']) {
            $builder->withEmail();
        }
        
        // Has phone filter
        if (isset($filters['has_phone']) && $filters['has_phone']) {
            $builder->withPhone();
        }
        
        return $builder->with('customer')->latest()->paginate(20);
    }

    /**
     * Get LINE user statistics
     */
    public function getStatistics()
    {
        return Cache::remember('line_user_statistics', 300, function() {
            return [
                'total_users' => LineUser::count(),
                'active_users' => LineUser::active()->count(),
                'friend_users' => LineUser::friends()->count(),
                'users_with_email' => LineUser::withEmail()->count(),
                'users_with_phone' => LineUser::withPhone()->count(),
                'users_with_customer' => LineUser::where('has_customer_record', true)->count(),
                'users_with_login_access' => LineUser::where('has_login_access', true)->count(),
                'users_with_profile_plus' => LineUser::where('has_profile_plus_access', true)->count(),
                'avg_profile_completeness' => LineUser::active()->get()->avg(function($user) {
                    return $user->getProfileCompletenessScore();
                }),
                'total_messages' => LineUser::sum('message_count'),
                'total_conversations' => LineUser::sum('conversation_count'),
                'recent_interactions' => LineUser::where('last_interaction_at', '>=', now()->subWeek())->count(),
            ];
        });
    }
}