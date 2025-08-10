<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'category',
    ];

    /**
     * Get permission display name for frontend
     */
    public function getDisplayNameAttribute($value)
    {
        return $value ?: ucfirst(str_replace(['-', '_', '.'], ' ', $this->name));
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get permission categories with Chinese translations
     */
    public static function getCategories(): array
    {
        return [
            'customer' => '客戶管理',
            'case' => '案件管理',
            'report' => '報表統計',
            'chat' => '聊天管理',
            'user' => '用戶管理',
            'system' => '系統管理',
            'bank' => '銀行交涉',
            'finance' => '財務管理',
        ];
    }

    /**
     * Get all permissions grouped by category
     */
    public static function getPermissionsByCategory(): array
    {
        return [
            'customer' => [
                'customer.view' => '查看客戶資料',
                'customer.create' => '新增客戶資料',
                'customer.edit' => '編輯客戶資料',
                'customer.delete' => '刪除客戶資料',
                'customer.assign' => '分配客戶給業務',
                'customer.track' => '設定追蹤日期',
                'customer.status' => '更改客戶狀態',
                'customer.view-all' => '查看所有客戶（不限負責人）',
            ],
            'case' => [
                'case.view' => '查看案件資料',
                'case.create' => '新增案件',
                'case.edit' => '編輯案件資料',
                'case.delete' => '刪除案件',
                'case.submit' => '送件處理',
                'case.approve' => '案件核准',
                'case.disburse' => '撥款處理',
            ],
            'bank' => [
                'bank.view' => '查看銀行交涉紀錄',
                'bank.create' => '新增銀行交涉紀錄',
                'bank.edit' => '編輯銀行交涉紀錄',
                'bank.delete' => '刪除銀行交涉紀錄',
            ],
            'report' => [
                'report.daily' => '查看日報表',
                'report.monthly' => '查看月報表',
                'report.website' => '查看網站統計',
                'report.region' => '查看地區統計',
                'report.approval' => '查看核准率統計',
                'report.accounting' => '查看會計報表',
                'report.export' => '匯出報表',
            ],
            'chat' => [
                'chat.view' => '查看聊天記錄',
                'chat.reply' => '回覆聊天訊息',
                'chat.manage' => '管理聊天設定',
            ],
            'user' => [
                'user.view' => '查看用戶列表',
                'user.create' => '新增用戶',
                'user.edit' => '編輯用戶資料',
                'user.delete' => '刪除用戶',
                'user.roles' => '管理用戶角色',
                'user.permissions' => '管理用戶權限',
            ],
            'system' => [
                'system.settings' => '系統設定',
                'system.backup' => '系統備份',
                'system.maintenance' => '系統維護',
                'system.logs' => '查看系統日誌',
            ],
        ];
    }
}