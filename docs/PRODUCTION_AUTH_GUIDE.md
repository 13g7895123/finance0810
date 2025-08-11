# 生產環境認證配置指南 (Production Authentication Configuration Guide)

## 問題概述 (Issue Overview)

在生產環境 `https://dev-finance.mercylife.cc` 中發生認證失敗問題，即使用戶具有 admin 權限，仍無法存取用戶管理功能，API 請求返回 401 錯誤。

## 根本原因分析 (Root Cause Analysis)

經過詳細分析，發現以下三個主要問題：

### 1. CORS 配置問題
- **問題**：生產環境域名 `https://dev-finance.mercylife.cc` 未包含在後端 CORS 允許來源清單中
- **影響**：瀏覽器拒絕跨域 API 請求，導致認證失敗
- **位置**：`backend/config/cors.php`

### 2. Cookie 域名設定問題  
- **問題**：HTTP-Only Cookie 的域名設定為 `null`，在跨子域名情況下無法共享
- **影響**：認證 Cookie 無法在不同子域名間正確傳遞
- **位置**：`backend/app/Http/Controllers/Api/AuthController.php`

### 3. SameSite 策略問題
- **問題**：生產環境使用 `SameSite=None` 但缺少適當的 HTTPS 配置
- **影響**：現代瀏覽器安全策略阻止 Cookie 傳送

## 修復方案 (Solution Implementation)

### ✅ 修復 1：更新 CORS 配置

**檔案**：`backend/config/cors.php`

```php
'allowed_origins' => [
    'http://finance.local',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost:9121',
    'http://127.0.0.1:9121',
    'https://dev-finance.mercylife.cc',      // ✅ 新增生產域名
    'https://finance.mercylife.cc',          // ✅ 新增正式域名
],
```

### ✅ 修復 2：優化 Cookie 配置

**檔案**：`backend/app/Http/Controllers/Api/AuthController.php`

**登入方法優化**：
```php
// 根據環境設定 domain 和 sameSite
$isProduction = app()->environment('production');
$domain = $isProduction ? '.mercylife.cc' : null; // 生產環境使用子域名通用設定
$sameSite = $isProduction ? 'Lax' : 'None'; // 生產環境使用 Lax，開發環境使用 None

$cookie = cookie(
    'auth-token',           // cookie 名稱
    $token,                 // token 值
    JWTAuth::factory()->getTTL(), // 過期時間（分鐘）
    '/',                    // path
    $domain,                // domain - 生產環境設定子域名共用
    request()->secure(),    // secure (HTTPS)
    true,                   // httpOnly
    false,                  // raw
    $sameSite               // sameSite - 依環境調整
);
```

**登出方法優化**：
```php
// 清除 HTTP-Only Cookie
// 使用與登入時相同的 domain 設定
$isProduction = app()->environment('production');
$domain = $isProduction ? '.mercylife.cc' : null;

$cookie = cookie(
    'auth-token',           // cookie 名稱
    null,                   // 清空值
    -1,                     // 過期時間設為過去
    '/',                    // path
    $domain,                // domain - 與登入時相同
    request()->secure(),    // secure
    true,                   // httpOnly
    false,                  // raw
    $isProduction ? 'Lax' : 'None' // sameSite
);
```

### ✅ 修復 3：新增除錯端點

**檔案**：`backend/routes/api.php` 和 `backend/app/Http/Controllers/TestController.php`

新增 `/api/test/cookies` 端點，用於診斷認證配置問題：

```php
/**
 * Test cookie and authentication configuration
 */
public function cookieTest(Request $request)
{
    $isProduction = app()->environment('production');
    $domain = $isProduction ? '.mercylife.cc' : null;
    
    return response()->json([
        'environment' => app()->environment(),
        'request_secure' => $request->secure(),
        'request_host' => $request->getHost(),
        'cookie_domain' => $domain,
        'sameSite' => $isProduction ? 'Lax' : 'None',
        'cors_enabled' => config('cors.supports_credentials'),
        'allowed_origins' => config('cors.allowed_origins'),
        'auth_cookie_present' => !empty($request->cookie('auth-token')),
        'recommendations' => [/* 相關建議 */],
    ]);
}
```

## 部署指南 (Deployment Guide)

### 1. 生產環境變數設定

確保生產環境 `.env` 檔案包含以下設定：

```bash
# 基本設定
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dev-finance.mercylife.cc

# 資料庫設定
DB_CONNECTION=mysql
DB_HOST=your-production-db-host
DB_PORT=3306
DB_DATABASE=finance_crm_prod
DB_USERNAME=your-db-user
DB_PASSWORD=your-secure-password

# JWT 設定
JWT_SECRET=your-production-jwt-secret-key
JWT_TTL=60

# 其他必要設定...
```

### 2. HTTPS 配置檢查

確保生產環境正確配置 HTTPS：

```bash
# 檢查 SSL 證書
curl -I https://dev-finance.mercylife.cc/api/health

# 預期回應包含：
# HTTP/2 200
# strict-transport-security: max-age=31536000
```

### 3. 部署後驗證步驟

#### 步驟 1：檢查基本配置
```bash
curl https://dev-finance.mercylife.cc/api/test/cookies
```

#### 步驟 2：測試 CORS 設定
```bash
curl -H "Origin: https://dev-finance.mercylife.cc" \
     -H "Access-Control-Request-Method: POST" \
     -H "Access-Control-Request-Headers: Content-Type" \
     -X OPTIONS \
     https://dev-finance.mercylife.cc/api/auth/login
```

#### 步驟 3：測試登入流程
```bash
curl -X POST https://dev-finance.mercylife.cc/api/auth/login \
     -H "Content-Type: application/json" \
     -H "Origin: https://dev-finance.mercylife.cc" \
     -d '{"username":"admin","password":"admin123"}' \
     -c cookies.txt -b cookies.txt
```

#### 步驟 4：測試認證 API
```bash
curl https://dev-finance.mercylife.cc/api/users \
     -H "Origin: https://dev-finance.mercylife.cc" \
     -b cookies.txt
```

## 故障排除指南 (Troubleshooting Guide)

### 問題 1：API 請求返回 401 錯誤

**可能原因**：
1. CORS 配置未包含生產域名
2. Cookie 未正確傳遞
3. JWT Token 已過期

**除錯步驟**：
1. 檢查瀏覽器開發者工具 Network 標籤
2. 確認請求包含 `Origin` 標頭
3. 檢查回應是否包含 CORS 標頭
4. 訪問 `/api/test/cookies` 確認配置

### 問題 2：Cookie 未設定或傳送

**可能原因**：
1. 非 HTTPS 環境使用 `Secure` cookie
2. SameSite 策略衝突
3. 域名不匹配

**除錯步驟**：
1. 檢查瀏覽器開發者工具 Application > Cookies
2. 確認 cookie 域名設定正確
3. 檢查 SameSite 和 Secure 屬性

### 問題 3：跨域請求被拒絕

**可能原因**：
1. CORS 預檢請求失敗
2. Origin 不在允許清單中
3. Credentials 設定不正確

**除錯步驟**：
1. 檢查 OPTIONS 請求回應
2. 確認 `supports_credentials: true`
3. 驗證 `allowed_origins` 設定

## 安全考量 (Security Considerations)

### 1. Cookie 安全設定
- **HttpOnly**: 防止 XSS 攻擊
- **Secure**: 僅在 HTTPS 連線傳送
- **SameSite=Lax**: 平衡安全性與可用性

### 2. CORS 策略
- **嚴格來源控制**: 只允許信任的域名
- **Credentials 支援**: 僅在必要時啟用
- **定期檢查**: 確保配置與實際需求一致

### 3. JWT 配置
- **短過期時間**: 預設 60 分鐘
- **強密鑰**: 使用足夠長度的隨機密鑰
- **黑名單機制**: 登出時失效 token

## 效能優化建議 (Performance Recommendations)

### 1. Cookie 優化
- 使用適當的過期時間
- 避免不必要的 cookie 屬性

### 2. CORS 快取
- 設定適當的 `max_age` 值
- 減少預檢請求頻率

### 3. JWT 處理
- 實作 token 刷新機制
- 考慮使用 Redis 快取權限資訊

## 監控與日誌 (Monitoring and Logging)

### 建議監控項目
- 認證成功/失敗率
- CORS 錯誤頻率
- Cookie 設定問題
- API 回應時間

### 日誌配置
```php
// 在 AuthController 中新增日誌
Log::info('Login attempt', [
    'username' => $request->username,
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'origin' => $request->header('Origin'),
]);
```

## 總結 (Summary)

本次修復解決了生產環境認證失敗的三個核心問題：
1. ✅ 更新 CORS 配置，新增生產域名支援
2. ✅ 優化 Cookie 設定，支援跨子域名認證  
3. ✅ 調整 SameSite 策略，符合現代瀏覽器安全要求

這些修復確保了：
- 🔒 生產環境認證正常運作
- 🌐 跨域請求正確處理
- 🍪 Cookie 安全且可靠傳遞
- 🔧 完善的除錯工具支援

---

**文檔版本**: v1.0  
**更新日期**: 2024-01-15  
**適用環境**: Production deployment on dev-finance.mercylife.cc