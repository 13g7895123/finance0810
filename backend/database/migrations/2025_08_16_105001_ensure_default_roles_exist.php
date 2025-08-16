<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure default roles exist
        $roles = [
            [
                'name' => 'admin',
                'guard_name' => 'api',
                'display_name' => '經銷商/公司高層',
                'description' => '系統管理員，擁有所有權限',
                'is_system_role' => true,
            ],
            [
                'name' => 'executive',
                'guard_name' => 'api',
                'display_name' => '經銷商/公司高層',
                'description' => '公司高層，擁有管理員等級權限',
                'is_system_role' => true,
            ],
            [
                'name' => 'manager',
                'guard_name' => 'api',
                'display_name' => '行政人員/主管',
                'description' => '可編輯大部分資料，無法修改銀行交涉紀錄',
                'is_system_role' => true,
            ],
            [
                'name' => 'staff',
                'guard_name' => 'api',
                'display_name' => '業務人員',
                'description' => '僅能編輯查詢自己負責的客戶資料',
                'is_system_role' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['name' => $roleData['name'], 'guard_name' => $roleData['guard_name']],
                $roleData
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't delete system roles on rollback as they might be in use
        // Role::whereIn('name', ['admin', 'executive', 'manager', 'staff'])->delete();
    }
};