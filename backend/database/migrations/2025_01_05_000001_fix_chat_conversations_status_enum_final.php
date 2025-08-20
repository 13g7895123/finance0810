<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Fix chat_conversations status enum to include all needed values
        if (Schema::hasTable('chat_conversations')) {
            try {
                // Get current status values
                $currentStatuses = DB::table('chat_conversations')
                    ->select('status')
                    ->distinct()
                    ->pluck('status')
                    ->toArray();
                
                // Update any problematic status values to valid ones
                DB::table('chat_conversations')
                    ->whereNotIn('status', ['unread', 'read', 'replied', 'archived', 'sent', 'failed', 'delivered', 'pending'])
                    ->update(['status' => 'unread']);
                
                // Now safely alter the column
                DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('unread', 'read', 'replied', 'archived', 'sent', 'failed', 'delivered', 'pending') DEFAULT 'unread'");
                
                \Log::info('Successfully updated chat_conversations status enum');
            } catch (\Exception $e) {
                \Log::warning('Failed to update status enum: ' . $e->getMessage());
            }
        }
    }
    
    public function down()
    {
        // Revert to original enum values if needed
        if (Schema::hasTable('chat_conversations')) {
            DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('unread', 'read', 'replied', 'archived') DEFAULT 'unread'");
        }
    }
};