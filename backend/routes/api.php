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

// Test routes (public - for debugging)
Route::get('/test/system', [TestController::class, 'systemTest']);
Route::get('/test/auth', [TestController::class, 'authTest']);
Route::get('/test/setup', [TestController::class, 'setupStatus']);
Route::get('/test/cookies', [TestController::class, 'cookieTest']);
Route::get('/test/simple-debug', [TestController::class, 'simpleDebug']);
Route::get('/test/debug-auth', [TestController::class, 'detailedAuthDebug']);
Route::get('/test/customers-basic', [TestController::class, 'testCustomersBasic']);

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

// Webhooks
Route::post('/line/webhook', [ChatController::class, 'webhook']);
Route::post('/webhook/wp', [WebhookController::class, 'wp']);

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
        
        // Chat Debug Operations
        Route::post('/chat/batch-sync', [ChatController::class, 'batchSyncToFirebaseDebug']);
        Route::post('/chat/full-sync', [ChatController::class, 'fullSyncToFirebase']);
        Route::post('/chat/validate-integrity', [ChatController::class, 'validateFirebaseDataIntegrity']);
        Route::post('/chat/cleanup-firebase', [ChatController::class, 'cleanupFirebaseDataDebug'])->middleware('role:admin|manager');
        
        // Extended Firebase Health Checks
        Route::get('/firebase/health-extended', [ChatController::class, 'checkFirebaseHealth']);
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
    });
    
    // Leads (pending cases)
    Route::get('/leads', [LeadController::class, 'index']);
    Route::get('/leads/submittable', [LeadController::class, 'submittable']);
    Route::get('/leads/{lead}', [LeadController::class, 'show']);
    Route::put('/leads/{lead}', [LeadController::class, 'update']);
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy']);

    // Cases
    Route::get('/cases', [CaseController::class, 'index']);
    Route::get('/cases/{case}', [CaseController::class, 'show']);
    Route::put('/cases/{case}', [CaseController::class, 'update']);
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