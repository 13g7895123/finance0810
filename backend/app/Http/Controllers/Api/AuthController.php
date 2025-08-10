<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ], [
            'username.required' => '請輸入使用者名稱或電子郵件',
            'password.required' => '請輸入密碼',
            'password.min' => '密碼至少需要6個字元',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Try to authenticate with username or email
        $credentials = $request->only(['password']);
        $user = User::where('username', $request->username)
                   ->orWhere('email', $request->username)
                   ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => '登入資訊有誤'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json(['error' => '帳號已被停用，請聯繫管理員'], 403);
        }

        // Generate JWT token
        $token = JWTAuth::fromUser($user);
        
        // Update last login
        $user->updateLastLogin($request->ip());

        return $this->respondWithToken($token, $user);
    }

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|confirmed|min:6',
        ], [
            'name.required' => '請輸入姓名',
            'username.required' => '請輸入使用者名稱',
            'username.unique' => '使用者名稱已被使用',
            'email.required' => '請輸入電子郵件',
            'email.unique' => '電子郵件已被註冊',
            'password.required' => '請輸入密碼',
            'password.confirmed' => '密碼確認不符合',
            'password.min' => '密碼至少需要6個字元',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create(array_merge(
            $validator->validated(),
            [
                'password' => Hash::make($request->password),
                'status' => 'active',
                'password_changed_at' => now(),
            ]
        ));

        // Assign default staff role
        $user->assignRole('staff');

        return response()->json([
            'message' => '註冊成功',
            'user' => $user
        ], 201);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = Auth::user();
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'status' => $user->status,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'last_login_at' => $user->last_login_at,
                'preferences' => $user->preferences,
            ]
        ]);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        JWTAuth::logout();

        return response()->json(['message' => '登出成功']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(JWTAuth::refresh(), Auth::user());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token, $user)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'status' => $user->status,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'last_login_at' => $user->last_login_at,
                'is_admin' => $user->isAdmin(),
                'is_manager' => $user->isManager(),
            ]
        ]);
    }
}