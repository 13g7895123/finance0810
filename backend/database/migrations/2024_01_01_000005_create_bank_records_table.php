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
        Schema::create('bank_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('customer_cases')->nullOnDelete();
            
            // Bank information
            $table->string('bank_name', 100);
            $table->string('contact_person', 100)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email', 100)->nullable();
            
            // Communication details
            $table->enum('communication_type', ['phone', 'email', 'meeting', 'video_call'])->default('phone');
            $table->datetime('communication_date');
            $table->text('content'); // Main communication content
            $table->text('result')->nullable(); // Outcome/result
            $table->text('next_action')->nullable(); // Next steps
            $table->datetime('next_contact_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('attachments')->nullable(); // File attachments
            
            $table->timestamps();
            
            // Indexes
            $table->index(['customer_id', 'communication_date']);
            $table->index(['case_id', 'status']);
            $table->index(['bank_name', 'communication_date']);
            $table->index(['next_contact_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_records');
    }
};