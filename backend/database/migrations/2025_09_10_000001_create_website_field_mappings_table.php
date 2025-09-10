<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point 61: 建立WordPress網站表單欄位對應表
 * 
 * 支援每個網站自訂表單欄位與系統標準欄位的對應關係
 * 解決不同WordPress網站表單欄位名稱不一致的問題
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('website_field_mappings', function (Blueprint $table) {
            $table->id();
            
            // 關聯的網站
            $table->unsignedBigInteger('website_id');
            
            // 系統標準欄位名稱（如：name, phone, email, line_id等）
            $table->string('system_field', 50)->comment('系統標準欄位名稱');
            
            // WordPress表單中的實際欄位名稱
            $table->string('wp_field_name', 100)->comment('WordPress表單欄位名稱');
            
            // 欄位顯示名稱（用於前端顯示）
            $table->string('display_name', 100)->comment('欄位顯示名稱');
            
            // 是否為必填欄位
            $table->boolean('is_required')->default(false)->comment('是否為必填欄位');
            
            // 欄位類型（text, email, phone, number, date等）
            $table->enum('field_type', [
                'text', 'email', 'phone', 'number', 'date', 
                'time', 'datetime', 'url', 'select', 'textarea'
            ])->default('text')->comment('欄位資料類型');
            
            // 資料驗證規則（JSON格式）
            $table->json('validation_rules')->nullable()->comment('欄位驗證規則');
            
            // 資料轉換規則（JSON格式）
            $table->json('transform_rules')->nullable()->comment('資料轉換規則');
            
            // 預設值
            $table->string('default_value')->nullable()->comment('欄位預設值');
            
            // 說明文字
            $table->text('description')->nullable()->comment('欄位說明');
            
            // 排序順序
            $table->integer('sort_order')->default(0)->comment('排序順序');
            
            // 是否啟用
            $table->boolean('is_active')->default(true)->comment('是否啟用');
            
            $table->timestamps();
            
            // 索引
            $table->index(['website_id', 'system_field']);
            $table->index(['website_id', 'wp_field_name']);
            $table->index(['website_id', 'is_active']);
            $table->unique(['website_id', 'system_field'], 'unique_website_system_field');
            
            // 外鍵約束
            $table->foreign('website_id')->references('id')->on('websites')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_field_mappings');
    }
};