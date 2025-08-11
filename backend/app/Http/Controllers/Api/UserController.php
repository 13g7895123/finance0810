<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        // 修正中間件配置：新增executive角色，並改為使用我們自定義的角色中間件
        $this->middleware('role:admin|executive|manager');
    }

    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        try {
            // 新增詳細的權限除錯日誌
            $currentUser = auth('api')->user();
            
            \Log::info('UserController@index - 詳細權限檢查開始', [
                'current_user_id' => $currentUser ? $currentUser->id : null,
                'current_user_username' => $currentUser ? $currentUser->username : null,
                'request_search' => $request->get('search', ''),
                'request_ip' => $request->ip(),
                'request_origin' => $request->header('Origin'),
                'auth_header_present' => $request->hasHeader('Authorization'),
                'cookie_present' => $request->hasCookie('auth-token')
            ]);

            if ($currentUser) {
                $roles = $currentUser->getRoleNames()->toArray();
                $permissions = $currentUser->getAllPermissions()->pluck('name')->toArray();
                
                \Log::info('UserController@index - 當前用戶權限詳情', [
                    'user_id' => $currentUser->id,
                    'username' => $currentUser->username,
                    'guard_name' => $currentUser->guard_name ?? 'unknown',
                    'roles' => $roles,
                    'roles_count' => count($roles),
                    'permissions' => $permissions,
                    'permissions_count' => count($permissions),
                    'is_admin_role' => in_array('admin', $roles),
                    'is_manager_role' => in_array('manager', $roles),
                    'is_executive_role' => in_array('executive', $roles),
                    'hasRole_admin' => $currentUser->hasRole('admin'),
                    'hasRole_manager' => $currentUser->hasRole('manager'),
                    'hasRole_executive' => $currentUser->hasRole('executive'),
                ]);

                // 記錄middleware是否已經通過權限檢查
                \Log::info('UserController@index - Middleware權限檢查已通過，開始獲取用戶列表');
            }

            $query = User::with('roles');

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->has('role')) {
                $query->whereHas('roles', function($q) use ($request) {
                    $q->where('name', $request->role);
                });
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $users = $query->orderBy('created_at', 'desc')->paginate(15);

            \Log::info('UserController@index - 成功返回用戶列表', [
                'total_users' => $users->total(),
                'returned_users' => $users->count(),
                'current_page' => $users->currentPage()
            ]);

            return response()->json($users);
        } catch (\Exception $e) {
            \Log::error('UserController@index - 系統錯誤', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '獲取用戶列表失敗',
                'error' => $e->getMessage(),
                'debug_info' => [
                    'error_type' => get_class($e),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        try {
            \Log::info('UserController@store - 開始建立使用者', [
                'request_data' => $request->all()
            ]);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'username' => 'required|string|max:50|unique:users',
                'email' => 'required|email|max:100|unique:users',
                'password' => 'required|string|min:6|confirmed',
                'role' => 'required|exists:roles,name',
                'status' => 'sometimes|in:active,inactive,suspended',
            ]);

            if ($validator->fails()) {
                \Log::warning('UserController@store - 驗證失敗', [
                    'errors' => $validator->errors()->toArray()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => '表單驗證失敗',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => $request->status ?? 'active',
                'password_changed_at' => now(),
            ]);

            \Log::info('UserController@store - 使用者已建立', [
                'user_id' => $user->id,
                'username' => $user->username
            ]);

            $user->assignRole($request->role);

            \Log::info('UserController@store - 角色已指派', [
                'user_id' => $user->id,
                'role' => $request->role
            ]);

            return response()->json([
                'success' => true,
                'message' => '使用者建立成功',
                'user' => $user->load('roles')
            ], 201);

        } catch (\Exception $e) {
            \Log::error('UserController@store - 建立使用者失敗', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_data' => $request->all(),
                'error_trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '建立使用者時發生錯誤',
                'error' => $e->getMessage(),
                'debug_info' => [
                    'error_type' => get_class($e),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        return response()->json([
            'user' => $user->load(['roles', 'permissions'])
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'username' => 'sometimes|string|max:50|unique:users,username,' . $user->id,
            'email' => 'sometimes|email|max:100|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6|confirmed',
            'status' => 'sometimes|in:active,inactive,suspended',
            'avatar' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = $validator->validated();

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
            $updateData['password_changed_at'] = now();
        } else {
            unset($updateData['password']);
        }

        $user->update($updateData);

        return response()->json([
            'message' => '使用者資料已更新',
            'user' => $user->load('roles')
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        // Cannot delete admin users or self
        if ($user->hasRole('admin') || $user->id === auth()->id()) {
            return response()->json(['error' => '無法刪除此使用者'], 403);
        }

        $user->delete();

        return response()->json(['message' => '使用者已刪除']);
    }

    /**
     * Assign role to user.
     */
    public function assignRole(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Remove all existing roles and assign new one
        $user->syncRoles([$request->role]);

        return response()->json([
            'message' => '角色指派成功',
            'user' => $user->load('roles')
        ]);
    }

    /**
     * Remove role from user.
     */
    public function removeRole(Request $request, User $user, Role $role)
    {
        if (!$user->hasRole($role->name)) {
            return response()->json(['error' => '使用者沒有此角色'], 404);
        }

        $user->removeRole($role);

        return response()->json([
            'message' => '角色已移除',
            'user' => $user->load('roles')
        ]);
    }

    /**
     * Get available roles for assignment.
     */
    public function getRoles()
    {
        $roles = Role::all()->map(function($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->getTranslatedName(),
                'description' => $role->description,
            ];
        });

        return response()->json($roles);
    }

    /**
     * Get user statistics.
     */
    public function getStats()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'inactive_users' => User::where('status', 'inactive')->count(),
            'suspended_users' => User::where('status', 'suspended')->count(),
            'users_by_role' => User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->select('roles.name as role_name', \DB::raw('count(*) as count'))
                ->groupBy('roles.name')
                ->get()
                ->pluck('count', 'role_name'),
        ];

        return response()->json($stats);
    }
}