<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_blacklisted')->default(false)->after('invalid_reason');
            $table->enum('blacklist_status', ['none','suspected','pending_review','blacklisted','cleared'])->default('none')->after('is_blacklisted');
            $table->string('blacklist_reason')->nullable()->after('blacklist_status');
            $table->foreignId('blacklist_reported_by')->nullable()->constrained('users')->nullOnDelete()->after('blacklist_reason');
            $table->timestamp('blacklist_reported_at')->nullable()->after('blacklist_reported_by');
            $table->foreignId('blacklist_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('blacklist_reported_at');
            $table->timestamp('blacklist_approved_at')->nullable()->after('blacklist_approved_by');
            $table->boolean('is_hidden')->default(false)->after('blacklist_approved_at');
            $table->index(['is_blacklisted', 'blacklist_status']);
            $table->index(['is_hidden']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['is_blacklisted', 'blacklist_status']);
            $table->dropIndex(['is_hidden']);
            $table->dropColumn([
                'is_blacklisted',
                'blacklist_status',
                'blacklist_reason',
                'blacklist_reported_by',
                'blacklist_reported_at',
                'blacklist_approved_by',
                'blacklist_approved_at',
                'is_hidden',
            ]);
        });
    }
};
