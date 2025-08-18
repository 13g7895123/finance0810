<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_leads', 'status')) {
                $table->string('status', 20)->default('pending')->after('assigned_to');
                $table->index(['status', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_leads', function (Blueprint $table) {
            if (Schema::hasColumn('customer_leads', 'status')) {
                $table->dropIndex(['status', 'created_at']);
                $table->dropColumn('status');
            }
        });
    }
};
