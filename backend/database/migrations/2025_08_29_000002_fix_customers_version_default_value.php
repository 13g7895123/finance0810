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
        // 修改 customers 表的 version 欄位，添加預設值
        if (Schema::hasColumn('customers', 'version')) {
            // 先為現有沒有 version 值的記錄設置預設值
            DB::table('customers')
                ->whereNull('version')
                ->orWhere('version', 0)
                ->update(['version' => 1, 'version_updated_at' => now()]);
            
            // 使用 DB::statement 直接執行 SQL，避免 Doctrine DBAL 問題
            DB::statement('ALTER TABLE customers MODIFY COLUMN version BIGINT UNSIGNED NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE customers MODIFY COLUMN version_updated_at TIMESTAMP NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('customers', 'version')) {
            // 使用 DB::statement 移除預設值，避免 Doctrine DBAL 問題
            DB::statement('ALTER TABLE customers MODIFY COLUMN version BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE customers MODIFY COLUMN version_updated_at TIMESTAMP NOT NULL');
        }
    }
};