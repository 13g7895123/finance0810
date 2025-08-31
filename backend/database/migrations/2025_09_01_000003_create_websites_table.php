<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point 40: Create websites table for WordPress website management
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            
            // Basic website info
            $table->string('name', 100)->comment('網站顯示名稱');
            $table->string('domain', 255)->unique()->comment('網站域名');
            $table->string('url', 500)->comment('完整網址');
            
            // Website status and settings
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active')->comment('網站狀態');
            $table->enum('type', ['wordpress', 'other'])->default('wordpress')->comment('網站類型');
            
            // Integration settings
            $table->boolean('webhook_enabled')->default(true)->comment('是否啟用webhook');
            $table->string('webhook_url', 500)->nullable()->comment('webhook網址');
            $table->text('webhook_secret')->nullable()->comment('webhook密鑰');
            
            // Analytics and tracking
            $table->integer('lead_count')->default(0)->comment('總進件數');
            $table->integer('customer_count')->default(0)->comment('總客戶數');
            $table->decimal('conversion_rate', 5, 2)->default(0)->comment('轉換率%');
            $table->timestamp('last_lead_at')->nullable()->comment('最後進件時間');
            
            // Additional settings
            $table->json('form_settings')->nullable()->comment('表單設定');
            $table->json('tracking_settings')->nullable()->comment('追蹤設定');
            $table->text('notes')->nullable()->comment('備註');
            
            // Management info
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'type']);
            $table->index(['domain', 'status']);
            $table->index(['created_at', 'status']);
            
            // Foreign keys
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};