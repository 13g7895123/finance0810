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
        Schema::create('webhook_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->string('execution_id')->unique();
            $table->string('webhook_type')->default('line');
            $table->string('request_method')->nullable();
            $table->text('request_url')->nullable();
            $table->json('request_headers')->nullable();
            $table->longText('request_body')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('status', ['started', 'processing', 'completed', 'failed'])->default('started');
            $table->integer('events_count')->default(0);
            $table->json('events_data')->nullable();
            $table->json('execution_steps')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->json('results')->nullable();
            $table->timestamps();
            
            $table->index('webhook_type');
            $table->index('status');
            $table->index('started_at');
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_execution_logs');
    }
};