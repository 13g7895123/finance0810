<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Redis;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class TestController extends Controller
{
    /**
     * Comprehensive system test endpoint
     */
    public function systemTest()
    {
        $results = [
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'tests' => []
        ];

        // Test 1: Database Connection
        try {
            DB::connection()->getPdo();
            $results['tests']['database'] = [
                'status' => 'ok',
                'message' => 'Database connected successfully'
            ];
        } catch (\Exception $e) {
            $results['tests']['database'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        // Test 2: Cache System
        try {
            cache()->put('test_key', 'test_value', 60);
            $cachedValue = cache()->get('test_key');
            $results['tests']['cache'] = [
                'status' => $cachedValue === 'test_value' ? 'ok' : 'error',
                'message' => $cachedValue === 'test_value' ? 'Cache system working' : 'Cache system failed'
            ];
            cache()->forget('test_key');
        } catch (\Exception $e) {
            $results['tests']['cache'] = [
                'status' => 'warning',
                'message' => 'Cache not available: ' . $e->getMessage()
            ];
        }

        // Test 3: Users Table
        try {
            $userCount = User::count();
            $results['tests']['users'] = [
                'status' => 'ok',
                'message' => "Found {$userCount} users in database"
            ];
        } catch (\Exception $e) {
            $results['tests']['users'] = [
                'status' => 'error',
                'message' => 'Users table issue: ' . $e->getMessage()
            ];
        }

        // Test 4: Roles and Permissions
        try {
            $roleCount = Role::count();
            $permissionCount = Permission::count();
            $results['tests']['permissions'] = [
                'status' => 'ok',
                'message' => "Found {$roleCount} roles and {$permissionCount} permissions"
            ];
        } catch (\Exception $e) {
            $results['tests']['permissions'] = [
                'status' => 'error',
                'message' => 'Permission system issue: ' . $e->getMessage()
            ];
        }

        // Test 5: JWT Configuration
        try {
            $jwtSecret = config('jwt.secret');
            $results['tests']['jwt'] = [
                'status' => $jwtSecret ? 'ok' : 'error',
                'message' => $jwtSecret ? 'JWT secret configured' : 'JWT secret not configured'
            ];
        } catch (\Exception $e) {
            $results['tests']['jwt'] = [
                'status' => 'error',
                'message' => 'JWT configuration issue: ' . $e->getMessage()
            ];
        }

        // Test 6: Storage Directories
        $storagePaths = [
            'storage/app',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache'
        ];

        $storageIssues = [];
        foreach ($storagePaths as $path) {
            $fullPath = base_path($path);
            if (!is_dir($fullPath)) {
                $storageIssues[] = $path . ' does not exist';
            } elseif (!is_writable($fullPath)) {
                $storageIssues[] = $path . ' is not writable';
            }
        }

        $results['tests']['storage'] = [
            'status' => empty($storageIssues) ? 'ok' : 'error',
            'message' => empty($storageIssues) ? 'All storage directories OK' : implode(', ', $storageIssues)
        ];

        // Test 7: Environment Configuration
        $requiredEnvVars = ['APP_KEY', 'DB_CONNECTION', 'DB_DATABASE', 'JWT_SECRET'];
        $missingVars = [];
        
        foreach ($requiredEnvVars as $var) {
            if (!env($var)) {
                $missingVars[] = $var;
            }
        }

        $results['tests']['environment'] = [
            'status' => empty($missingVars) ? 'ok' : 'error',
            'message' => empty($missingVars) ? 'Environment variables configured' : 'Missing: ' . implode(', ', $missingVars)
        ];

        // Overall status
        $hasErrors = collect($results['tests'])->contains('status', 'error');
        $results['overall_status'] = $hasErrors ? 'error' : 'ok';
        $results['summary'] = $hasErrors ? 'System has issues that need attention' : 'System is ready for use';

        return response()->json($results, $hasErrors ? 500 : 200);
    }

    /**
     * Test authentication system
     */
    public function authTest()
    {
        try {
            $roles = Role::with('permissions')->get();
            $users = User::with('roles')->take(5)->get();

            return response()->json([
                'status' => 'ok',
                'auth_system' => [
                    'roles' => $roles->map(function ($role) {
                        return [
                            'name' => $role->name,
                            'display_name' => $role->display_name,
                            'permissions_count' => $role->permissions->count(),
                        ];
                    }),
                    'sample_users' => $users->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'username' => $user->username,
                            'roles' => $user->roles->pluck('name'),
                            'status' => $user->status
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quick setup check
     */
    public function setupStatus()
    {
        $checks = [
            'migrations_run' => $this->checkMigrationsRun(),
            'admin_user_exists' => $this->checkAdminUserExists(),
            'permissions_seeded' => $this->checkPermissionsSeeded(),
            'jwt_configured' => !empty(config('jwt.secret')),
        ];

        $allGood = collect($checks)->every();

        return response()->json([
            'setup_complete' => $allGood,
            'checks' => $checks,
            'next_steps' => $allGood ? [] : $this->getSetupInstructions($checks)
        ]);
    }

    private function checkMigrationsRun()
    {
        try {
            return User::count() >= 0; // If we can query users table, migrations ran
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkAdminUserExists()
    {
        try {
            return User::where('email', 'admin@finance-crm.com')->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkPermissionsSeeded()
    {
        try {
            return Role::count() > 0 && Permission::count() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getSetupInstructions($checks)
    {
        $steps = [];

        if (!$checks['jwt_configured']) {
            $steps[] = 'Set JWT_SECRET in .env file';
        }

        if (!$checks['migrations_run']) {
            $steps[] = 'Run: php artisan migrate';
        }

        if (!$checks['permissions_seeded']) {
            $steps[] = 'Run: php artisan db:seed --class=RolesAndPermissionsSeeder';
        }

        if (!$checks['admin_user_exists']) {
            $steps[] = 'Run: php artisan db:seed --class=AdminUserSeeder';
        }

        return $steps;
    }
}