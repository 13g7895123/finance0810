<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LineIntegrationController;
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\BankRecordController;
use App\Http\Controllers\Api\VersionController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\DebugController;
use App\Http\Controllers\Api\WebhookLogController;
use App\Http\Controllers\Api\LineUserTestController;
use App\Http\Controllers\Api\WebsiteController;
use App\Http\Controllers\Api\WebsiteFieldMappingController;
use App\Http\Controllers\Api\CustomerContactScheduleController;
use App\Http\Controllers\Api\LogController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check routes (public)
Route::get('/health', [HealthController::class, 'check']);
Route::get('/health/database', [HealthController::class, 'database']);
Route::get('/health/info', [HealthController::class, 'info']);

// Webhook verification routes (public - no auth required)
Route::get('/diagnose/data-flow', [ChatController::class, 'diagnoseDataFlow']);
Route::get('/verify/webhook-execution', [ChatController::class, 'verifyWebhookExecution']);
Route::get('/webhook/status', [ChatController::class, 'webhookStatus']);

// Simple test route
Route::get('/test/simple', function() { return ['status' => 'ok', 'timestamp' => now()->format('c')]; });


// Test routes (public - for debugging)
Route::get('/test/system', [TestController::class, 'systemTest']);
Route::get('/test/auth', [TestController::class, 'authTest']);
Route::get('/test/setup', [TestController::class, 'setupStatus']);
Route::get('/test/cookies', [TestController::class, 'cookieTest']);
Route::get('/test/simple-debug', [TestController::class, 'simpleDebug']);
Route::get('/test/debug-auth', [TestController::class, 'detailedAuthDebug']);
Route::get('/test/customers-basic', [TestController::class, 'testCustomersBasic']);

// Point 36: LINE User Test routes (public - for debugging)
Route::get('/test/line-user/system', [LineUserTestController::class, 'testSystem']);
Route::get('/test/line-user/create', [LineUserTestController::class, 'testCreateUser']);

// Point 38: LINE User Re-adding Friend Test route (public - for debugging)
Route::get('/test/line-user/re-adding', [LineUserTestController::class, 'testReAddingFriend']);

// Point 39: LINE User Business Display Name Test route (public - for debugging)
Route::get('/test/line-user/business-name', [LineUserTestController::class, 'testBusinessDisplayName']);

// Point 39: Simple table structure check
Route::get('/test/line-user/check-columns', function() {
    try {
        $columns = \Schema::getColumnListing('line_users');
        $hasBusinessColumns = in_array('business_display_name', $columns);
        
        return response()->json([
            'success' => true,
            'table_exists' => \Schema::hasTable('line_users'),
            'all_columns' => $columns,
            'has_business_columns' => $hasBusinessColumns,
            'business_columns' => [
                'business_display_name' => in_array('business_display_name', $columns),
                'business_name_updated_by' => in_array('business_name_updated_by', $columns),
                'business_name_updated_at' => in_array('business_name_updated_at', $columns),
            ],
            'migration_status' => $hasBusinessColumns ? 'completed' : 'pending',
            'timestamp' => now()->format('c')
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Point 36: Basic table check without dependencies
Route::get('/test/line-user/basic', function() {
    try {
        $exists = \Schema::hasTable('line_users');
        $count = $exists ? \DB::table('line_users')->count() : null;
        
        return response()->json([
            'success' => true,
            'line_users_table_exists' => $exists,
            'record_count' => $count,
            'timestamp' => now()->format('c')
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Point 49: Test filled() behavior with empty params
Route::get('/debug/websites-filled-test', function() {
    try {
        $request = request();
        
        return response()->json([
            'success' => true,
            'parameters' => [
                'status_value' => $request->get('status', 'not_set'),
                'type_value' => $request->get('type', 'not_set'),
                'search_value' => $request->get('search', 'not_set'),
            ],
            'has_checks' => [
                'has_status' => $request->has('status'),
                'has_type' => $request->has('type'), 
                'has_search' => $request->has('search'),
            ],
            'filled_checks' => [
                'filled_status' => $request->filled('status'),
                'filled_type' => $request->filled('type'),
                'filled_search' => $request->filled('search'),
            ],
            'basic_website_count' => \App\Models\Website::count(),
            'timestamp' => now()->format('c')
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
});

// Point 49: Direct controller method test  
Route::get('/debug/websites-direct-test', function() {
    try {
        // Test the exact same logic as the controller
        $query = \App\Models\Website::query();
        
        // Test the filtering logic with empty parameters
        $request = request();
        $status = $request->get('status', '');
        $type = $request->get('type', '');
        $search = $request->get('search', '');
        
        $beforeFilters = $query->count();
        
        // Test filled() method behavior
        $statusFilled = $request->filled('status');
        $typeFilled = $request->filled('type'); 
        $searchFilled = $request->filled('search');
        
        // Apply same filters as controller
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%");
            });
        }
        
        $afterFilters = $query->count();
        
        // Apply ordering and pagination
        $query->orderBy('created_at', 'desc');
        $websites = $query->paginate(15);
        
        return response()->json([
            'success' => true,
            'debug_info' => [
                'parameters' => [
                    'status' => $status,
                    'type' => $type,
                    'search' => $search
                ],
                'filled_checks' => [
                    'status_filled' => $statusFilled,
                    'type_filled' => $typeFilled,
                    'search_filled' => $searchFilled
                ],
                'counts' => [
                    'before_filters' => $beforeFilters,
                    'after_filters' => $afterFilters,
                    'paginated_total' => $websites->total(),
                    'paginated_count' => $websites->count()
                ],
                'first_item' => $websites->items()[0] ?? null
            ],
            'websites_data' => $websites,
            'timestamp' => now()->format('c')
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// Point 49: Simple debug without user auth issues
Route::get('/debug/websites-simple-step', function() {
    try {
        // Step 1: Basic counts
        $rawCount = \App\Models\Website::count();
        $basicQuery = \App\Models\Website::query();
        $withRelCount = \App\Models\Website::with(['createdBy', 'updatedBy'])->count();
        
        // Step 2: Test pagination without filters
        $simplePagedQuery = \App\Models\Website::query();
        $simplePaged = $simplePagedQuery->paginate(15);
        
        // Step 3: Test with relationships
        $relPagedQuery = \App\Models\Website::with(['createdBy', 'updatedBy']);
        $relPaged = $relPagedQuery->paginate(15);
        
        // Step 4: Get actual first website data
        $firstWebsite = \App\Models\Website::first();
        
        return response()->json([
            'success' => true,
            'counts' => [
                'raw_count' => $rawCount,
                'with_relations_count' => $withRelCount,
                'simple_paged_total' => $simplePaged->total(),
                'simple_paged_count' => $simplePaged->count(),
                'rel_paged_total' => $relPaged->total(),
                'rel_paged_count' => $relPaged->count(),
            ],
            'first_website' => $firstWebsite ? [
                'id' => $firstWebsite->id,
                'name' => $firstWebsite->name,
                'domain' => $firstWebsite->domain,
                'created_by' => $firstWebsite->created_by,
                'updated_by' => $firstWebsite->updated_by,
                'created_at' => $firstWebsite->created_at,
            ] : null,
            'timestamp' => now()->format('c')
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
});

// Point 49: Debug step by step controller execution
Route::get('/debug/websites-step-by-step', function() {
    try {
        $page = request()->get('page', 1);
        $perPage = request()->get('per_page', 15);
        $search = request()->get('search', '');
        $status = request()->get('status', '');
        $type = request()->get('type', '');
        
        // Step 1: Raw count
        $rawCount = \App\Models\Website::count();
        
        // Step 2: Initial query
        $query = \App\Models\Website::query();
        $initialCount = $query->count();
        
        // Step 3: With relationships
        $query = \App\Models\Website::with(['createdBy', 'updatedBy']);
        $withRelationshipsCount = $query->count();
        
        // Step 4: Apply filters one by one
        $afterStatusFilter = 0;
        $afterTypeFilter = 0;
        $afterSearchFilter = 0;
        
        $query = \App\Models\Website::with(['createdBy', 'updatedBy']);
        
        if ($status) {
            $query->where('status', $status);
        }
        $afterStatusFilter = $query->count();
        
        if ($type) {
            $query->where('type', $type);
        }
        $afterTypeFilter = $query->count();
        
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%");
            });
        }
        $afterSearchFilter = $query->count();
        
        // Step 5: Get final paginated results
        $query->orderBy('created_at', 'desc');
        $paginatedResults = $query->paginate($perPage);
        
        // Step 6: Check user authentication and roles
        $user = \Illuminate\Support\Facades\Auth::user();
        $userRoles = $user ? $user->getRoleNames()->toArray() : [];
        
        return response()->json([
            'success' => true,
            'debug_steps' => [
                'step_1_raw_count' => $rawCount,
                'step_2_initial_count' => $initialCount,
                'step_3_with_relationships_count' => $withRelationshipsCount,
                'step_4_after_status_filter' => $afterStatusFilter,
                'step_4_after_type_filter' => $afterTypeFilter,
                'step_4_after_search_filter' => $afterSearchFilter,
                'step_5_paginated_total' => $paginatedResults->total(),
                'step_5_paginated_count' => $paginatedResults->count(),
                'step_5_paginated_data_count' => count($paginatedResults->items()),
            ],
            'applied_filters' => [
                'status' => $status ?: 'none',
                'type' => $type ?: 'none', 
                'search' => $search ?: 'none',
                'page' => $page,
                'per_page' => $perPage
            ],
            'user_info' => [
                'authenticated' => $user ? true : false,
                'user_id' => $user ? $user->id : null,
                'roles' => $userRoles,
            ],
            'sample_data' => \App\Models\Website::take(2)->get(['id', 'name', 'domain', 'status', 'type']),
            'timestamp' => now()->format('c')
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// Point 49: Debug exact frontend API call
Route::get('/debug/websites-frontend-test', function() {
    try {
        // Simulate exact same parameters as frontend
        $page = request()->get('page', 1);
        $perPage = request()->get('per_page', 15);
        $search = request()->get('search', '');
        $status = request()->get('status', '');
        $type = request()->get('type', '');
        
        // Log the exact parameters
        \Log::info('Point 49 - Frontend API test called', [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'all_params' => request()->all()
        ]);
        
        // Use the exact same controller logic
        $controller = new \App\Http\Controllers\Api\WebsiteController();
        $response = $controller->index(request());
        
        return response()->json([
            'success' => true,
            'controller_response' => $response->getContent(),
            'status_code' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'test_params' => [
                'page' => $page,
                'per_page' => $perPage,
                'search' => $search,
                'status' => $status,
                'type' => $type
            ],
            'timestamp' => now()->format('c')
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// Point 49: Debug website data retrieval issue
Route::get('/debug/websites-check', function() {
    try {
        // Check basic database connection and table existence
        $tableExists = \Schema::hasTable('websites');
        
        if (!$tableExists) {
            return response()->json([
                'success' => false,
                'error' => 'websites table does not exist'
            ]);
        }
        
        // Get basic counts
        $totalCount = \App\Models\Website::count();
        $activeCount = \App\Models\Website::where('status', 'active')->count();
        
        // Get sample data
        $sampleData = \App\Models\Website::take(3)->get(['id', 'name', 'domain', 'status', 'created_at']);
        
        // Test the exact same query as index method
        $query = \App\Models\Website::with(['createdBy', 'updatedBy']);
        $testPagination = $query->paginate(15);
        
        return response()->json([
            'success' => true,
            'table_exists' => $tableExists,
            'total_websites' => $totalCount,
            'active_websites' => $activeCount,
            'sample_data' => $sampleData,
            'pagination_test' => [
                'total' => $testPagination->total(),
                'per_page' => $testPagination->perPage(),
                'current_page' => $testPagination->currentPage(),
                'last_page' => $testPagination->lastPage(),
                'data_count' => $testPagination->count(),
                'first_item' => $testPagination->items()[0] ?? null,
            ],
            'timestamp' => now()->format('c')
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// Point 34: Test route for customer deletion debugging
Route::delete('/test/customers/{customer}/delete', function(\App\Models\Customer $customer) {
    \Log::info('Point 34 - Test delete route called', [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name
    ]);
    
    return response()->json([
        'message' => 'Test delete route works',
        'customer' => [
            'id' => $customer->id,
            'name' => $customer->name
        ]
    ]);
})->middleware('auth:api');

// Point 34: Test actual customer deletion without ownership middleware
Route::delete('/test/customers/{customer}/force-delete', function(\App\Models\Customer $customer) {
    $user = \Illuminate\Support\Facades\Auth::user();
    
    \Log::info('Point 34 - Force delete test route called', [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'user_id' => $user->id,
        'user_name' => $user->name,
        'is_manager' => $user->isManager()
    ]);
    
    try {
        // Create activity log
        \App\Models\CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'activity_type' => 'deleted',
            'description' => "客戶資料已刪除（測試路由）",
            'old_data' => $customer->toArray(),
            'ip_address' => request()->ip(),
        ]);
        
        $customer->delete();
        
        \Log::info('Point 34 - Force delete completed', [
            'customer_id' => $customer->id,
            'user_id' => $user->id
        ]);
        
        return response()->json([
            'message' => 'Customer deleted successfully via test route',
            'customer_id' => $customer->id
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Point 34 - Force delete failed', [
            'customer_id' => $customer->id,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'error' => 'Delete failed',
            'message' => $e->getMessage()
        ], 500);
    }
})->middleware('auth:api');
Route::get('/test/webhook-firebase', [ChatController::class, 'testWebhookFirebase']);

// LINE Debug Routes (public - for debugging LINE integration)
Route::get('/debug/line/settings/test', [DebugController::class, 'testLineSettingsApi']);
Route::get('/debug/line/info-with-signature-test', [DebugController::class, 'lineInfoWithSignatureTest']);
Route::get('/debug/webhook-diagnosis', [DebugController::class, 'webhookDiagnosis']);
Route::post('/debug/simulate-webhook', [DebugController::class, 'simulateWebhook']);

// Firebase Diagnostic Routes (public - for troubleshooting connection issues)
Route::get('/firebase/diagnostic', [DebugController::class, 'diagnosticFirebaseConnection']);
Route::get('/debug/firebase/diagnostic', [DebugController::class, 'diagnosticFirebaseConnection']);
Route::get('/firebase/status', [DebugController::class, 'quickFirebaseStatus']);
Route::get('/test/webhook-firebase-sync', [DebugController::class, 'testWebhookFirebaseSync']);
Route::get('/test/complete-webhook-flow', [DebugController::class, 'testCompleteWebhookFlow']);
Route::get('/debug/webhook-diagnosis', [DebugController::class, 'webhookDiagnosis']);
Route::post('/debug/simulate-webhook', [DebugController::class, 'simulateWebhookEvent']);
Route::get('/debug/recent-chats', [DebugController::class, 'checkRecentChats']);
Route::get('/debug/simple-health', [DebugController::class, 'simpleHealthCheck']);
Route::post('/debug/test-mysql-creation', [DebugController::class, 'testMysqlConversationCreation']);

// Point 20: 直接測試MySQL創建功能 - 使用controller方法避免closure問題
Route::get('/debug/point20-mysql-test', [DebugController::class, 'point20MysqlDirectTest']);
Route::get('/debug/point20-simple-test', [DebugController::class, 'point20SimpleTest']);
Route::get('/debug/point20-customer-test', [DebugController::class, 'point20CustomerTest']);
Route::get('/debug/point20-database-test', [DebugController::class, 'point20DatabaseTest']);
Route::get('/debug/point20-conversation-raw-test', [DebugController::class, 'point20ConversationRawTest']);

// Diagnostic routes for Point 85
Route::get('/diagnostic/basic-health', [\App\Http\Controllers\Api\DiagnosticController::class, 'basicHealth']);
Route::get('/diagnostic/database-check', [\App\Http\Controllers\Api\DiagnosticController::class, 'databaseCheck']);
Route::get('/diagnostic/test-data-creation', [\App\Http\Controllers\Api\DiagnosticController::class, 'testDataCreation']);

// Public routes
Route::post('/auth/login', [AuthController::class, 'login'])->name('login');
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

// Webhooks
Route::post('/line/webhook', [ChatController::class, 'webhook']);
Route::post('/line/webhook-test', [ChatController::class, 'webhookTest']);
Route::post('/line/webhook-simple', [ChatController::class, 'webhookSimpleTest']);
Route::post('/line/webhook-debug', [ChatController::class, 'webhookDebugTest']);
Route::post('/line/webhook-nosig', [ChatController::class, 'webhookNoSignature']);
Route::post('/line/webhook-simulate', [ChatController::class, 'webhookSimulate']);
Route::post('/webhook/wp', [WebhookController::class, 'wp']);

// Point 64: Webhook除錯記錄查看API
Route::get('/webhook/execution-logs', [WebhookController::class, 'getExecutionLogs'])->middleware('auth:api');
Route::get('/webhook/execution-logs/{executionId}', [WebhookController::class, 'getExecutionLogDetail'])->middleware('auth:api');

// Broadcasting authentication route (needs to be here to use API auth)
Route::post('/broadcasting/auth', function () {
    return Broadcast::auth(request());
})->middleware('auth:api');

// Protected routes
Route::middleware(['auth:api'])->group(function () {
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    
    // Dashboard - Available to all authenticated users
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/dashboard/recent-customers', [DashboardController::class, 'getRecentCustomers']);
    Route::get('/dashboard/monthly-summary', [DashboardController::class, 'getMonthlySummary']);
    Route::get('/dashboard/charts', [DashboardController::class, 'getChartsData']);
    
    // Customer Management - Uses customer ownership middleware
    Route::apiResource('customers', CustomerController::class);
    Route::post('/customers/{customer}/track', [CustomerController::class, 'setTrackDate']);
    Route::post('/customers/{customer}/status', [CustomerController::class, 'updateStatus']);
    Route::post('/customers/{customer}/assign', [CustomerController::class, 'assignToUser']);
    Route::get('/customers/{customer}/history', [CustomerController::class, 'getHistory']);
    Route::get('/customers/submittable', [CustomerController::class, 'submittable']);
    
    // Tracking Management - Point 66
    Route::get('/tracking/customers', [CustomerController::class, 'trackingList']);
    Route::patch('/customers/{customer}/level', [CustomerController::class, 'updateCustomerLevel']);
    Route::get('/tracking/sales-users', [CustomerController::class, 'getSalesUsers']);
    
    // Contact Schedule Management - Point 69
    Route::get('/contact-schedules', [CustomerContactScheduleController::class, 'index']);
    Route::post('/contact-schedules', [CustomerContactScheduleController::class, 'store']);
    Route::get('/contact-schedules/{id}', [CustomerContactScheduleController::class, 'show']);
    Route::put('/contact-schedules/{id}', [CustomerContactScheduleController::class, 'update']);
    Route::delete('/contact-schedules/{id}', [CustomerContactScheduleController::class, 'destroy']);
    Route::post('/contact-schedules/{id}/contacted', [CustomerContactScheduleController::class, 'markAsContacted']);
    Route::post('/contact-schedules/{id}/reschedule', [CustomerContactScheduleController::class, 'reschedule']);
    Route::get('/contact-schedules/overdue/list', [CustomerContactScheduleController::class, 'getOverdue']);
    Route::get('/contact-schedules/today/list', [CustomerContactScheduleController::class, 'getToday']);
    Route::get('/contact-schedules/reminders/list', [CustomerContactScheduleController::class, 'getNeedingReminder']);
    Route::get('/contact-schedules/calendar/data', [CustomerContactScheduleController::class, 'getCalendarData']);
    
    // LINE Integration for Customers
    Route::post('/customers/{customer}/line/link', [CustomerController::class, 'linkLineUser']);
    Route::delete('/customers/{customer}/line/unlink', [CustomerController::class, 'unlinkLineUser']);
    Route::get('/customers/{customer}/line/friend-status', [CustomerController::class, 'checkLineFriendStatus']);

    // Blacklist Management
    Route::post('/customers/{customer}/blacklist/report', [\App\Http\Controllers\Api\BlacklistController::class, 'report']);
    Route::post('/customers/{customer}/blacklist/approve', [\App\Http\Controllers\Api\BlacklistController::class, 'approve'])->middleware('role:admin|executive|manager');
    Route::post('/customers/{customer}/blacklist/toggle-hide', [\App\Http\Controllers\Api\BlacklistController::class, 'toggleHide'])->middleware('role:admin|executive|manager');
    
    // Chat Management - Uses customer ownership middleware
    Route::get('/chats', [ChatController::class, 'index']);
    Route::get('/chats/search', [ChatController::class, 'searchConversations']);
    Route::get('/chats/stats', [ChatController::class, 'getChatStats']);
    Route::get('/chats/test-permissions', [ChatController::class, 'testPermissions']);
    Route::get('/chats/unread/count', [ChatController::class, 'getUnreadCount']);
    Route::get('/chats/poll-updates', [ChatController::class, 'pollUpdates']);
    Route::get('/chats/incremental', [ChatController::class, 'getIncrementalUpdates']);
    Route::post('/chats/validate-checksum', [ChatController::class, 'validateChecksum']);
    Route::get('/chats/{userId}', [ChatController::class, 'getConversation']);
    Route::post('/chats/{userId}/reply', [ChatController::class, 'reply']);
    Route::post('/chats/{userId}/read', [ChatController::class, 'markAsRead']);
    Route::delete('/chats/{userId}', [ChatController::class, 'deleteConversation']);
    Route::post('/chats/test-websocket', [ChatController::class, 'testWebSocketBroadcast']);
    
    // Firebase Chat API
    Route::prefix('firebase/chat')->group(function () {
        Route::get('/conversations', [ChatController::class, 'getFirebaseConversations']);
        Route::get('/messages/{userId}', [ChatController::class, 'getFirebaseMessages']);
        Route::post('/sync', [ChatController::class, 'syncToFirebase']);
        Route::post('/sync/customer/{customerId}', [ChatController::class, 'syncCustomerToFirebase']);
        Route::get('/health', [ChatController::class, 'checkFirebaseHealth']);
        Route::post('/validate', [ChatController::class, 'validateFirebaseData']);
        Route::delete('/cleanup', [ChatController::class, 'cleanupFirebaseData'])->middleware('role:admin|manager');
    });

    // Debug API Endpoints - Only available in debug mode with admin access
    Route::prefix('debug')->middleware(['role:admin|executive|manager'])->group(function () {
        // System Health and Debug Information
        Route::get('/system/health', [DebugController::class, 'systemHealthCheck']);
        Route::get('/system/info', [DebugController::class, 'getDebugInfo']);
        
        // Firebase Batch Operations
        Route::post('/firebase/batch-sync', [DebugController::class, 'batchSyncToFirebase']);
        Route::post('/firebase/reset', [DebugController::class, 'resetFirebaseData'])->middleware('role:admin');
        Route::post('/firebase/test-connection', [DebugController::class, 'testFirebaseConnection']);
        
        // LINE Settings Management
        Route::get('/line/settings', [DebugController::class, 'getLineSettings']);
        Route::post('/line/settings', [DebugController::class, 'updateLineSettings']);
        
        // Chat Debug Operations
        Route::post('/chat/batch-sync', [ChatController::class, 'batchSyncToFirebaseDebug']);
        Route::post('/chat/full-sync', [ChatController::class, 'fullSyncToFirebase']);
        Route::post('/chat/validate-integrity', [ChatController::class, 'validateFirebaseDataIntegrity']);
        Route::post('/chat/cleanup-firebase', [ChatController::class, 'cleanupFirebaseDataDebug'])->middleware('role:admin|manager');
        
        // Extended Firebase Health Checks
        Route::get('/firebase/health-extended', [ChatController::class, 'checkFirebaseHealth']);

        // Application Log Management (Point 16 - 錯誤日誌查看)
        Route::prefix('logs')->group(function () {
            Route::get('/errors', [LogController::class, 'getErrorLogs']);
            Route::get('/stats', [LogController::class, 'getErrorStats']);
            Route::get('/critical', [LogController::class, 'getCriticalErrors']);
            Route::get('/patterns', [LogController::class, 'getPatternAnalysis']);
            Route::get('/health', [LogController::class, 'getSystemHealth']);
            Route::post('/alerts/check', [LogController::class, 'checkCriticalAlerts']);
            Route::post('/cleanup', [LogController::class, 'cleanOldLogs'])->middleware('role:admin');
        });
    });
    
    // Version Management - Available to all authenticated users
    Route::prefix('version')->group(function () {
        Route::get('current', [VersionController::class, 'getCurrentVersion']);
        Route::get('changes', [VersionController::class, 'getIncrementalChanges']);
        Route::get('history', [VersionController::class, 'getVersionHistory']);
        Route::post('check-conflict', [VersionController::class, 'checkVersionConflict']);
        Route::get('stats', [VersionController::class, 'getVersionStats']);
    });
    
    // Incremental Sync - Available to all authenticated users
    Route::prefix('sync')->group(function () {
        Route::get('{entityType}', [SyncController::class, 'getUpdates']);
        Route::post('{entityType}/validate', [SyncController::class, 'validateIntegrity']);
        Route::get('{entityType}/stats', [SyncController::class, 'getStats']);
    });
    
    // Point 40: Website Options - Available to all authenticated users
    Route::get('/websites/options', [WebsiteController::class, 'options']);
    
    // Webhook Execution Logs - Available to all authenticated users
    Route::prefix('webhook-logs')->group(function () {
        Route::get('/', [WebhookLogController::class, 'index']);
        Route::get('/statistics', [WebhookLogController::class, 'statistics']);
        Route::get('/recent', [WebhookLogController::class, 'recent']);
        Route::get('/export', [WebhookLogController::class, 'export']);
        Route::get('/execution/{executionId}', [WebhookLogController::class, 'getByExecutionId']);
        Route::get('/{id}', [WebhookLogController::class, 'show']);
    });
    
    // Webhook Log Management (Admin/Manager only)
    Route::middleware(['role:admin|executive|manager'])->group(function () {
        Route::delete('/webhook-logs/cleanup', [WebhookLogController::class, 'cleanup']);
    });
    
    // Query Performance and Cache Management (Admin/Manager only)
    Route::middleware(['role:admin|executive|manager'])->group(function () {
        Route::get('/chats/admin/query-stats', [ChatController::class, 'getQueryStats']);
        Route::post('/chats/admin/clear-cache', [ChatController::class, 'clearQueryCache']);
    });
    
    // User Management (Admin and Manager only)
    Route::middleware(['role:admin|executive|manager'])->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/users/{user}/roles', [UserController::class, 'assignRole']);
        Route::delete('/users/{user}/roles/{role}', [UserController::class, 'removeRole']);
        Route::get('/roles', [UserController::class, 'getRoles']);
        Route::get('/users/stats/overview', [UserController::class, 'getStats']);
        
        // Permission Management
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/permissions/category/{category}', [PermissionController::class, 'getByCategory']);
        Route::get('/users/{user}/roles', [PermissionController::class, 'getUserRoles']);
        Route::get('/roles/{role}/permissions', [PermissionController::class, 'getRolePermissions']);
        Route::post('/roles/{role}/permissions', [PermissionController::class, 'assignPermissionToRole']);
        Route::delete('/roles/{role}/permissions/{permissionName}', [PermissionController::class, 'removePermissionFromRole']);
        
        // Point 40: Website Management (Admin and Manager only)
        Route::apiResource('websites', WebsiteController::class);
        Route::put('/websites/{website}/statistics', [WebsiteController::class, 'updateStatistics']);
        Route::get('/websites-statistics', [WebsiteController::class, 'statistics']);
        
        // Point 61: Website Field Mapping Management
        Route::get('/websites/{website}/field-mappings', [WebsiteFieldMappingController::class, 'index']);
        Route::post('/websites/{website}/field-mappings', [WebsiteFieldMappingController::class, 'store']);
        Route::post('/websites/{website}/field-mappings/defaults', [WebsiteFieldMappingController::class, 'createDefaults']);
        Route::post('/websites/{website}/field-mappings/test', [WebsiteFieldMappingController::class, 'test']);
        Route::get('/field-mappings/system-fields', [WebsiteFieldMappingController::class, 'systemFields']);
        
        // Point 62: Custom System Fields Management
        Route::post('/field-mappings/system-fields', [WebsiteFieldMappingController::class, 'addSystemField']);
    });
    
    // Point 10: DEBUG ROUTE - Test if routes work at all
    Route::get('/debug-test-route', function() {
        file_put_contents('/tmp/debug_route_test.txt', 'DEBUG ROUTE CALLED AT ' . date('Y-m-d H:i:s'));
        return response()->json(['message' => 'Debug route works', 'time' => date('Y-m-d H:i:s')]);
    });

    // Leads (pending cases)
    Route::get('/leads', [LeadController::class, 'index']);
    Route::get('/leads/submittable', [LeadController::class, 'submittable']);
    Route::get('/leads/case-status-options', [LeadController::class, 'getCaseStatusOptions']);
    Route::get('/leads/{lead}', [LeadController::class, 'show']);
    Route::put('/leads/{lead}', [LeadController::class, 'update']);
    Route::patch('/leads/{lead}/case-status', [LeadController::class, 'updateCaseStatus']);
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy']);

    // Point 37: LINE user name update for leads
    Route::put('/leads/{lead}/line-name', [LeadController::class, 'updateLineUserName']);

    // Cases
    Route::get('/cases', [CaseController::class, 'index']);
    Route::get('/cases/status-options', [CaseController::class, 'getCaseStatusOptions']);
    Route::get('/cases/{case}', [CaseController::class, 'show']);
    Route::put('/cases/{case}', [CaseController::class, 'update']);
    Route::patch('/cases/{case}/status', [CaseController::class, 'updateCaseStatus']);
    Route::post('/customers/{customer}/cases', [CaseController::class, 'storeForCustomer']);

    // Bank Records (for negotiated cases view)
    Route::get('/bank-records', [BankRecordController::class, 'index']);
    Route::post('/bank-records', [BankRecordController::class, 'store']);
    Route::put('/bank-records/{record}', [BankRecordController::class, 'update']);

    // Reports (Manager, Admin and Executive only)
    Route::middleware(['role:admin|executive|manager'])->group(function () {
        Route::get('/reports/daily', [ReportController::class, 'dailyReport']);
        Route::get('/reports/monthly', [ReportController::class, 'monthlyReport']);
        Route::get('/reports/website-performance', [ReportController::class, 'websiteReport']);
        Route::get('/reports/region-performance', [ReportController::class, 'regionReport']);
        Route::get('/reports/approval-rates', [ReportController::class, 'approvalRate']);

        // Custom fields management
        Route::get('/custom-fields', [\App\Http\Controllers\Api\CustomFieldController::class, 'index'])->withoutMiddleware(['role:admin|executive|manager']);
        Route::post('/custom-fields', [\App\Http\Controllers\Api\CustomFieldController::class, 'store']);
        Route::put('/custom-fields/{field}', [\App\Http\Controllers\Api\CustomFieldController::class, 'update']);
        Route::delete('/custom-fields/{field}', [\App\Http\Controllers\Api\CustomFieldController::class, 'destroy']);
        Route::post('/custom-fields/set-value', [\App\Http\Controllers\Api\CustomFieldController::class, 'setValue']);

        // LINE Integration Management (Admin and Manager only)
        Route::prefix('line-integration')->group(function () {
            Route::get('/settings', [LineIntegrationController::class, 'getSettings']);
            Route::post('/settings', [LineIntegrationController::class, 'updateSettings']);
            Route::post('/test-connection', [LineIntegrationController::class, 'testConnection']);
            Route::get('/debug-connection', [LineIntegrationController::class, 'debugConnection']);
            Route::get('/bot-info', [LineIntegrationController::class, 'getBotInfo']);
            Route::get('/stats', [LineIntegrationController::class, 'getStats']);
            Route::get('/recent-conversations', [LineIntegrationController::class, 'getRecentConversations']);
            Route::post('/send-message', [LineIntegrationController::class, 'sendMessage']);
        });
    });
});