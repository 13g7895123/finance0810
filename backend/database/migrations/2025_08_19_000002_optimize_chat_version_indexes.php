<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            // 版本號查詢索引 - 用於 Long Polling 版本比較
            $table->index(['version', 'line_user_id'], 'idx_version_user');
            
            // 複合索引用於版本範圍查詢
            $table->index(['version', 'message_timestamp'], 'idx_version_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropIndex('idx_version_user');
            $table->dropIndex('idx_version_timestamp');
        });
    }
};