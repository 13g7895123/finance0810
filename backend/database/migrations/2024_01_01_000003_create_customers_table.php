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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('region', 50)->nullable();
            $table->string('website_source', 100)->nullable();
            $table->string('channel', 50)->nullable(); // line, email, phone, web_form
            $table->enum('status', ['new', 'contacted', 'interested', 'not_interested', 'invalid', 'converted'])->default('new');
            $table->enum('tracking_status', ['pending', 'scheduled', 'contacted', 'follow_up', 'completed'])->default('pending');
            $table->datetime('tracking_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            // LINE integration
            $table->string('line_user_id')->nullable()->unique();
            $table->string('line_display_name')->nullable();
            $table->json('source_data')->nullable(); // Original data from forms/APIs
            
            // Case and financial data
            $table->enum('case_status', ['submitted', 'approved', 'rejected', 'disbursed'])->nullable();
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('disbursed_amount', 12, 2)->nullable();
            $table->enum('disbursement_status', ['pending', 'processing', 'completed', 'failed'])->nullable();
            
            // Follow-up management
            $table->datetime('next_contact_date')->nullable();
            $table->enum('priority_level', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('invalid_reason', ['refuse_tracking', 'malicious', 'duplicate', 'fake_info'])->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['region', 'created_at']);
            $table->index(['website_source', 'created_at']);
            $table->index(['next_contact_date', 'status']);
            $table->index(['line_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};