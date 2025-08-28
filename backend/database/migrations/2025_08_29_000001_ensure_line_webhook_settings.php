<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\LineIntegrationSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure LINE channel secret and access token are set from environment variables
        $settings = [
            [
                'key' => 'channel_secret',
                'value' => env('LINE_BOT_CHANNEL_SECRET', env('LINE_CHANNEL_SECRET', '')),
                'type' => 'string',
                'description' => 'LINE Channel Secret for webhook signature verification',
                'is_sensitive' => true,
            ],
            [
                'key' => 'channel_access_token',
                'value' => env('LINE_BOT_CHANNEL_ACCESS_TOKEN', env('LINE_CHANNEL_ACCESS_TOKEN', '')),
                'type' => 'string',
                'description' => 'LINE Channel Access Token for sending messages',
                'is_sensitive' => true,
            ],
        ];

        foreach ($settings as $setting) {
            LineIntegrationSetting::firstOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'description' => $setting['description'],
                    'is_sensitive' => $setting['is_sensitive'],
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally remove the settings
        LineIntegrationSetting::whereIn('key', ['channel_secret', 'channel_access_token'])->delete();
    }
};