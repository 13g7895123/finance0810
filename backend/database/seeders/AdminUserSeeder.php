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
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@finance-crm.com'],
            [
                'name' => '系統管理員',
                'username' => 'admin',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'password_changed_at' => now(),
            ]
        );
        
        // Ensure the admin role exists before assigning
        $adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
        if ($adminRole) {
            $admin->assignRole('admin');
        } else {
            $this->command->error('Admin role not found. Please run RolesAndPermissionsSeeder first.');
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
        
        $executiveRole = \Spatie\Permission\Models\Role::where('name', 'executive')->first();
        if ($executiveRole) {
            $executive->assignRole('executive');
        } else {
            $this->command->error('Executive role not found.');
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
        
        $managerRole = \Spatie\Permission\Models\Role::where('name', 'manager')->first();
        if ($managerRole) {
            $manager->assignRole('manager');
        } else {
            $this->command->error('Manager role not found.');
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
        
        $staffRole = \Spatie\Permission\Models\Role::where('name', 'staff')->first();
        if ($staffRole) {
            $staff->assignRole('staff');
        } else {
            $this->command->error('Staff role not found.');
        }

        $this->command->info('Default users created successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('Admin: admin@finance-crm.com / password123');
        $this->command->info('Executive: executive@finance-crm.com / password123');
        $this->command->info('Manager: manager@finance-crm.com / password123');
        $this->command->info('Staff: staff@finance-crm.com / password123');
        $this->command->warn('Please change default passwords in production!');
    }
}