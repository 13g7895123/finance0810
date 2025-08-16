<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_leads', function (Blueprint $table) {
            // 新增承辦業務欄位，關聯 users.id
            if (!Schema::hasColumn('customer_leads', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
                $table->index(['assigned_to', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_leads', function (Blueprint $table) {
            if (Schema::hasColumn('customer_leads', 'assigned_to')) {
                $table->dropForeign(['assigned_to']);
                $table->dropIndex(['assigned_to', 'created_at']);
                $table->dropColumn('assigned_to');
            }
        });
    }
};
