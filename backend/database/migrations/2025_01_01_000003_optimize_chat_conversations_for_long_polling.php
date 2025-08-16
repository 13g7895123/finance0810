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
            // 添加複合索引以優化Long Polling查詢
            $table->index(['message_timestamp', 'line_user_id'], 'idx_timestamp_line_user');
            $table->index(['line_user_id', 'message_timestamp', 'id'], 'idx_line_user_timestamp_id');
            
            // 為經常用於GROUP BY的查詢添加覆蓋索引
            $table->index(['line_user_id', 'message_timestamp', 'is_from_customer'], 'idx_line_user_polling');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropIndex('idx_timestamp_line_user');
            $table->dropIndex('idx_line_user_timestamp_id');
            $table->dropIndex('idx_line_user_polling');
        });
    }
};