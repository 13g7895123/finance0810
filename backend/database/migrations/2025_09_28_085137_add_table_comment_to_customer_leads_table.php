<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Point 12 完善版: 為 customer_leads 表格本身添加表級註記
     * 補齊表格整體功用說明，完善資料庫文檔
     */
    public function up(): void
    {
        // 為 customer_leads 表格添加表級註記
        $tableComment = '客戶貸款申請案件主表 - 融資貸款CRM系統核心資料表，存儲從申請到審核完整流程的客戶資訊，包含個人資料、聯絡方式、工作收入、緊急聯絡人等完整案件數據，支援案件分配、風險控制、業務追蹤等核心功能';

        DB::statement("ALTER TABLE customer_leads COMMENT = '" . addslashes($tableComment) . "'");

        \Log::info('Point 12 完善版 - customer_leads 表格級註記添加完成', [
            'table' => 'customer_leads',
            'comment' => $tableComment
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 移除表格註記
        DB::statement("ALTER TABLE customer_leads COMMENT = ''");

        \Log::info('Point 12 完善版 - customer_leads 表格級註記已移除');
    }
};