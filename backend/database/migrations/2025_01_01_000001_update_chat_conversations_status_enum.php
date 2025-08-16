<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if the table exists first
        if (Schema::hasTable('chat_conversations')) {
            // Check if status column exists before modifying
            if (Schema::hasColumn('chat_conversations', 'status')) {
                // Update the status enum to include all values used in the application
                DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('unread', 'read', 'replied', 'archived', 'sent', 'failed') DEFAULT 'unread'");
                
                // Update any existing invalid status values to valid ones
                DB::statement("UPDATE chat_conversations SET status = 'replied' WHERE status NOT IN ('unread', 'read', 'replied', 'archived', 'sent', 'failed')");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('unread', 'read', 'replied', 'archived') DEFAULT 'unread'");
    }
};