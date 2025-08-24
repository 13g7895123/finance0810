<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RefreshFirebaseConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:refresh-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear and refresh Laravel configuration to pick up updated Firebase environment variables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Refreshing Laravel configuration for Firebase...');
        
        // Clear all caches
        $this->info('1. Clearing configuration cache...');
        $this->call('config:clear');
        
        $this->info('2. Clearing route cache...');
        $this->call('route:clear');
        
        $this->info('3. Clearing view cache...');
        $this->call('view:clear');
        
        // Re-cache config with updated environment variables
        $this->info('4. Re-caching configuration...');
        $this->call('config:cache');
        
        $this->info('5. Testing Firebase configuration...');
        
        // Test the configuration
        $config = [
            'project_id' => config('services.firebase.project_id'),
            'database_url' => config('services.firebase.database_url'),
            'credentials_exist' => file_exists(config('services.firebase.credentials') ?: ''),
            'credentials_path' => config('services.firebase.credentials')
        ];
        
        $this->table(['Config Key', 'Value'], [
            ['Project ID', $config['project_id'] ?: 'NOT SET'],
            ['Database URL', $config['database_url'] ?: 'NOT SET'],
            ['Credentials Exist', $config['credentials_exist'] ? 'Yes' : 'No'],
            ['Credentials Path', $config['credentials_path'] ?: 'NOT SET']
        ]);
        
        // Test Firebase service binding
        try {
            $database = app('firebase.database');
            $this->info('✓ Firebase Database service binding successful');
        } catch (\Exception $e) {
            $this->error('✗ Firebase Database service binding failed: ' . $e->getMessage());
        }
        
        $this->info('Firebase configuration refresh completed!');
        
        return Command::SUCCESS;
    }
}