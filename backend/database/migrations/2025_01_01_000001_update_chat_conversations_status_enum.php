<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First check if the table exists and what the current schema is
        $schemaManager = Schema::getConnection()->getDoctrineSchemaManager();
        $tableDetails = $schemaManager->listTableDetails('chat_conversations');
        
        if ($tableDetails->hasColumn('status')) {
            // Update the status enum to include all values used in the application
            DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('unread', 'read', 'replied', 'archived', 'sent', 'failed') DEFAULT 'unread'");
            
            // Update any existing invalid status values to valid ones
            DB::statement("UPDATE chat_conversations SET status = 'replied' WHERE status NOT IN ('unread', 'read', 'replied', 'archived', 'sent', 'failed')");
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