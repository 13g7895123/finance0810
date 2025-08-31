<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point 39: Add business-editable display name field to line_users table
 * This allows business staff to modify display names without affecting original LINE API data
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('line_users', function (Blueprint $table) {
            // Point 39: Business-editable display name
            $table->string('business_display_name')->nullable()->after('display_name_original')
                  ->comment('Business staff can edit this name without affecting original LINE API data');
            
            // Track who last modified the business name and when
            $table->unsignedBigInteger('business_name_updated_by')->nullable()->after('business_display_name');
            $table->timestamp('business_name_updated_at')->nullable()->after('business_name_updated_by');
            
            // Add foreign key constraint
            $table->foreign('business_name_updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('line_users', function (Blueprint $table) {
            $table->dropForeign(['business_name_updated_by']);
            $table->dropColumn(['business_display_name', 'business_name_updated_by', 'business_name_updated_at']);
        });
    }
};