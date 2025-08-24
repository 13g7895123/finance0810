<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class FirebaseAuthMiddleware
{
    protected $firebaseAuth;

    public function __construct(FirebaseAuth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 檢查是否啟用 Firebase 驗證
        if (!env('FIREBASE_AUTH_ENABLED', false)) {
            // 如果未啟用，直接通過
            return $next($request);
        }

        try {
            // 從 Header 或 Cookie 中獲取 Firebase ID Token
            $idToken = $this->extractIdToken($request);
            
            if (!$idToken) {
                return $this->unauthorizedResponse('Missing Firebase ID token');
            }

            // 驗證 Firebase ID Token
            $verifiedIdToken = $this->firebaseAuth->verifyIdToken($idToken);
            $uid = $verifiedIdToken->claims()->get('sub');

            // 將 Firebase 用戶資訊添加到請求中
            $request->merge([
                'firebase_uid' => $uid,
                'firebase_token' => $verifiedIdToken,
                'firebase_user' => [
                    'uid' => $uid,
                    'email' => $verifiedIdToken->claims()->get('email'),
                    'email_verified' => $verifiedIdToken->claims()->get('email_verified', false),
                    'name' => $verifiedIdToken->claims()->get('name'),
                    'picture' => $verifiedIdToken->claims()->get('picture'),
                ]
            ]);

            Log::info('Firebase authentication successful', [
                'uid' => $uid,
                'email' => $verifiedIdToken->claims()->get('email'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return $next($request);

        } catch (\Kreait\Firebase\Exception\Auth\InvalidIdToken $e) {
            Log::warning('Invalid Firebase ID token', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->unauthorizedResponse('Invalid Firebase ID token');

        } catch (\Kreait\Firebase\Exception\Auth\ExpiredIdToken $e) {
            Log::warning('Expired Firebase ID token', [
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            return $this->unauthorizedResponse('Expired Firebase ID token');

        } catch (\Exception $e) {
            Log::error('Firebase authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip()
            ]);
            return $this->unauthorizedResponse('Firebase authentication failed');
        }
    }

    /**
     * 從請求中提取 ID Token
     */
    protected function extractIdToken(Request $request): ?string
    {
        // 1. 檢查 Authorization Header (Bearer token)
        $authHeader = $request->header('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        // 2. 檢查自定義 Header
        $customToken = $request->header('X-Firebase-Token');
        if ($customToken) {
            return trim($customToken);
        }

        // 3. 檢查 Cookie
        $cookieToken = $request->cookie('firebase_token');
        if ($cookieToken) {
            return trim($cookieToken);
        }

        // 4. 檢查 POST 參數 (不建議，但作為備選)
        $postToken = $request->input('firebase_token');
        if ($postToken) {
            return trim($postToken);
        }

        return null;
    }

    /**
     * 回傳未授權回應
     */
    protected function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => $message,
            'code' => 'FIREBASE_AUTH_FAILED'
        ], 401);
    }

    /**
     * 檢查 Firebase 用戶是否與 Laravel 用戶關聯
     */
    protected function findOrCreateLaravelUser(string $firebaseUid, array $firebaseUser): ?\App\Models\User
    {
        try {
            // 嘗試通過 Firebase UID 查找用戶
            $user = \App\Models\User::where('firebase_uid', $firebaseUid)->first();
            
            if (!$user && !empty($firebaseUser['email'])) {
                // 通過 email 查找用戶
                $user = \App\Models\User::where('email', $firebaseUser['email'])->first();
                
                if ($user) {
                    // 關聯 Firebase UID
                    $user->update(['firebase_uid' => $firebaseUid]);
                }
            }

            if (!$user && env('FIREBASE_AUTO_CREATE_USERS', false)) {
                // 自動建立用戶（僅在啟用時）
                $user = \App\Models\User::create([
                    'name' => $firebaseUser['name'] ?? 'Firebase User',
                    'email' => $firebaseUser['email'] ?? $firebaseUid . '@firebase.local',
                    'email_verified_at' => $firebaseUser['email_verified'] ? now() : null,
                    'firebase_uid' => $firebaseUid,
                    'password' => bcrypt(str()->random(32)), // 隨機密碼
                ]);
            }

            return $user;
        } catch (\Exception $e) {
            Log::error('Failed to find or create Laravel user from Firebase', [
                'firebase_uid' => $firebaseUid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}