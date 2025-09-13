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
        Schema::create('customer_contact_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // 負責的業務人員
            
            // 預計聯絡時間
            $table->date('scheduled_date');
            $table->time('scheduled_time')->nullable();
            
            // 聯絡狀態
            $table->enum('status', [
                'scheduled',    // 已排程
                'contacted',    // 已聯絡
                'rescheduled',  // 已改約
                'missed',       // 未聯絡/錯過
                'completed'     // 已完成追蹤
            ])->default('scheduled');
            
            // 聯絡相關資訊
            $table->enum('contact_type', ['phone', 'line', 'email', 'meeting', 'other'])->default('phone');
            $table->text('notes')->nullable(); // 備註或聯絡結果
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');
            
            // 實際聯絡時間
            $table->datetime('actual_contact_at')->nullable();
            
            // 下次追蹤日期
            $table->date('follow_up_date')->nullable();
            
            // 提醒系統
            $table->boolean('reminder_sent')->default(false);
            $table->datetime('reminder_sent_at')->nullable();
            
            $table->timestamps();
            
            // 索引
            $table->index(['user_id', 'scheduled_date']);
            $table->index(['customer_id', 'status']);
            $table->index(['scheduled_date', 'status']);
            $table->index(['status', 'reminder_sent']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_contact_schedules');
    }
};