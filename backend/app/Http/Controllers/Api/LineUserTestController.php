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
                // Get table statistics
                $totalUsers = LineUser::count();
                $activeUsers = LineUser::active()->count();
                $friendUsers = LineUser::friends()->count();
                
                $result['statistics'] = [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'friend_users' => $friendUsers
                ];
                
                // Check if service methods work
                $stats = $this->lineUserService->getStatistics();
                $result['service_statistics'] = $stats;
                
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
                'point_36_status' => 'error'
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
}