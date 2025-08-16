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
        // Update the status enum to include additional values
        DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('unread', 'read', 'replied', 'archived', 'pending', 'sent', 'delivered', 'failed') DEFAULT 'unread'");
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