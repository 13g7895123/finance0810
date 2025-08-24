<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestFirebaseService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:test-service';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Firebase service binding and logging functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Firebase Service...');
        
        // 測試日誌功能
        $this->info('1. Testing logging functionality...');
        Log::info('Firebase service test started', ['timestamp' => now()]);
        Log::channel('firebase')->info('Firebase channel test message');
        $this->info('   ✓ Log messages sent');
        
        // 測試 Firebase Database 服務綁定
        $this->info('2. Testing Firebase Database service binding...');
        
        try {
            $database = app('firebase.database');
            $this->info('   ✓ Firebase Database service resolved successfully');
            $this->info('   Database class: ' . get_class($database));
            
            // 測試獲取引用
            try {
                $ref = $database->getReference('test');
                $this->info('   ✓ Reference obtained successfully');
            } catch (\Exception $e) {
                $this->warn('   ⚠ Reference test failed (expected if mock): ' . $e->getMessage());
            }
            
        } catch (\Exception $e) {
            $this->error('   ✗ Firebase Database service binding failed: ' . $e->getMessage());
            Log::error('Firebase service test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // 測試其他 Firebase 服務
        $this->info('3. Testing other Firebase services...');
        
        try {
            $firestore = app('firebase.firestore');
            $this->info('   ✓ Firebase Firestore service resolved');
        } catch (\Exception $e) {
            $this->warn('   ⚠ Firestore service: ' . $e->getMessage());
        }
        
        try {
            $auth = app('firebase.auth');
            $this->info('   ✓ Firebase Auth service resolved');
        } catch (\Exception $e) {
            $this->warn('   ⚠ Auth service: ' . $e->getMessage());
        }
        
        // 檢查配置
        $this->info('4. Checking Firebase configuration...');
        $config = [
            'project_id' => config('services.firebase.project_id'),
            'database_url' => config('services.firebase.database_url'),
            'credentials_exist' => file_exists(config('services.firebase.credentials') ?: ''),
            'credentials_path' => config('services.firebase.credentials')
        ];
        
        $this->table(['Config Key', 'Value'], [
            ['Project ID', $config['project_id'] ?: 'Not set'],
            ['Database URL', $config['database_url'] ?: 'Not set'],
            ['Credentials Exist', $config['credentials_exist'] ? 'Yes' : 'No'],
            ['Credentials Path', $config['credentials_path'] ?: 'Not set']
        ]);
        
        // 記錄配置到日誌
        Log::channel('firebase')->info('Firebase configuration check', $config);
        
        // 檢查日誌檔案
        $this->info('5. Checking log files...');
        $logPath = storage_path('logs/laravel.log');
        $firebaseLogPath = storage_path('logs/firebase.log');
        
        if (file_exists($logPath)) {
            $this->info("   ✓ Laravel log file exists: {$logPath}");
            $size = filesize($logPath);
            $this->info("   Log file size: " . number_format($size) . " bytes");
        } else {
            $this->warn("   ⚠ Laravel log file not found: {$logPath}");
        }
        
        if (file_exists($firebaseLogPath)) {
            $this->info("   ✓ Firebase log file exists: {$firebaseLogPath}");
        } else {
            $this->warn("   ⚠ Firebase log file not found: {$firebaseLogPath}");
        }
        
        $this->info('Firebase service test completed!');
        $this->info('Check the log files for detailed information.');
        
        return Command::SUCCESS;
    }
}