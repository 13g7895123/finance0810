<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Point 10: 為 customer_leads 表格添加詳細的欄位註解
     * 確保每個欄位都有清楚的說明，便於開發維護
     */
    public function up(): void
    {
        // 使用原生 SQL 來添加欄位註解，因為 Laravel Schema Builder 對註解支援有限
        $comments = [
            // 系統欄位
            'id' => '主鍵，案件唯一識別碼',
            'customer_id' => '客戶ID，關聯到 customers 表格',
            'status' => '案件狀態 (pending-待處理, approved-已核准, rejected-已拒絕, processing-處理中)',
            'case_status' => '案件處理狀態 (unassigned-未分配, valid_customer-有效客戶, invalid_customer-無效客戶, customer_service-客服處理, blacklist-黑名單, approved_disbursed-已核准撥款, conditional-有條件核准, declined-拒絕, follow_up-追蹤)',
            'assigned_to' => '指派給的用戶ID，關聯到 users 表格',

            // 來源資訊
            'channel' => '進件管道 (wp_form-網站表單, lineoa-LINE官方帳號, email-電子郵件, phone-電話)',
            'source' => '來源網站或頁面URL',
            'ip_address' => '客戶提交時的IP地址',
            'user_agent' => '客戶瀏覽器資訊',
            'payload' => '原始提交數據，JSON格式儲存',

            // 風險控制
            'is_suspected_blacklist' => '是否疑似黑名單客戶 (0-否, 1-是)',
            'suspected_reason' => '疑似黑名單的原因說明',

            // 基本聯絡資料
            'name' => '客戶姓名',
            'phone' => '手機號碼',
            'email' => '電子郵件地址',
            'line_id' => 'LINE用戶ID或LINE暱稱',

            // 個人基本資料
            'birth_date' => '出生年月日',
            'id_number' => '身份證字號',
            'education_level' => '最高學歷 (國小, 國中, 高中職, 專科, 大學, 碩士, 博士, 其他)',

            // 聯絡資訊
            'contact_time' => '方便聯絡的時間 (如：平日9:00-18:00)',
            'registered_address' => '戶籍地址',
            'home_phone' => '室內電話',
            'mailing_same_as_registered' => '通訊地址是否與戶籍地址相同 (0-否, 1-是)',
            'mailing_address' => '通訊地址 (當與戶籍地址不同時填寫)',
            'mailing_phone' => '通訊電話 (當與戶籍地址不同時填寫)',
            'residence_duration' => '現居地居住時間 (如：3年2個月)',
            'residence_owner' => '居住地持有人 (本人, 父母, 配偶, 子女, 親戚, 朋友, 租屋, 其他)',
            'telecom_provider' => '電信業者 (中華電信, 台灣大哥大, 遠傳電信, 亞太電信, 台灣之星, 其他)',

            // 工作與收入資料
            'company_name' => '任職公司名稱',
            'company_phone' => '公司電話',
            'company_address' => '公司地址',
            'job_title' => '職稱或職務',
            'monthly_income' => '月收入金額 (新台幣)',
            'labor_insurance_transfer' => '是否有新轉勞保 (0-否, 1-是, NULL-未設定)',
            'current_job_duration' => '目前公司工作年資 (如：2年6個月)',

            // 緊急聯絡人資料
            'emergency_contact_1_name' => '緊急聯絡人①姓名',
            'emergency_contact_1_relationship' => '緊急聯絡人①關係 (父親, 母親, 配偶, 子女, 兄弟姐妹, 朋友, 同事, 其他)',
            'emergency_contact_1_phone' => '緊急聯絡人①電話',
            'emergency_contact_1_available_time' => '緊急聯絡人①方便聯絡時間',
            'emergency_contact_1_confidential' => '緊急聯絡人①是否保密 (0-否, 1-是)',
            'emergency_contact_2_name' => '緊急聯絡人②姓名',
            'emergency_contact_2_relationship' => '緊急聯絡人②關係 (父親, 母親, 配偶, 子女, 兄弟姐妹, 朋友, 同事, 其他)',
            'emergency_contact_2_phone' => '緊急聯絡人②電話',
            'emergency_contact_2_available_time' => '緊急聯絡人②方便聯絡時間',
            'emergency_contact_2_confidential' => '緊急聯絡人②是否保密 (0-否, 1-是)',

            // 其他資訊
            'referrer' => '介紹人姓名',

            // 系統時間戳記
            'created_at' => '記錄建立時間',
            'updated_at' => '記錄最後更新時間'
        ];

        // 逐一為每個欄位添加註解
        foreach ($comments as $column => $comment) {
            try {
                DB::statement("ALTER TABLE customer_leads MODIFY COLUMN `{$column}` " . $this->getColumnDefinition($column) . " COMMENT '{$comment}'");
            } catch (Exception $e) {
                // 記錄錯誤但不中斷遷移
                \Log::warning("Point 10 - 無法為欄位 {$column} 添加註解: " . $e->getMessage());
            }
        }

        \Log::info('Point 10 - customer_leads 表格欄位註解添加完成');
    }

    /**
     * 獲取欄位的完整定義
     */
    private function getColumnDefinition(string $column): string
    {
        $definitions = [
            'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'customer_id' => 'BIGINT UNSIGNED NULL',
            'status' => 'VARCHAR(255) NOT NULL DEFAULT \'pending\'',
            'case_status' => 'VARCHAR(255) NULL',
            'assigned_to' => 'BIGINT UNSIGNED NULL',
            'channel' => 'VARCHAR(255) NULL',
            'source' => 'VARCHAR(255) NULL',
            'name' => 'VARCHAR(255) NULL',
            'phone' => 'VARCHAR(255) NULL',
            'email' => 'VARCHAR(255) NULL',
            'line_id' => 'VARCHAR(255) NULL',
            'ip_address' => 'VARCHAR(255) NULL',
            'user_agent' => 'TEXT NULL',
            'payload' => 'JSON NULL',
            'is_suspected_blacklist' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'suspected_reason' => 'TEXT NULL',
            'birth_date' => 'DATE NULL',
            'id_number' => 'VARCHAR(255) NULL',
            'education_level' => 'VARCHAR(255) NULL',
            'contact_time' => 'VARCHAR(255) NULL',
            'registered_address' => 'TEXT NULL',
            'home_phone' => 'VARCHAR(255) NULL',
            'mailing_same_as_registered' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'mailing_address' => 'TEXT NULL',
            'mailing_phone' => 'VARCHAR(255) NULL',
            'residence_duration' => 'VARCHAR(255) NULL',
            'residence_owner' => 'VARCHAR(255) NULL',
            'telecom_provider' => 'VARCHAR(255) NULL',
            'company_name' => 'VARCHAR(255) NULL',
            'company_phone' => 'VARCHAR(255) NULL',
            'company_address' => 'TEXT NULL',
            'job_title' => 'VARCHAR(255) NULL',
            'monthly_income' => 'DECIMAL(12,2) NULL',
            'labor_insurance_transfer' => 'TINYINT(1) NULL',
            'current_job_duration' => 'VARCHAR(255) NULL',
            'emergency_contact_1_name' => 'VARCHAR(255) NULL',
            'emergency_contact_1_relationship' => 'VARCHAR(255) NULL',
            'emergency_contact_1_phone' => 'VARCHAR(255) NULL',
            'emergency_contact_1_available_time' => 'VARCHAR(255) NULL',
            'emergency_contact_1_confidential' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'emergency_contact_2_name' => 'VARCHAR(255) NULL',
            'emergency_contact_2_relationship' => 'VARCHAR(255) NULL',
            'emergency_contact_2_phone' => 'VARCHAR(255) NULL',
            'emergency_contact_2_available_time' => 'VARCHAR(255) NULL',
            'emergency_contact_2_confidential' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'referrer' => 'VARCHAR(255) NULL',
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
        // 移除所有註解 (設為空字串)
        $columns = [
            'id', 'customer_id', 'status', 'case_status', 'assigned_to', 'channel', 'source',
            'name', 'phone', 'email', 'line_id', 'ip_address', 'user_agent', 'payload',
            'is_suspected_blacklist', 'suspected_reason', 'birth_date', 'id_number', 'education_level',
            'contact_time', 'registered_address', 'home_phone', 'mailing_same_as_registered',
            'mailing_address', 'mailing_phone', 'residence_duration', 'residence_owner', 'telecom_provider',
            'company_name', 'company_phone', 'company_address', 'job_title', 'monthly_income',
            'labor_insurance_transfer', 'current_job_duration', 'emergency_contact_1_name',
            'emergency_contact_1_relationship', 'emergency_contact_1_phone', 'emergency_contact_1_available_time',
            'emergency_contact_1_confidential', 'emergency_contact_2_name', 'emergency_contact_2_relationship',
            'emergency_contact_2_phone', 'emergency_contact_2_available_time', 'emergency_contact_2_confidential',
            'referrer', 'created_at', 'updated_at'
        ];

        foreach ($columns as $column) {
            try {
                DB::statement("ALTER TABLE customer_leads MODIFY COLUMN `{$column}` " . $this->getColumnDefinition($column) . " COMMENT ''");
            } catch (Exception $e) {
                \Log::warning("Point 10 - 無法移除欄位 {$column} 的註解: " . $e->getMessage());
            }
        }
    }
};