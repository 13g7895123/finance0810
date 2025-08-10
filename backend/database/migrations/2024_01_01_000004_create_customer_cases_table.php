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
        Schema::create('customer_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('case_number')->unique();
            $table->decimal('loan_amount', 12, 2);
            $table->string('loan_type', 50)->nullable(); // car, motorcycle, mobile, personal
            $table->integer('loan_term')->nullable(); // months
            $table->decimal('interest_rate', 5, 4)->nullable();
            $table->enum('status', ['submitted', 'approved', 'rejected', 'disbursed'])->default('submitted');
            
            // Important dates
            $table->datetime('submitted_at')->nullable();
            $table->datetime('approved_at')->nullable();
            $table->datetime('rejected_at')->nullable();
            $table->datetime('disbursed_at')->nullable();
            
            // Financial details
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('disbursed_amount', 12, 2)->nullable();
            
            // Additional info
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('documents')->nullable(); // Uploaded document paths
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['case_number']);
            $table->index(['submitted_at']);
            $table->index(['approved_at']);
            $table->index(['disbursed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_cases');
    }
};