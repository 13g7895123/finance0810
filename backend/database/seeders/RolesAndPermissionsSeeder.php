<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Permission as CustomPermission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions based on the system requirements
        $permissions = CustomPermission::getPermissionsByCategory();

        foreach ($permissions as $category => $categoryPermissions) {
            foreach ($categoryPermissions as $permissionName => $displayName) {
                // 創建 web guard 權限
                Permission::firstOrCreate(
                    ['name' => $permissionName, 'guard_name' => 'web'],
                    [
                        'display_name' => $displayName,
                        'category' => $category,
                        'description' => $displayName,
                    ]
                );
            }
        }

        // Create roles
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            [
                'display_name' => '經銷商/公司高層',
                'description' => '系統管理員，擁有所有權限',
                'is_system_role' => true,
            ]
        );

        $executiveRole = Role::firstOrCreate(
            ['name' => 'executive', 'guard_name' => 'web'],
            [
                'display_name' => '經銷商/公司高層',
                'description' => '公司高層，擁有管理員等級權限',
                'is_system_role' => true,
            ]
        );

        $managerRole = Role::firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'web'],
            [
                'display_name' => '行政人員/主管',
                'description' => '可編輯大部分資料，無法修改銀行交涉紀錄',
                'is_system_role' => true,
            ]
        );

        $staffRole = Role::firstOrCreate(
            ['name' => 'staff', 'guard_name' => 'web'],
            [
                'display_name' => '業務人員',
                'description' => '僅能編輯查詢自己負責的客戶資料',
                'is_system_role' => true,
            ]
        );

        // Assign permissions to roles

        // Admin and Executive - All permissions
        $allPermissions = Permission::all();
        $adminRole->syncPermissions($allPermissions);
        $executiveRole->syncPermissions($allPermissions);

        // Manager - Most permissions except bank record modifications
        $managerPermissions = Permission::where('name', 'NOT LIKE', 'bank.edit')
            ->where('name', 'NOT LIKE', 'bank.delete')
            ->get();
        $managerRole->syncPermissions($managerPermissions);

        // Staff - Limited permissions (only their own customers)
        $staffPermissions = [
            // Customer permissions (limited to own customers via middleware)
            'customer.view',
            'customer.create',
            'customer.edit',
            'customer.track',
            'customer.status',
            
            // Case permissions (limited to own customers)
            'case.view',
            'case.create',
            'case.edit',
            'case.submit',
            
            // Bank records (view only, no edit/delete)
            'bank.view',
            
            // Chat permissions
            'chat.view',
            'chat.reply',
            
            // Basic reporting (limited scope)
            'report.daily',
        ];

        $staffPermissionModels = Permission::whereIn('name', $staffPermissions)->get();
        $staffRole->syncPermissions($staffPermissionModels);

        $this->command->info('Roles and permissions created successfully!');
        $this->command->info('Created roles:');
        $this->command->info('- admin (經銷商/公司高層): ' . $adminRole->permissions->count() . ' permissions');
        $this->command->info('- executive (經銷商/公司高層): ' . $executiveRole->permissions->count() . ' permissions');
        $this->command->info('- manager (行政人員/主管): ' . $managerRole->permissions->count() . ' permissions');
        $this->command->info('- staff (業務人員): ' . $staffRole->permissions->count() . ' permissions');
    }
}