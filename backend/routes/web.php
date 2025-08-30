<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

// Include Point 20 test routes
require __DIR__ . '/point20-test.php';

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

// 環境診斷路由
Route::get('/env-diagnosis', function () {
    $output = [];
    
    try {
        $output[] = '=== Environment Variable Diagnosis ===';
        
        // 檢查環境變數
        $output[] = '1. Checking Firebase Environment Variables:';
        $firebaseEnvVars = [
            'FIREBASE_PROJECT_ID',
            'FIREBASE_DATABASE_URL', 
            'FIREBASE_CREDENTIALS'
        ];
        
        foreach ($firebaseEnvVars as $var) {
            $value = env($var);
            $output[] = "   {$var}: " . ($value ? $value : 'NOT SET');
        }
        
        $output[] = '';
        $output[] = '2. Checking Laravel Configuration:';
        
        $configs = [
            'services.firebase.project_id' => config('services.firebase.project_id'),
            'services.firebase.database_url' => config('services.firebase.database_url'),
            'services.firebase.credentials' => config('services.firebase.credentials'),
        ];
        
        foreach ($configs as $key => $value) {
            $output[] = "   {$key}: " . ($value ?: 'NOT SET');
        }
        
        $output[] = '';
        $output[] = '3. Checking App Environment:';
        $output[] = '   APP_ENV: ' . app()->environment();
        $output[] = '   Config Cached: ' . (app()->configurationIsCached() ? 'YES' : 'NO');
        
        $output[] = '';
        $output[] = '4. Checking Firebase Credentials File:';
        $credentialsPath = config('services.firebase.credentials');
        if ($credentialsPath && file_exists($credentialsPath)) {
            $output[] = "   ✓ File exists: {$credentialsPath}";
            $content = file_get_contents($credentialsPath);
            $json = json_decode($content, true);
            if ($json && isset($json['project_id'])) {
                $output[] = "   Project ID in file: {$json['project_id']}";
            } else {
                $output[] = "   ✗ Invalid JSON or missing project_id";
            }
        } else {
            $output[] = "   ✗ File not found: {$credentialsPath}";
        }
        
        $output[] = '';
        $output[] = '5. System Information:';
        $output[] = '   PHP Version: ' . PHP_VERSION;
        $output[] = '   Laravel Version: ' . app()->version();
        $output[] = '   Current Directory: ' . getcwd();
        
        // 測試直接讀取 .env 檔案
        $output[] = '';
        $output[] = '6. Direct .env file check:';
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            $lines = explode("\n", $envContent);
            foreach ($lines as $line) {
                if (strpos($line, 'FIREBASE_') === 0) {
                    $output[] = "   .env: {$line}";
                }
            }
        } else {
            $output[] = "   .env file not found at: {$envPath}";
        }
        
        $output[] = '';
        $output[] = '=== Diagnosis completed ===';
        
    } catch (\Exception $e) {
        $output[] = 'FATAL ERROR: ' . $e->getMessage();
        $output[] = 'Stack trace: ' . $e->getTraceAsString();
    }
    
    return response(implode("\n", $output), 200, ['Content-Type' => 'text/plain']);
});

// Firebase 配置刷新路由
Route::get('/refresh-firebase-config', function () {
    $output = [];
    
    try {
        $output[] = '=== Refreshing Firebase Configuration ===';
        
        // Clear all caches
        $output[] = '1. Clearing configuration cache...';
        \Artisan::call('config:clear');
        $output[] = '   ✓ Config cache cleared';
        
        $output[] = '2. Clearing route cache...';
        \Artisan::call('route:clear');
        $output[] = '   ✓ Route cache cleared';
        
        $output[] = '3. Clearing view cache...';
        \Artisan::call('view:clear');
        $output[] = '   ✓ View cache cleared';
        
        // Re-cache config with updated environment variables
        $output[] = '4. Re-caching configuration...';
        \Artisan::call('config:cache');
        $output[] = '   ✓ Configuration cached';
        
        $output[] = '5. Testing Firebase configuration...';
        
        // Test the configuration
        $config = [
            'project_id' => config('services.firebase.project_id'),
            'database_url' => config('services.firebase.database_url'),
            'credentials_exist' => file_exists(config('services.firebase.credentials') ?: ''),
            'credentials_path' => config('services.firebase.credentials')
        ];
        
        foreach ($config as $key => $value) {
            if ($key === 'credentials_exist') {
                $output[] = "   {$key}: " . ($value ? 'Yes' : 'No');
            } else {
                $output[] = "   {$key}: " . ($value ?: 'NOT SET');
            }
        }
        
        // Test Firebase service binding
        try {
            $database = app('firebase.database');
            $output[] = '   ✓ Firebase Database service binding successful';
            $output[] = '   Database class: ' . get_class($database);
        } catch (\Exception $e) {
            $output[] = '   ✗ Firebase Database service binding failed: ' . $e->getMessage();
        }
        
        $output[] = '';
        $output[] = '=== Firebase configuration refresh completed! ===';
        
    } catch (\Exception $e) {
        $output[] = 'FATAL ERROR: ' . $e->getMessage();
        $output[] = 'Stack trace: ' . $e->getTraceAsString();
    }
    
    return response(implode("\n", $output), 200, ['Content-Type' => 'text/plain']);
});

// 登入 API 診斷路由
Route::post('/debug-login', function (\Illuminate\Http\Request $request) {
    $output = [];
    
    try {
        $output[] = '=== Login API Debug ===';
        $output[] = 'Request data: ' . json_encode($request->all());
        
        // 檢查資料庫連接
        $output[] = '1. Testing database connection...';
        try {
            \DB::connection()->getPdo();
            $output[] = '   ✓ Database connection successful';
            
            $userCount = \App\Models\User::count();
            $output[] = "   Users in database: {$userCount}";
            
        } catch (\Exception $e) {
            $output[] = '   ✗ Database connection failed: ' . $e->getMessage();
        }
        
        // 檢查 JWT 配置
        $output[] = '2. Testing JWT configuration...';
        try {
            $jwtSecret = config('jwt.secret');
            $output[] = '   JWT Secret: ' . ($jwtSecret ? 'SET' : 'NOT SET');
            
            $factory = \Tymon\JWTAuth\Facades\JWTAuth::factory();
            $output[] = '   ✓ JWT factory created successfully';
            
        } catch (\Exception $e) {
            $output[] = '   ✗ JWT configuration failed: ' . $e->getMessage();
        }
        
        // 檢查 Spatie Permissions
        $output[] = '3. Testing Spatie Permissions...';
        try {
            $roles = \Spatie\Permission\Models\Role::count();
            $permissions = \Spatie\Permission\Models\Permission::count();
            $output[] = "   Roles in database: {$roles}";
            $output[] = "   Permissions in database: {$permissions}";
            $output[] = '   ✓ Spatie Permissions working';
            
        } catch (\Exception $e) {
            $output[] = '   ✗ Spatie Permissions failed: ' . $e->getMessage();
        }
        
        // 測試用戶查詢
        if ($request->has('username')) {
            $output[] = '4. Testing user lookup...';
            try {
                $user = \App\Models\User::where('username', $request->username)
                                      ->orWhere('email', $request->username)
                                      ->first();
                
                if ($user) {
                    $output[] = "   ✓ User found: {$user->name} ({$user->username})";
                    $output[] = "   User status: {$user->status}";
                    $output[] = "   User roles: " . $user->getRoleNames()->implode(', ');
                } else {
                    $output[] = '   ⚠ User not found';
                }
                
            } catch (\Exception $e) {
                $output[] = '   ✗ User lookup failed: ' . $e->getMessage();
            }
        }
        
        // 實際測試登入流程
        if ($request->has('username') && $request->has('password')) {
            $output[] = '5. Testing actual login...';
            try {
                $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                    'username' => 'required|string',
                    'password' => 'required|string|min:6',
                ]);
                
                if ($validator->fails()) {
                    $output[] = '   ⚠ Validation failed: ' . json_encode($validator->errors());
                } else {
                    $user = \App\Models\User::where('username', $request->username)
                                          ->orWhere('email', $request->username)
                                          ->first();
                    
                    if ($user && \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
                        if ($user->status === 'active') {
                            $token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
                            $user->updateLastLogin($request->ip());
                            $output[] = '   ✓ Login successful, token generated';
                        } else {
                            $output[] = '   ⚠ User account not active';
                        }
                    } else {
                        $output[] = '   ⚠ Invalid credentials';
                    }
                }
                
            } catch (\Exception $e) {
                $output[] = '   ✗ Login process failed: ' . $e->getMessage();
                $output[] = '   Stack trace: ' . $e->getTraceAsString();
            }
        }
        
    } catch (\Exception $e) {
        $output[] = 'FATAL ERROR: ' . $e->getMessage();
        $output[] = 'Stack trace: ' . $e->getTraceAsString();
    }
    
    return response(implode("\n", $output), 200, ['Content-Type' => 'text/plain']);
});