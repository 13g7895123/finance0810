<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 移除舊的索引（如果存在）
        Schema::table('chat_conversations', function (Blueprint $table) {
            // 檢查並移除舊索引
            try {
                $indexes = DB::select('SHOW INDEX FROM chat_conversations');
                $indexNames = collect($indexes)->pluck('Key_name')->unique();
                
                if ($indexNames->contains('idx_line_user_timestamp')) {
                    $table->dropIndex('idx_line_user_timestamp');
                }
                if ($indexNames->contains('idx_status_customer')) {
                    $table->dropIndex('idx_status_customer');
                }
            } catch (\Exception $e) {
                // 索引可能不存在，忽略錯誤
            }
        });
        
        // 建立覆蓋索引（包含所有查詢需要的欄位）
        Schema::table('chat_conversations', function (Blueprint $table) {
            // 主要查詢索引：覆蓋 line_user_id 的所有查詢
            $table->index([
                'line_user_id',
                'message_timestamp',
                'version',
                'status',
                'is_from_customer'
            ], 'idx_covering_main_query');
            
            // 版本查詢索引：用於 Long Polling
            $table->index([
                'version',
                'message_timestamp',
                'line_user_id'
            ], 'idx_covering_version_query');
            
            // 未讀計數索引
            $table->index([
                'line_user_id',
                'status',
                'is_from_customer',
                'id'
            ], 'idx_covering_unread_count');
        });
        
        // 優化 customers 表索引
        Schema::table('customers', function (Blueprint $table) {
            try {
                $table->index(['line_user_id', 'assigned_to'], 'idx_customer_assignment');
            } catch (\Exception $e) {
                // 索引可能已存在，忽略錯誤
            }
        });
    }
    
    public function down()
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_covering_main_query');
                $table->dropIndex('idx_covering_version_query');
                $table->dropIndex('idx_covering_unread_count');
            } catch (\Exception $e) {
                // 索引可能不存在，忽略錯誤
            }
        });
        
        Schema::table('customers', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_customer_assignment');
            } catch (\Exception $e) {
                // 索引可能不存在，忽略錯誤
            }
        });
    }
};