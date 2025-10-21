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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('wp_lead'); // notification type: wp_lead, system, etc.
            $table->string('title');
            $table->text('message');
            $table->unsignedBigInteger('user_id')->nullable(); // target user, null means broadcast to all
            $table->unsignedBigInteger('lead_id')->nullable(); // related customer_lead
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->string('priority')->default('high'); // high, medium, low
            $table->json('data')->nullable(); // additional data
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'is_read']);
            $table->index(['type', 'created_at']);
            $table->index('lead_id');

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lead_id')->references('id')->on('customer_leads')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
