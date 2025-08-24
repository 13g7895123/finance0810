<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'Finance CRM Backend API',
        'version' => '1.0.0',
        'status' => 'running'
    ]);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString()
    ]);
});

// Broadcasting authentication route
Route::post('/broadcasting/auth', function () {
    return Broadcast::auth(request());
})->middleware('auth:api');

// Firebase 測試路由 - 替代命令行測試
Route::get('/firebase-test', function () {
    $output = [];
    
    try {
        $output[] = '=== Firebase Service Test ===';
        
        // 1. 測試日誌功能
        $output[] = '1. Testing logging functionality...';
        Log::info('Firebase service test started', ['timestamp' => now()]);
        Log::channel('firebase')->info('Firebase channel test message');
        $output[] = '   ✓ Log messages sent';
        
        // 2. 測試 Firebase Database 服務綁定
        $output[] = '2. Testing Firebase Database service binding...';
        
        try {
            $database = app('firebase.database');
            $output[] = '   ✓ Firebase Database service resolved successfully';
            $output[] = '   Database class: ' . get_class($database);
            
            // 測試獲取引用
            try {
                $ref = $database->getReference('test');
                $output[] = '   ✓ Reference obtained successfully';
            } catch (\Exception $e) {
                $output[] = '   ⚠ Reference test failed (expected if mock): ' . $e->getMessage();
            }
            
        } catch (\Exception $e) {
            $output[] = '   ✗ Firebase Database service binding failed: ' . $e->getMessage();
            Log::error('Firebase service test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // 3. 測試其他 Firebase 服務
        $output[] = '3. Testing other Firebase services...';
        
        try {
            $firestore = app('firebase.firestore');
            $output[] = '   ✓ Firebase Firestore service resolved';
        } catch (\Exception $e) {
            $output[] = '   ⚠ Firestore service: ' . $e->getMessage();
        }
        
        try {
            $auth = app('firebase.auth');
            $output[] = '   ✓ Firebase Auth service resolved';
        } catch (\Exception $e) {
            $output[] = '   ⚠ Auth service: ' . $e->getMessage();
        }
        
        // 4. 檢查配置
        $output[] = '4. Checking Firebase configuration...';
        $config = [
            'project_id' => config('services.firebase.project_id') ?: 'Not set',
            'database_url' => config('services.firebase.database_url') ?: 'Not set',
            'credentials_exist' => file_exists(config('services.firebase.credentials') ?: '') ? 'Yes' : 'No',
            'credentials_path' => config('services.firebase.credentials') ?: 'Not set'
        ];
        
        foreach ($config as $key => $value) {
            $output[] = "   {$key}: {$value}";
        }
        
        // 記錄配置到日誌
        Log::channel('firebase')->info('Firebase configuration check', $config);
        
        // 5. 檢查日誌檔案
        $output[] = '5. Checking log files...';
        $logPath = storage_path('logs/laravel.log');
        $firebaseLogPath = storage_path('logs/firebase.log');
        
        if (file_exists($logPath)) {
            $size = filesize($logPath);
            $output[] = "   ✓ Laravel log file exists: {$logPath} (" . number_format($size) . " bytes)";
        } else {
            $output[] = "   ⚠ Laravel log file not found: {$logPath}";
        }
        
        if (file_exists($firebaseLogPath)) {
            $output[] = "   ✓ Firebase log file exists: {$firebaseLogPath}";
        } else {
            $output[] = "   ⚠ Firebase log file not found: {$firebaseLogPath}";
        }
        
        $output[] = '=== Firebase service test completed! ===';
        
    } catch (\Exception $e) {
        $output[] = 'FATAL ERROR: ' . $e->getMessage();
        $output[] = 'Stack trace: ' . $e->getTraceAsString();
    }
    
    // 返回文本響應
    return response(implode("\n", $output), 200, ['Content-Type' => 'text/plain']);
});