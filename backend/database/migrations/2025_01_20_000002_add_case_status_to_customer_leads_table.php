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
        Schema::table('customer_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_leads', 'case_status')) {
                $table->enum('case_status', [
                    'unassigned',      // 未指派
                    'valid_customer',  // 有效客
                    'invalid_customer',// 無效客
                    'customer_service',// 客服
                    'blacklist',       // 黑名單
                    'approved_disbursed', // 核准撥款
                    'conditional',     // 附條件
                    'declined',        // 婉拒
                    'follow_up'        // 追蹤管理
                ])->default('unassigned')->after('suspected_reason');

                $table->index(['case_status']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_leads', function (Blueprint $table) {
            if (Schema::hasColumn('customer_leads', 'case_status')) {
                $table->dropIndex(['case_status']);
                $table->dropColumn('case_status');
            }
        });
    }
};