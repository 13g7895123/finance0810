<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Point 1: 新增案件編輯頁面所需的詳細欄位到 customer_leads 表格
     * 將欄位分為四個區塊：個人資料、聯絡資訊、公司資料、緊急聯絡人
     */
    public function up(): void
    {
        Schema::table('customer_leads', function (Blueprint $table) {
            // ====================================================
            // 個人資料 (Personal Information)
            // ====================================================

            // 出生年月日
            if (!Schema::hasColumn('customer_leads', 'birth_date')) {
                $table->date('birth_date')->nullable()->comment('出生年月日');
            }

            // 身份證字號
            if (!Schema::hasColumn('customer_leads', 'id_number')) {
                $table->string('id_number', 20)->nullable()->comment('身份證字號');
            }

            // 最高學歷
            if (!Schema::hasColumn('customer_leads', 'education_level')) {
                $table->string('education_level', 50)->nullable()->comment('最高學歷');
            }

            // ====================================================
            // 聯絡資訊 (Contact Information)
            // ====================================================

            // 可聯繫時間
            if (!Schema::hasColumn('customer_leads', 'contact_time')) {
                $table->string('contact_time', 100)->nullable()->comment('可聯繫時間');
            }

            // 戶籍地址
            if (!Schema::hasColumn('customer_leads', 'registered_address')) {
                $table->text('registered_address')->nullable()->comment('戶籍地址');
            }

            // 室內電話
            if (!Schema::hasColumn('customer_leads', 'home_phone')) {
                $table->string('home_phone', 20)->nullable()->comment('室內電話');
            }

            // 通訊地址是否同戶籍地
            if (!Schema::hasColumn('customer_leads', 'mailing_same_as_registered')) {
                $table->boolean('mailing_same_as_registered')->default(false)->comment('通訊地址是否同戶籍地');
            }

            // 通訊地址
            if (!Schema::hasColumn('customer_leads', 'mailing_address')) {
                $table->text('mailing_address')->nullable()->comment('通訊地址');
            }

            // 通訊電話
            if (!Schema::hasColumn('customer_leads', 'mailing_phone')) {
                $table->string('mailing_phone', 20)->nullable()->comment('通訊電話');
            }

            // 現居地住多久
            if (!Schema::hasColumn('customer_leads', 'residence_duration')) {
                $table->string('residence_duration', 50)->nullable()->comment('現居地住多久');
            }

            // 居住地持有人
            if (!Schema::hasColumn('customer_leads', 'residence_owner')) {
                $table->string('residence_owner', 100)->nullable()->comment('居住地持有人');
            }

            // 電信業者
            if (!Schema::hasColumn('customer_leads', 'telecom_provider')) {
                $table->string('telecom_provider', 50)->nullable()->comment('電信業者');
            }

            // ====================================================
            // 公司資料 (Company Information)
            // ====================================================

            // 公司名稱
            if (!Schema::hasColumn('customer_leads', 'company_name')) {
                $table->string('company_name', 200)->nullable()->comment('公司名稱');
            }

            // 公司電話
            if (!Schema::hasColumn('customer_leads', 'company_phone')) {
                $table->string('company_phone', 20)->nullable()->comment('公司電話');
            }

            // 公司地址
            if (!Schema::hasColumn('customer_leads', 'company_address')) {
                $table->text('company_address')->nullable()->comment('公司地址');
            }

            // 職稱
            if (!Schema::hasColumn('customer_leads', 'job_title')) {
                $table->string('job_title', 100)->nullable()->comment('職稱');
            }

            // 月收入
            if (!Schema::hasColumn('customer_leads', 'monthly_income')) {
                $table->decimal('monthly_income', 12, 2)->nullable()->comment('月收入');
            }

            // 有無新轉勞保
            if (!Schema::hasColumn('customer_leads', 'labor_insurance_transfer')) {
                $table->boolean('labor_insurance_transfer')->nullable()->comment('有無新轉勞保');
            }

            // 目前公司在職多久
            if (!Schema::hasColumn('customer_leads', 'current_job_duration')) {
                $table->string('current_job_duration', 50)->nullable()->comment('目前公司在職多久');
            }

            // ====================================================
            // 緊急聯絡人 (Emergency Contacts)
            // ====================================================

            // 聯絡人①
            if (!Schema::hasColumn('customer_leads', 'emergency_contact_1_name')) {
                $table->string('emergency_contact_1_name', 100)->nullable()->comment('聯絡人①姓名');
            }

            if (!Schema::hasColumn('customer_leads', 'emergency_contact_1_relationship')) {
                $table->string('emergency_contact_1_relationship', 50)->nullable()->comment('聯絡人①關係');
            }

            if (!Schema::hasColumn('customer_leads', 'emergency_contact_1_phone')) {
                $table->string('emergency_contact_1_phone', 20)->nullable()->comment('聯絡人①電話');
            }

            if (!Schema::hasColumn('customer_leads', 'emergency_contact_1_available_time')) {
                $table->string('emergency_contact_1_available_time', 100)->nullable()->comment('聯絡人①方便聯絡時間');
            }

            if (!Schema::hasColumn('customer_leads', 'emergency_contact_1_confidential')) {
                $table->boolean('emergency_contact_1_confidential')->default(false)->comment('聯絡人①是否保密');
            }

            // 聯絡人②
            if (!Schema::hasColumn('customer_leads', 'emergency_contact_2_name')) {
                $table->string('emergency_contact_2_name', 100)->nullable()->comment('聯絡人②姓名');
            }

            if (!Schema::hasColumn('customer_leads', 'emergency_contact_2_relationship')) {
                $table->string('emergency_contact_2_relationship', 50)->nullable()->comment('聯絡人②關係');
            }

            if (!Schema::hasColumn('customer_leads', 'emergency_contact_2_phone')) {
                $table->string('emergency_contact_2_phone', 20)->nullable()->comment('聯絡人②電話');
            }

            if (!Schema::hasColumn('customer_leads', 'emergency_contact_2_available_time')) {
                $table->string('emergency_contact_2_available_time', 100)->nullable()->comment('聯絡人②方便聯絡時間');
            }

            if (!Schema::hasColumn('customer_leads', 'emergency_contact_2_confidential')) {
                $table->boolean('emergency_contact_2_confidential')->default(false)->comment('聯絡人②是否保密');
            }

            // 介紹人
            if (!Schema::hasColumn('customer_leads', 'referrer')) {
                $table->string('referrer', 100)->nullable()->comment('介紹人');
            }

            // ====================================================
            // 新增索引以提升查詢效能
            // ====================================================

            // 為常用查詢欄位建立索引
            if (!Schema::hasColumn('customer_leads', 'id_number') ||
                !$this->indexExists('customer_leads', 'customer_leads_id_number_index')) {
                $table->index('id_number', 'customer_leads_id_number_index');
            }

            if (!Schema::hasColumn('customer_leads', 'company_name') ||
                !$this->indexExists('customer_leads', 'customer_leads_company_name_index')) {
                $table->index('company_name', 'customer_leads_company_name_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_leads', function (Blueprint $table) {
            // 移除新增的欄位（按照相反順序）
            $fieldsToRemove = [
                'referrer',
                'emergency_contact_2_confidential',
                'emergency_contact_2_available_time',
                'emergency_contact_2_phone',
                'emergency_contact_2_relationship',
                'emergency_contact_2_name',
                'emergency_contact_1_confidential',
                'emergency_contact_1_available_time',
                'emergency_contact_1_phone',
                'emergency_contact_1_relationship',
                'emergency_contact_1_name',
                'current_job_duration',
                'labor_insurance_transfer',
                'monthly_income',
                'job_title',
                'company_address',
                'company_phone',
                'company_name',
                'telecom_provider',
                'residence_owner',
                'residence_duration',
                'mailing_phone',
                'mailing_address',
                'mailing_same_as_registered',
                'home_phone',
                'registered_address',
                'contact_time',
                'education_level',
                'id_number',
                'birth_date'
            ];

            foreach ($fieldsToRemove as $field) {
                if (Schema::hasColumn('customer_leads', $field)) {
                    $table->dropColumn($field);
                }
            }

            // 移除索引
            if ($this->indexExists('customer_leads', 'customer_leads_id_number_index')) {
                $table->dropIndex('customer_leads_id_number_index');
            }

            if ($this->indexExists('customer_leads', 'customer_leads_company_name_index')) {
                $table->dropIndex('customer_leads_company_name_index');
            }
        });
    }

    /**
     * 檢查索引是否存在
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = Schema::getConnection()->getDoctrineSchemaManager()
            ->listTableIndexes($table);

        return array_key_exists($indexName, $indexes);
    }
};