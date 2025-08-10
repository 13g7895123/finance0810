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
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // Platform integration
            $table->string('line_user_id')->nullable();
            $table->enum('platform', ['line', 'email', 'web_chat'])->default('line');
            
            // Message details
            $table->enum('message_type', ['text', 'image', 'file', 'sticker', 'location'])->default('text');
            $table->text('message_content');
            $table->datetime('message_timestamp');
            $table->boolean('is_from_customer')->default(true);
            
            // Reply handling
            $table->text('reply_content')->nullable();
            $table->datetime('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Status tracking
            $table->enum('status', ['unread', 'read', 'replied', 'archived'])->default('unread');
            
            // Additional metadata
            $table->json('metadata')->nullable(); // Store platform-specific data
            
            $table->timestamps();
            
            // Indexes
            $table->index(['customer_id', 'message_timestamp']);
            $table->index(['line_user_id', 'message_timestamp']);
            $table->index(['status', 'is_from_customer']);
            $table->index(['platform', 'message_timestamp']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};