<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LineUserService;
use App\Models\LineUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Point 36: Test controller for LINE user management system
 */
class LineUserTestController extends Controller
{
    private $lineUserService;
    
    public function __construct(LineUserService $lineUserService)
    {
        $this->lineUserService = $lineUserService;
    }
    
    /**
     * Test LINE user system status
     */
    public function testSystem()
    {
        try {
            // Check if line_users table exists
            $tableExists = Schema::hasTable('line_users');
            
            $result = [
                'success' => true,
                'point_36_status' => 'operational',
                'table_exists' => $tableExists,
                'timestamp' => now()->toDateTimeString()
            ];
            
            if ($tableExists) {
                try {
                    // Get table statistics - try with basic query first
                    $totalUsers = DB::table('line_users')->count();
                    
                    $result['statistics'] = [
                        'total_users' => $totalUsers
                    ];
                    
                    // Only try Eloquent if basic DB query works
                    if ($totalUsers >= 0) {
                        try {
                            $activeUsers = LineUser::active()->count();
                            $friendUsers = LineUser::friends()->count();
                            
                            $result['statistics']['active_users'] = $activeUsers;
                            $result['statistics']['friend_users'] = $friendUsers;
                            
                            // Check if service methods work
                            $stats = $this->lineUserService->getStatistics();
                            $result['service_statistics'] = $stats;
                            
                        } catch (\Exception $serviceError) {
                            $result['eloquent_error'] = 'Eloquent/Service error: ' . $serviceError->getMessage();
                        }
                    }
                    
                } catch (\Exception $dbError) {
                    $result['db_error'] = 'Database error: ' . $dbError->getMessage();
                }
                
            } else {
                $result['error'] = 'line_users table does not exist - migration may not have run';
            }
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Point 36 - LINE user system test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'System test failed: ' . $e->getMessage(),
                'point_36_status' => 'error',
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    
    /**
     * Test creating a LINE user
     */
    public function testCreateUser(Request $request)
    {
        try {
            $testLineUserId = $request->get('line_user_id', 'test_user_' . time());
            $profileData = [
                'displayName' => 'Test User Point 36',
                'pictureUrl' => 'https://example.com/test.jpg',
                'statusMessage' => 'Point 36 test user'
            ];
            
            $lineUser = $this->lineUserService->findOrCreateLineUser(
                $testLineUserId,
                $profileData,
                'api_test'
            );
            
            return response()->json([
                'success' => true,
                'line_user' => [
                    'id' => $lineUser->id,
                    'line_user_id' => $lineUser->line_user_id,
                    'display_name' => $lineUser->display_name,
                    'status' => $lineUser->status,
                    'profile_completeness' => $lineUser->getProfileCompletenessScore(),
                    'created_at' => $lineUser->created_at,
                    'first_interaction_at' => $lineUser->first_interaction_at
                ],
                'message' => 'LINE user created/found successfully via Point 36 system'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Point 36 - LINE user creation test failed', [
                'error' => $e->getMessage(),
                'line_user_id' => $request->get('line_user_id'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'User creation test failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Point 39: Test business display name functionality
     * Tests the dual name management system
     */
    public function testBusinessDisplayName(Request $request)
    {
        try {
            $testLineUserId = $request->get('line_user_id', 'U' . str_pad(dechex(time()), 32, '0', STR_PAD_LEFT));
            
            // Step 1: Create initial user with API data
            $apiProfileData = [
                'displayName' => 'API Original Name - Point 39',
                'pictureUrl' => 'https://example.com/api-user.jpg',
                'statusMessage' => 'API status message'
            ];
            
            Log::info('Point 39 - Test: Creating user with API data', [
                'line_user_id' => $testLineUserId,
                'api_data' => $apiProfileData
            ]);
            
            $lineUser = $this->lineUserService->findOrCreateLineUser(
                $testLineUserId,
                $apiProfileData,
                'messaging_api'
            );
            
            $initialState = [
                'api_name' => $lineUser->getApiDisplayName(),
                'display_name' => $lineUser->getDisplayName(),
                'has_custom_name' => $lineUser->hasCustomBusinessName()
            ];
            
            // Step 2: Business staff updates display name
            $businessName = 'Business Custom Name - Point 39';
            $lineUser->updateBusinessDisplayName($businessName, 1); // Assuming user ID 1
            
            $afterBusinessUpdate = [
                'api_name' => $lineUser->getApiDisplayName(),
                'display_name' => $lineUser->getDisplayName(),
                'business_name' => $lineUser->business_display_name,
                'has_custom_name' => $lineUser->hasCustomBusinessName(),
                'updated_by' => $lineUser->business_name_updated_by,
                'updated_at' => $lineUser->business_name_updated_at
            ];
            
            // Step 3: Simulate API update (should preserve business name)
            $updatedApiData = [
                'displayName' => 'Updated API Name - Point 39',
                'pictureUrl' => 'https://example.com/updated-api-user.jpg'
            ];
            
            $lineUser->syncMessagingApiProfile($updatedApiData);
            
            $afterApiUpdate = [
                'api_name' => $lineUser->getApiDisplayName(),
                'display_name' => $lineUser->getDisplayName(), // Should still show business name
                'business_name' => $lineUser->business_display_name,
                'has_custom_name' => $lineUser->hasCustomBusinessName()
            ];
            
            return response()->json([
                'success' => true,
                'point_39_test' => 'completed',
                'line_user_id' => $testLineUserId,
                'line_user_table_id' => $lineUser->id,
                'test_results' => [
                    'initial_state' => $initialState,
                    'after_business_update' => $afterBusinessUpdate,
                    'after_api_update' => $afterApiUpdate
                ],
                'validation' => [
                    'api_name_protected' => $afterApiUpdate['api_name'] === 'Updated API Name - Point 39',
                    'business_name_preserved' => $afterApiUpdate['display_name'] === $businessName,
                    'dual_system_working' => $afterApiUpdate['api_name'] !== $afterApiUpdate['display_name']
                ],
                'message' => 'Point 39 - Dual name management test completed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Point 39 - Business display name test failed', [
                'error' => $e->getMessage(),
                'line_user_id' => $request->get('line_user_id'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Point 39 test failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Point 38: Test re-adding friend functionality
     * Simulates LINE user re-adding bot with updated profile data
     */
    public function testReAddingFriend(Request $request)
    {
        try {
            $testLineUserId = $request->get('line_user_id', 'U' . str_pad(dechex(time()), 32, '0', STR_PAD_LEFT));
            
            // Step 1: Create initial user
            $initialProfileData = [
                'displayName' => 'Initial Test Name',
                'pictureUrl' => 'https://example.com/initial.jpg',
                'statusMessage' => 'Initial status'
            ];
            
            Log::info('Point 38 - Test: Creating initial user', [
                'line_user_id' => $testLineUserId,
                'initial_data' => $initialProfileData
            ]);
            
            $initialUser = $this->lineUserService->handleFriendAdd(
                $testLineUserId,
                $initialProfileData
            );
            
            // Step 2: Simulate user removing friend (optional)
            $this->lineUserService->handleFriendRemove($testLineUserId);
            
            // Step 3: Simulate user re-adding friend with updated profile
            $updatedProfileData = [
                'displayName' => 'Updated Test Name - Point 38',
                'pictureUrl' => 'https://example.com/updated.jpg',
                'statusMessage' => 'Updated status message',
                'language' => 'zh-TW'
            ];
            
            Log::info('Point 38 - Test: Re-adding friend with updated data', [
                'line_user_id' => $testLineUserId,
                'updated_data' => $updatedProfileData
            ]);
            
            $updatedUser = $this->lineUserService->handleFriendAdd(
                $testLineUserId,
                $updatedProfileData
            );
            
            // Compare data
            $changes = [
                'display_name' => [
                    'old' => $initialProfileData['displayName'],
                    'new' => $updatedUser->display_name
                ],
                'picture_url' => [
                    'old' => $initialProfileData['pictureUrl'],
                    'new' => $updatedUser->picture_url
                ],
                'status_message' => [
                    'old' => $initialProfileData['statusMessage'],
                    'new' => $updatedUser->status_message
                ]
            ];
            
            return response()->json([
                'success' => true,
                'point_38_test' => 'completed',
                'line_user_id' => $testLineUserId,
                'initial_user_id' => $initialUser->id,
                'updated_user_id' => $updatedUser->id,
                'same_record' => $initialUser->id === $updatedUser->id,
                'profile_changes' => $changes,
                'current_profile' => [
                    'display_name' => $updatedUser->display_name,
                    'picture_url' => $updatedUser->picture_url,
                    'status_message' => $updatedUser->status_message,
                    'language' => $updatedUser->language,
                    'profile_completeness' => $updatedUser->getProfileCompletenessScore(),
                    'is_friend' => $updatedUser->is_friend,
                    'friend_added_at' => $updatedUser->friend_added_at,
                    'messaging_api_synced_at' => $updatedUser->messaging_api_synced_at
                ],
                'message' => 'Point 38 - Re-adding friend test completed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Point 38 - Re-adding friend test failed', [
                'error' => $e->getMessage(),
                'line_user_id' => $request->get('line_user_id'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Point 38 test failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}