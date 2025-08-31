<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Point 36: Create line_users table for centralized LINE user management
     * Supports both LINE Messaging API and LINE Login API data
     */
    public function up(): void
    {
        Schema::create('line_users', function (Blueprint $table) {
            $table->id();
            
            // Core LINE identifiers
            $table->string('line_user_id')->unique(); // Primary LINE identifier
            $table->string('display_name')->nullable(); // Current display name
            $table->string('display_name_original')->nullable(); // Original display name
            
            // LINE Messaging API data (always available)
            $table->text('picture_url')->nullable(); // Profile picture URL
            $table->text('status_message')->nullable(); // User's status message
            $table->string('language', 10)->nullable(); // User's language setting
            
            // LINE Login API data (requires permissions)
            $table->string('email')->nullable(); // Email from LINE Login API
            $table->boolean('email_verified')->default(false); // Email verification status
            $table->string('phone')->nullable(); // Phone from LINE Profile+ (corporate)
            $table->boolean('phone_verified')->default(false); // Phone verification status
            
            // LINE Profile+ extended data (corporate features)
            $table->string('real_name')->nullable(); // Real name
            $table->string('gender')->nullable(); // Gender
            $table->date('birth_date')->nullable(); // Birth date
            $table->text('address')->nullable(); // Address
            
            // API access tracking
            $table->boolean('has_login_access')->default(false); // Has LINE Login permissions
            $table->boolean('has_profile_plus_access')->default(false); // Has Profile+ permissions
            $table->json('granted_scopes')->nullable(); // Scopes granted by user
            $table->timestamp('login_last_used_at')->nullable(); // Last LINE Login usage
            
            // Profile sync metadata
            $table->timestamp('messaging_api_synced_at')->nullable(); // Last Messaging API sync
            $table->timestamp('login_api_synced_at')->nullable(); // Last Login API sync
            $table->boolean('profile_sync_failed')->default(false); // Sync failure flag
            $table->text('profile_sync_error')->nullable(); // Last sync error
            
            // Extended data from interactions
            $table->string('inferred_phone')->nullable(); // Phone from conversations
            $table->string('inferred_email')->nullable(); // Email from conversations  
            $table->json('interaction_data')->nullable(); // Data from conversations
            
            // Relationship tracking
            $table->boolean('has_customer_record')->default(false); // Has linked customer
            $table->timestamp('first_interaction_at')->nullable(); // First contact time
            $table->timestamp('last_interaction_at')->nullable(); // Last contact time
            
            // Profile status
            $table->enum('status', ['active', 'blocked', 'deleted', 'unknown'])->default('active');
            $table->boolean('is_friend')->default(true); // Current friend status with bot
            $table->timestamp('friend_added_at')->nullable(); // When they added bot as friend
            $table->timestamp('friend_removed_at')->nullable(); // When they removed bot
            
            // Statistics
            $table->integer('message_count')->default(0); // Total messages sent
            $table->integer('conversation_count')->default(0); // Total conversations
            $table->timestamp('last_message_at')->nullable(); // Last message timestamp
            
            $table->timestamps();
            $table->softDeletes(); // Soft delete for data retention
            
            // Indexes for performance
            $table->index(['line_user_id']); // Primary lookup
            $table->index(['display_name']); // Name search
            $table->index(['email']); // Email lookup
            $table->index(['phone']); // Phone lookup
            $table->index(['status', 'is_friend']); // Status filtering
            $table->index(['has_login_access']); // Login API users
            $table->index(['has_profile_plus_access']); // Profile+ users
            $table->index(['messaging_api_synced_at']); // Sync scheduling
            $table->index(['first_interaction_at', 'last_interaction_at']); // Activity tracking
            $table->index(['has_customer_record']); // Customer relationship
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_users');
    }
};