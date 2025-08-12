<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 50); // wp_form, phone_call, line, email
            $table->string('source')->nullable(); // e.g., page URL or website name
            $table->string('name')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('line_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('is_suspected_blacklist')->default(false);
            $table->string('suspected_reason')->nullable();
            $table->timestamps();
            
            $table->index(['channel', 'created_at']);
            $table->index(['phone']);
            $table->index(['email']);
            $table->index(['line_id']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_leads');
    }
};
