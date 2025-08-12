<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('line_display_name_original')->nullable()->after('line_display_name');
            $table->timestamp('latest_case_at')->nullable()->after('disbursement_status');
            $table->index(['latest_case_at']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['latest_case_at']);
            $table->dropColumn(['line_display_name_original', 'latest_case_at']);
        });
    }
};
