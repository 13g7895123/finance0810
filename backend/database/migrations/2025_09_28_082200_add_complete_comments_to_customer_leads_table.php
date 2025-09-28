<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Point 12: 為 customer_leads 表格補上完整的欄位註解
     * customer_leads 表格功用：存儲客戶貸款申請案件的完整資訊，包含個人資料、聯絡方式、工作收入、緊急聯絡人等
     * 是融資貸款CRM系統的核心資料表，用於管理從申請到審核的完整流程
     */
    public function up(): void
    {
        // 使用原生 SQL 來添加欄位註解，確保中文字符正確顯示
        $comments = [
            // 系統管理欄位
            'assigned_to' => '指派給的業務人員ID，關聯到 users 表格，用於案件分配管理',

            // 來源追蹤欄位
            'channel' => '進件管道 (wp_form-網站表單, lineoa-LINE官方帳號, email-電子郵件, phone-電話進件)',
            'source' => '來源網站或頁面URL，用於追蹤客戶來源和行銷成效分析',
            'line_id' => 'LINE用戶ID或LINE暱稱，用於LINE官方帳號客戶識別',
            'ip_address' => '客戶提交申請時的IP地址，用於風險控制和地區分析',
            'user_agent' => '客戶瀏覽器和設備資訊，用於技術分析和使用者體驗優化',
            'payload' => '原始提交數據的JSON格式儲存，保留完整申請表單內容',

            // 風險控制欄位
            'is_suspected_blacklist' => '是否疑似黑名單客戶標記 (0-否, 1-是)，用於風險預警',
            'suspected_reason' => '疑似黑名單的具體原因說明，協助風控判斷',

            // 聊絡資訊詳細欄位
            'contact_time' => '客戶方便聯絡的時間段 (如：平日9:00-18:00)，提升聯絡成功率',
            'registered_address' => '戶籍地址完整資訊，用於身份驗證和風險評估',
            'home_phone' => '室內電話號碼，提供多元聯絡管道',
            'mailing_same_as_registered' => '通訊地址是否與戶籍地址相同 (0-否, 1-是)，簡化資料輸入',
            'mailing_address' => '通訊地址 (當與戶籍地址不同時填寫)，確保文件寄送正確',
            'mailing_phone' => '通訊地址對應電話 (當地址不同時填寫)，聯絡資訊完整性',
            'residence_duration' => '現居地居住時間長度 (如：3年2個月)，評估居住穩定性',
            'residence_owner' => '現居地房屋持有人 (本人, 父母, 配偶, 子女, 親戚, 朋友, 租屋, 其他)，了解居住狀況',
            'telecom_provider' => '手機電信業者 (中華電信, 台灣大哥大, 遠傳電信, 亞太電信, 台灣之星, 其他)，聯絡穩定性參考',

            // 工作收入欄位
            'company_address' => '任職公司完整地址，用於工作地點驗證和穩定性評估',
            'labor_insurance_transfer' => '是否有新轉勞保記錄 (0-否, 1-是, NULL-未設定)，工作穩定性指標',
            'current_job_duration' => '目前公司工作年資 (如：2年6個月)，評估工作穩定性和還款能力',

            // 緊急聯絡人詳細資訊
            'emergency_contact_1_relationship' => '緊急聯絡人①關係 (父親, 母親, 配偶, 子女, 兄弟姐妹, 朋友, 同事, 其他)，了解社會關係',
            'emergency_contact_1_available_time' => '緊急聯絡人①方便聯絡時間，提升聯絡成功率',
            'emergency_contact_1_confidential' => '緊急聯絡人①是否需要保密聯絡 (0-否, 1-是)，保護客戶隱私',
            'emergency_contact_2_relationship' => '緊急聯絡人②關係 (父親, 母親, 配偶, 子女, 兄弟姐妹, 朋友, 同事, 其他)，第二聯絡人關係',
            'emergency_contact_2_phone' => '緊急聯絡人②電話號碼，備用聯絡管道',
            'emergency_contact_2_available_time' => '緊急聯絡人②方便聯絡時間，聯絡時間規劃',
            'emergency_contact_2_confidential' => '緊急聯絡人②是否需要保密聯絡 (0-否, 1-是)，隱私保護設定',

            // 系統時間戳記
            'created_at' => '案件建立時間，記錄申請提交的精確時間',
            'updated_at' => '案件最後更新時間，追蹤資料異動歷程'
        ];

        // 逐一為每個欄位添加註解
        foreach ($comments as $column => $comment) {
            try {
                // 使用原生 SQL 確保字符集正確
                $definition = $this->getColumnDefinition($column);
                $sql = "ALTER TABLE customer_leads MODIFY COLUMN `{$column}` {$definition} COMMENT ?";
                DB::statement($sql, [$comment]);
            } catch (Exception $e) {
                // 記錄錯誤但不中斷遷移
                \Log::warning("Point 12 - 無法為欄位 {$column} 添加註解: " . $e->getMessage());
            }
        }

        \Log::info('Point 12 - customer_leads 表格完整欄位註解添加完成');
    }

    /**
     * 獲取欄位的完整定義 (根據實際表格結構)
     */
    private function getColumnDefinition(string $column): string
    {
        $definitions = [
            'assigned_to' => 'BIGINT UNSIGNED NULL',
            'channel' => 'VARCHAR(255) NULL',
            'source' => 'VARCHAR(255) NULL',
            'line_id' => 'VARCHAR(255) NULL',
            'ip_address' => 'VARCHAR(255) NULL',
            'user_agent' => 'TEXT NULL',
            'payload' => 'JSON NULL',
            'is_suspected_blacklist' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'suspected_reason' => 'TEXT NULL',
            'contact_time' => 'VARCHAR(255) NULL',
            'registered_address' => 'TEXT NULL',
            'home_phone' => 'VARCHAR(255) NULL',
            'mailing_same_as_registered' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'mailing_address' => 'TEXT NULL',
            'mailing_phone' => 'VARCHAR(255) NULL',
            'residence_duration' => 'VARCHAR(255) NULL',
            'residence_owner' => 'VARCHAR(255) NULL',
            'telecom_provider' => 'VARCHAR(255) NULL',
            'company_address' => 'TEXT NULL',
            'labor_insurance_transfer' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'current_job_duration' => 'VARCHAR(255) NULL',
            'emergency_contact_1_relationship' => 'VARCHAR(255) NULL',
            'emergency_contact_1_available_time' => 'VARCHAR(255) NULL',
            'emergency_contact_1_confidential' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'emergency_contact_2_relationship' => 'VARCHAR(255) NULL',
            'emergency_contact_2_phone' => 'VARCHAR(255) NULL',
            'emergency_contact_2_available_time' => 'VARCHAR(255) NULL',
            'emergency_contact_2_confidential' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'created_at' => 'TIMESTAMP NULL',
            'updated_at' => 'TIMESTAMP NULL'
        ];

        return $definitions[$column] ?? 'VARCHAR(255) NULL';
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 移除所有新增的註解 (設為空字串)
        $columns = [
            'assigned_to', 'channel', 'source', 'line_id', 'ip_address', 'user_agent', 'payload',
            'is_suspected_blacklist', 'suspected_reason', 'contact_time', 'registered_address', 'home_phone',
            'mailing_same_as_registered', 'mailing_address', 'mailing_phone', 'residence_duration', 'residence_owner',
            'telecom_provider', 'company_address', 'labor_insurance_transfer', 'current_job_duration',
            'emergency_contact_1_relationship', 'emergency_contact_1_available_time', 'emergency_contact_1_confidential',
            'emergency_contact_2_relationship', 'emergency_contact_2_phone', 'emergency_contact_2_available_time',
            'emergency_contact_2_confidential', 'created_at', 'updated_at'
        ];

        foreach ($columns as $column) {
            try {
                $definition = $this->getColumnDefinition($column);
                $sql = "ALTER TABLE customer_leads MODIFY COLUMN `{$column}` {$definition} COMMENT ?";
                DB::statement($sql, ['']);
            } catch (Exception $e) {
                \Log::warning("Point 12 - 無法移除欄位 {$column} 的註解: " . $e->getMessage());
            }
        }
    }
};