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
            
            // 修改欄位定義，添加預設值
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedBigInteger('version')->default(1)->change();
                $table->timestamp('version_updated_at')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('customers', 'version')) {
            Schema::table('customers', function (Blueprint $table) {
                // 移除預設值
                $table->unsignedBigInteger('version')->change();
                $table->timestamp('version_updated_at')->change();
            });
        }
    }
};