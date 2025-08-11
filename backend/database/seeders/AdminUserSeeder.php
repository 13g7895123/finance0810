<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cache and ensure roles are available
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        // Wait a moment to ensure roles are properly cached
        sleep(1);
        
        // Create admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@finance-crm.com'],
            [
                'name' => '系統管理員',
                'username' => 'admin',
                'password' => Hash::make('admin123'),
                'status' => 'active',
                'password_changed_at' => now(),
            ]
        );
        
        // Try to find admin role with explicit guard
        $adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')
            ->where('guard_name', 'api')
            ->first();
            
        if ($adminRole) {
            // Remove any existing roles first
            $admin->roles()->detach();
            $admin->assignRole('admin');
            $this->command->info('Admin role assigned to admin user.');
        } else {
            $this->command->warn('Admin role not found. Creating it now...');
            // Create the admin role if it doesn't exist
            $adminRole = \Spatie\Permission\Models\Role::create([
                'name' => 'admin',
                'guard_name' => 'api',
                'display_name' => '經銷商/公司高層',
                'description' => '系統管理員，擁有所有權限',
            ]);
            $admin->assignRole('admin');
            $this->command->info('Admin role created and assigned.');
        }

        // Create executive user
        $executive = User::firstOrCreate(
            ['email' => 'executive@finance-crm.com'],
            [
                'name' => '公司高層',
                'username' => 'executive',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'password_changed_at' => now(),
            ]
        );
        
        $executiveRole = \Spatie\Permission\Models\Role::where('name', 'executive')
            ->where('guard_name', 'api')
            ->first();
        if ($executiveRole) {
            $executive->roles()->detach();
            $executive->assignRole('executive');
            $this->command->info('Executive role assigned to executive user.');
        } else {
            $this->command->warn('Executive role not found. Creating it now...');
            $executiveRole = \Spatie\Permission\Models\Role::create([
                'name' => 'executive',
                'guard_name' => 'api',
                'display_name' => '經銷商/公司高層',
                'description' => '公司高層，擁有管理員等級權限',
            ]);
            $executive->assignRole('executive');
            $this->command->info('Executive role created and assigned.');
        }

        // Create manager user
        $manager = User::firstOrCreate(
            ['email' => 'manager@finance-crm.com'],
            [
                'name' => '部門主管',
                'username' => 'manager',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'password_changed_at' => now(),
            ]
        );
        
        $managerRole = \Spatie\Permission\Models\Role::where('name', 'manager')
            ->where('guard_name', 'api')
            ->first();
        if ($managerRole) {
            $manager->roles()->detach();
            $manager->assignRole('manager');
            $this->command->info('Manager role assigned to manager user.');
        } else {
            $this->command->warn('Manager role not found. Creating it now...');
            $managerRole = \Spatie\Permission\Models\Role::create([
                'name' => 'manager',
                'guard_name' => 'api',
                'display_name' => '行政人員/主管',
                'description' => '可編輯大部分資料，無法修改銀行交涉紀錄',
            ]);
            $manager->assignRole('manager');
            $this->command->info('Manager role created and assigned.');
        }

        // Create staff user
        $staff = User::firstOrCreate(
            ['email' => 'staff@finance-crm.com'],
            [
                'name' => '業務人員',
                'username' => 'staff',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'password_changed_at' => now(),
            ]
        );
        
        $staffRole = \Spatie\Permission\Models\Role::where('name', 'staff')
            ->where('guard_name', 'api')
            ->first();
        if ($staffRole) {
            $staff->roles()->detach();
            $staff->assignRole('staff');
            $this->command->info('Staff role assigned to staff user.');
        } else {
            $this->command->warn('Staff role not found. Creating it now...');
            $staffRole = \Spatie\Permission\Models\Role::create([
                'name' => 'staff',
                'guard_name' => 'api',
                'display_name' => '業務人員',
                'description' => '僅能編輯查詢自己負責的客戶資料',
            ]);
            $staff->assignRole('staff');
            $this->command->info('Staff role created and assigned.');
        }

        $this->command->info('Default users created successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('Admin: admin@finance-crm.com / admin123');
        $this->command->info('Executive: executive@finance-crm.com / password123');
        $this->command->info('Manager: manager@finance-crm.com / password123');
        $this->command->info('Staff: staff@finance-crm.com / password123');
        $this->command->warn('Please change default passwords in production!');
    }
}