<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DiagnoseEnvironment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:env';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose environment variables and configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Environment Variable Diagnosis');
        $this->info('================================');
        
        // 檢查環境變數
        $this->info('1. Checking Firebase Environment Variables:');
        $firebaseEnvVars = [
            'FIREBASE_PROJECT_ID',
            'FIREBASE_DATABASE_URL', 
            'FIREBASE_CREDENTIALS'
        ];
        
        foreach ($firebaseEnvVars as $var) {
            $value = env($var);
            $this->line("   {$var}: " . ($value ? $value : 'NOT SET'));
        }
        
        $this->info('');
        $this->info('2. Checking Laravel Configuration:');
        
        $configs = [
            'services.firebase.project_id' => config('services.firebase.project_id'),
            'services.firebase.database_url' => config('services.firebase.database_url'),
            'services.firebase.credentials' => config('services.firebase.credentials'),
        ];
        
        foreach ($configs as $key => $value) {
            $this->line("   {$key}: " . ($value ?: 'NOT SET'));
        }
        
        $this->info('');
        $this->info('3. Checking App Environment:');
        $this->line('   APP_ENV: ' . app()->environment());
        $this->line('   Config Cached: ' . (app()->configurationIsCached() ? 'YES' : 'NO'));
        
        $this->info('');
        $this->info('4. Checking Firebase Credentials File:');
        $credentialsPath = config('services.firebase.credentials');
        if ($credentialsPath && file_exists($credentialsPath)) {
            $this->info("   ✓ File exists: {$credentialsPath}");
            $content = file_get_contents($credentialsPath);
            $json = json_decode($content, true);
            if ($json && isset($json['project_id'])) {
                $this->line("   Project ID in file: {$json['project_id']}");
            } else {
                $this->error("   ✗ Invalid JSON or missing project_id");
            }
        } else {
            $this->error("   ✗ File not found: {$credentialsPath}");
        }
        
        $this->info('');
        $this->info('5. System Information:');
        $this->line('   PHP Version: ' . PHP_VERSION);
        $this->line('   Laravel Version: ' . app()->version());
        $this->line('   Current Directory: ' . getcwd());
        
        return Command::SUCCESS;
    }
}