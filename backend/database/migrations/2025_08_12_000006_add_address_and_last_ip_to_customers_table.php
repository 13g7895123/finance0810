<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('address')->nullable()->after('region');
            $table->string('last_ip_address', 45)->nullable()->after('priority_level');
            $table->index(['channel', 'website_source']);
            $table->index(['is_blacklisted', 'blacklist_status', 'is_hidden']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['channel', 'website_source']);
            $table->dropIndex(['is_blacklisted', 'blacklist_status', 'is_hidden']);
            $table->dropColumn(['address', 'last_ip_address']);
        });
    }
};
