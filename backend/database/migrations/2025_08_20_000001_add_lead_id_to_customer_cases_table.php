<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_cases', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_cases', 'lead_id')) {
                $table->foreignId('lead_id')->nullable()->after('customer_id')
                    ->constrained('customer_leads')->nullOnDelete();
                $table->index(['lead_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_cases', function (Blueprint $table) {
            if (Schema::hasColumn('customer_cases', 'lead_id')) {
                $table->dropConstrainedForeignId('lead_id');
            }
        });
    }
};
