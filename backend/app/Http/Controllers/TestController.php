<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Redis;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tymon\JWTAuth\Facades\JWTAuth;

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

    /**
     * Test cookie and authentication configuration
     */
    public function cookieTest(Request $request)
    {
        $isProduction = app()->environment('production');
        $domain = $isProduction ? '.mercylife.cc' : null;
        
        $cookieInfo = [
            'environment' => app()->environment(),
            'is_production' => $isProduction,
            'request_secure' => $request->secure(),
            'request_host' => $request->getHost(),
            'request_url' => $request->url(),
            'cookie_domain' => $domain,
            'sameSite' => $isProduction ? 'Lax' : 'None',
            'cors_enabled' => config('cors.supports_credentials'),
            'allowed_origins' => config('cors.allowed_origins'),
            'jwt_configured' => !empty(config('jwt.secret')),
            'jwt_ttl' => config('jwt.ttl', 60),
        ];

        // 檢查是否有 auth-token cookie
        $authToken = $request->cookie('auth-token');
        $cookieInfo['auth_cookie_present'] = !empty($authToken);
        $cookieInfo['auth_cookie_length'] = $authToken ? strlen($authToken) : 0;

        // 檢查 Authorization header
        $authHeader = $request->header('Authorization');
        $cookieInfo['auth_header_present'] = !empty($authHeader);
        
        // 檢查 JWT middleware 是否正常運作
        if ($authToken && !$authHeader) {
            $cookieInfo['jwt_middleware_status'] = 'Cookie found but not converted to header - check JWTCookieMiddleware';
        } elseif ($authToken && $authHeader) {
            $cookieInfo['jwt_middleware_status'] = 'Cookie successfully converted to Authorization header';
        } else {
            $cookieInfo['jwt_middleware_status'] = 'No authentication cookie found';
        }

        // 建議
        $recommendations = [];
        if (!$isProduction && !$request->secure()) {
            $recommendations[] = 'Development: OK to use HTTP, but HTTPS is recommended for testing production scenarios';
        }
        if ($isProduction && !$request->secure()) {
            $recommendations[] = 'Production: HTTPS is required for secure cookies';
        }
        if (!in_array($request->header('Origin', 'unknown'), config('cors.allowed_origins', []))) {
            $recommendations[] = 'Current origin may not be in CORS allowed_origins list';
        }

        $cookieInfo['recommendations'] = $recommendations;
        $cookieInfo['debug_note'] = 'This endpoint helps debug authentication issues. Check cookie settings and CORS configuration.';

        return response()->json($cookieInfo);
    }

    /**
     * 詳細的權限除錯端點 - 顯示每個步驟的權限檢查過程
     */
    public function detailedAuthDebug(Request $request)
    {
        $debug = [
            'timestamp' => now()->toISOString(),
            'request_info' => [
                'url' => $request->url(),
                'method' => $request->method(),
                'origin' => $request->header('Origin'),
                'user_agent' => $request->header('User-Agent'),
                'ip' => $request->ip()
            ],
            'step1_cookie_check' => [],
            'step2_jwt_parsing' => [],
            'step3_user_lookup' => [],
            'step4_permission_check' => [],
            'final_result' => []
        ];

        // Step 1: Cookie檢查
        $authToken = $request->cookie('auth-token');
        $debug['step1_cookie_check'] = [
            'has_auth_cookie' => !empty($authToken),
            'cookie_length' => $authToken ? strlen($authToken) : 0,
            'cookie_preview' => $authToken ? substr($authToken, 0, 20) . '...' : null,
            'status' => $authToken ? 'FOUND' : 'NOT_FOUND'
        ];

        // Step 2: JWT解析
        if ($authToken) {
            try {
                // 設置 token
                JWTAuth::setToken($authToken);
                $payload = JWTAuth::getPayload();
                $user = JWTAuth::toUser();
                
                $debug['step2_jwt_parsing'] = [
                    'jwt_valid' => true,
                    'token_payload' => $payload ? $payload->toArray() : null,
                    'user_id_from_token' => $user ? $user->id : null,
                    'status' => 'SUCCESS'
                ];

                // Step 3: 用戶查詢
                if ($user) {
                    $debug['step3_user_lookup'] = [
                        'user_exists' => true,
                        'user_id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'status' => $user->status,
                        'is_active' => $user->status === 'active',
                        'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
                        'last_login' => $user->last_login_at ? $user->last_login_at->toISOString() : null
                    ];

                    // Step 4: 權限檢查
                    try {
                        $roles = $user->getRoleNames()->toArray();
                        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
                        
                        $debug['step4_permission_check'] = [
                            'guard_name' => $user->guard_name ?? 'api',
                            'roles' => $roles,
                            'roles_count' => count($roles),
                            'total_permissions' => $permissions,
                            'permissions_count' => count($permissions),
                            'is_admin' => in_array('admin', $roles),
                            'is_executive' => in_array('executive', $roles),
                            'is_manager' => in_array('manager', $roles),
                            'has_user_management' => in_array('users.index', $permissions) || in_array('admin', $roles),
                            'check_methods' => [
                                'hasRole_admin' => $user->hasRole('admin'),
                                'hasRole_executive' => $user->hasRole('executive'),
                                'hasRole_manager' => $user->hasRole('manager'),
                                'hasPermissionTo_users_index' => $user->hasPermissionTo('users.index')
                            ]
                        ];

                        // Final Result
                        $canAccess = $user->hasRole('admin') || $user->hasRole('executive') || $user->hasRole('manager') || $user->hasPermissionTo('users.index');
                        $debug['final_result'] = [
                            'authentication_status' => 'AUTHENTICATED',
                            'authorization_status' => 'PERMISSIONS_CHECKED',
                            'can_access_users_endpoint' => $canAccess,
                            'access_reason' => $user->hasRole('admin') ? 'admin_role' : 
                                            ($user->hasRole('executive') ? 'executive_role' :
                                            ($user->hasRole('manager') ? 'manager_role' :
                                            ($user->hasPermissionTo('users.index') ? 'users_index_permission' : 'no_access')))
                        ];
                    } catch (\Exception $e) {
                        $debug['step4_permission_check'] = [
                            'error' => 'Permission check failed: ' . $e->getMessage(),
                            'status' => 'PERMISSION_CHECK_ERROR'
                        ];
                    }
                } else {
                    $debug['step3_user_lookup'] = [
                        'user_exists' => false,
                        'status' => 'USER_NOT_FOUND'
                    ];
                    $debug['final_result']['authentication_status'] = 'TOKEN_VALID_BUT_USER_NOT_FOUND';
                }
            } catch (\Exception $e) {
                $debug['step2_jwt_parsing'] = [
                    'jwt_valid' => false,
                    'error' => $e->getMessage(),
                    'error_type' => get_class($e),
                    'status' => 'JWT_PARSE_ERROR'
                ];
                $debug['final_result']['authentication_status'] = 'TOKEN_INVALID';
            }
        } else {
            $debug['step2_jwt_parsing']['status'] = 'SKIPPED_NO_TOKEN';
            $debug['step3_user_lookup']['status'] = 'SKIPPED_NO_TOKEN';
            $debug['step4_permission_check']['status'] = 'SKIPPED_NO_TOKEN';
            $debug['final_result']['authentication_status'] = 'NOT_AUTHENTICATED';
        }

        // 新增系統狀態檢查
        $debug['system_status'] = [
            'roles_count' => Role::count(),
            'permissions_count' => Permission::count(),
            'users_count' => User::count(),
            'admin_users_count' => User::role('admin')->count(),
            'api_guard_roles' => Role::where('guard_name', 'api')->pluck('name')->toArray(),
            'web_guard_roles' => Role::where('guard_name', 'web')->pluck('name')->toArray(),
        ];

        return response()->json($debug);
    }

    /**
     * 簡單的除錯測試端點
     */
    public function simpleDebug(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Debug endpoint is working',
            'timestamp' => now()->toISOString(),
            'request_url' => $request->url(),
            'has_auth_cookie' => $request->hasCookie('auth-token'),
            'cookie_length' => $request->hasCookie('auth-token') ? strlen($request->cookie('auth-token')) : 0,
            'environment' => app()->environment(),
        ]);
    }

    /**
     * Test customers basic functionality without middleware
     */
    public function testCustomersBasic(Request $request)
    {
        try {
            $debug = [
                'status' => 'testing',
                'timestamp' => now()->toISOString(),
                'tests' => []
            ];

            // Test 1: Customer model access
            try {
                $customerCount = \App\Models\Customer::count();
                $debug['tests']['customer_model'] = [
                    'status' => 'ok',
                    'count' => $customerCount,
                    'message' => 'Customer model accessible'
                ];
            } catch (\Exception $e) {
                $debug['tests']['customer_model'] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'message' => 'Customer model failed'
                ];
            }

            // Test 2: Customer table structure
            try {
                $columns = \Schema::getColumnListing('customers');
                $debug['tests']['customer_table'] = [
                    'status' => 'ok',
                    'columns' => $columns,
                    'message' => 'Customer table structure accessible'
                ];
            } catch (\Exception $e) {
                $debug['tests']['customer_table'] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'message' => 'Customer table structure failed'
                ];
            }

            // Test 3: Customer with relationships
            try {
                $customer = \App\Models\Customer::with(['assignedUser', 'creator'])->first();
                $debug['tests']['customer_relationships'] = [
                    'status' => 'ok',
                    'has_data' => !is_null($customer),
                    'sample_customer' => $customer ? [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'assigned_user' => $customer->assignedUser ? $customer->assignedUser->name : null,
                        'creator' => $customer->creator ? $customer->creator->name : null
                    ] : null,
                    'message' => 'Customer relationships accessible'
                ];
            } catch (\Exception $e) {
                $debug['tests']['customer_relationships'] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'message' => 'Customer relationships failed'
                ];
            }

            // Test 4: User model and roles
            try {
                $userCount = \App\Models\User::count();
                $roleCount = \Spatie\Permission\Models\Role::count();
                $debug['tests']['user_roles'] = [
                    'status' => 'ok',
                    'user_count' => $userCount,
                    'role_count' => $roleCount,
                    'message' => 'User and role models accessible'
                ];
            } catch (\Exception $e) {
                $debug['tests']['user_roles'] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'message' => 'User and role models failed'
                ];
            }

            // Test 5: Basic customer query similar to controller
            try {
                $customers = \App\Models\Customer::with(['assignedUser', 'creator'])
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get();
                
                $debug['tests']['customer_query'] = [
                    'status' => 'ok',
                    'count' => $customers->count(),
                    'sample_data' => $customers->map(function($customer) {
                        return [
                            'id' => $customer->id,
                            'name' => $customer->name,
                            'phone' => $customer->phone,
                            'status' => $customer->status,
                            'assigned_to' => $customer->assigned_to,
                            'assigned_user_name' => $customer->assignedUser ? $customer->assignedUser->name : null
                        ];
                    })->toArray(),
                    'message' => 'Customer query successful'
                ];
            } catch (\Exception $e) {
                $debug['tests']['customer_query'] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'message' => 'Customer query failed'
                ];
            }

            $debug['status'] = 'completed';
            return response()->json($debug);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message' => 'Test endpoint failed'
            ], 500);
        }
    }
}