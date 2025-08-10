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
        $this->middleware('role:admin|manager');
    }

    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
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

        return response()->json($users);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|email|max:100|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|exists:roles,name',
            'status' => 'sometimes|in:active,inactive,suspended',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => $request->status ?? 'active',
            'password_changed_at' => now(),
        ]);

        $user->assignRole($request->role);

        return response()->json([
            'message' => '使用者建立成功',
            'user' => $user->load('roles')
        ], 201);
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