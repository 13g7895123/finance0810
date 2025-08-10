<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\Api\PermissionController;

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

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

// LINE Bot webhook
Route::post('/line/webhook', [ChatController::class, 'webhook']);

// Protected routes
Route::middleware(['auth:api'])->group(function () {
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    
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
    
    // Chat Management - Uses customer ownership middleware
    Route::get('/chats', [ChatController::class, 'index']);
    Route::get('/chats/{userId}', [ChatController::class, 'getConversation']);
    Route::post('/chats/{userId}/reply', [ChatController::class, 'reply']);
    Route::get('/chats/unread/count', [ChatController::class, 'getUnreadCount']);
    
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
    });
    
    // Reports (Manager, Admin and Executive only)
    Route::middleware(['role:admin|executive|manager'])->group(function () {
        Route::get('/reports/daily', [ReportController::class, 'dailyReport']);
        Route::get('/reports/monthly', [ReportController::class, 'monthlyReport']);
        Route::get('/reports/website-performance', [ReportController::class, 'websiteReport']);
        Route::get('/reports/region-performance', [ReportController::class, 'regionReport']);
        Route::get('/reports/approval-rates', [ReportController::class, 'approvalRate']);
    });
});