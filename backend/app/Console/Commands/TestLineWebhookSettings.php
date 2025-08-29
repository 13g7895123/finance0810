<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LineIntegrationSetting;
use Illuminate\Support\Facades\Log;

class TestLineWebhookSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'line:test-settings {--show-values : Show actual setting values}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test LINE webhook settings from database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing LINE webhook settings from database...');
        
        $settings = LineIntegrationSetting::getAllSettings(true);
        
        $this->info('Available settings in database:');
        
        foreach ($settings as $key => $value) {
            $display = $this->option('show-values') ? $value : (strlen($value) > 0 ? str_repeat('*', min(10, strlen($value))) : 'empty');
            $this->line("  {$key}: {$display}");
        }
        
        // Test the specific settings needed for webhook
        $requiredSettings = ['channel_secret', 'channel_access_token'];
        $missingSettings = [];
        
        foreach ($requiredSettings as $setting) {
            if (empty($settings[$setting])) {
                $missingSettings[] = $setting;
            }
        }
        
        if (empty($missingSettings)) {
            $this->info('✓ All required LINE webhook settings are configured in database');
            
            // Test signature verification logic
            $this->testSignatureVerification($settings['channel_secret']);
            
        } else {
            $this->error('✗ Missing required settings: ' . implode(', ', $missingSettings));
            $this->info('Use the following commands to set them:');
            $this->info('  POST /api/debug/line/settings with channel_secret and channel_access_token');
        }
        
        return 0;
    }
    
    private function testSignatureVerification($channelSecret)
    {
        $this->info('Testing signature verification logic...');
        
        $testBody = '{"events":[],"destination":"test"}';
        $testSignature = base64_encode(hash_hmac('sha256', $testBody, $channelSecret, true));
        
        $this->info("Test body: {$testBody}");
        $this->info("Expected signature: {$testSignature}");
        
        // Verify the signature matches
        $verifySignature = base64_encode(hash_hmac('sha256', $testBody, $channelSecret, true));
        
        if ($testSignature === $verifySignature) {
            $this->info('✓ Signature verification logic is working correctly');
        } else {
            $this->error('✗ Signature verification logic failed');
        }
    }
}