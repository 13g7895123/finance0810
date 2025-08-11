# 認證與授權系統說明書 (Authentication & Authorization Documentation)

## 概述 (Overview)

本系統採用基於 JWT (JSON Web Token) 的認證機制，結合基於角色的權限控制 (RBAC) 來確保系統安全性。前端使用 Nuxt 3 + Vue 3，後端使用 Laravel，配合 Spatie Permission 套件管理權限。

### 技術架構
- **前端認證**：Nuxt 3 + Pinia Store + HTTP-Only Cookies
- **後端認證**：Laravel + JWT + Spatie Permission
- **資料庫**：MySQL 8.0
- **權限模型**：角色基礎權限控制 (RBAC)

---

## 認證流程 (Authentication Flow)

### 1. 登入流程 (Login Process)

#### 前端登入步驟：
1. 用戶在 `/auth/login` 頁面輸入帳號密碼
2. 前端驗證表單資料格式
3. 發送 POST 請求到 `/api/auth/login`
4. 後端驗證認證資訊
5. 成功時設定 HTTP-Only Cookie 並回傳用戶資訊
6. 前端儲存用戶資料到 SessionStorage
7. 重定向到儀表板頁面

#### API 請求格式：
```javascript
POST /api/auth/login
{
  \"username\": \"admin\",        // 可以是使用者名稱或電子郵件
  \"password\": \"admin123\"
}
```

#### 成功回應格式：
```javascript
{
  \"access_token\": \"eyJ0eXAiOiJKV1Q...\",
  \"token_type\": \"bearer\",
  \"expires_in\": 3600,
  \"user\": {
    \"id\": 1,
    \"name\": \"系統管理員\",
    \"username\": \"admin\",
    \"email\": \"admin@finance-crm.com\",
    \"roles\": [\"admin\"],
    \"permissions\": [\"user.view\", \"user.create\", ...],
    \"is_admin\": true,
    \"is_manager\": true
  }
}
```

### 2. 認證狀態維護

#### HTTP-Only Cookie
- Cookie 名稱：`auth-token`
- 包含：JWT Token
- 特性：HttpOnly、Secure、SameSite
- 過期時間：60 分鐘 (可設定)

#### SessionStorage
- 儲存項目：`user-profile`
- 內容：用戶基本資訊 (不含敏感資料)
- 用途：前端權限判斷和 UI 顯示

#### 認證狀態檢查
```javascript
// 前端檢查認證狀態
const authStore = useAuthStore()

// 頁面載入時自動初始化
await authStore.initializeAuth()

// 檢查是否已登入
if (authStore.isLoggedIn) {
  // 用戶已登入邏輯
}
```

### 3. 登出流程 (Logout Process)

1. 調用 `authStore.logout()`
2. 發送 POST 請求到 `/api/auth/logout`
3. 後端清除 JWT Token (黑名單)
4. 清除 HTTP-Only Cookie
5. 清除前端 SessionStorage
6. 重定向到登入頁面

---

## 權限系統 (Authorization System)

### 角色定義 (Role Definitions)

系統定義四種主要角色：

#### 1. 經銷商/公司高層 (Admin/Executive)
- **角色代碼**：`admin`, `executive`
- **權限範圍**：系統所有權限
- **特殊權限**：
  - 系統設定管理
  - 用戶管理 (新增、修改、刪除)
  - 所有報表查看
  - 系統維護功能

#### 2. 行政人員/主管 (Manager)
- **角色代碼**：`manager`
- **權限範圍**：大部分業務功能
- **限制**：無法修改銀行交涉紀錄
- **主要權限**：
  - 用戶管理 (檢視、新增、修改)
  - 客戶資料管理
  - 報表查看與匯出
  - 案件狀態管理

#### 3. 業務人員 (Staff)
- **角色代碼**：`staff`
- **權限範圍**：僅限自己負責的客戶
- **主要權限**：
  - 查看與編輯自己的客戶
  - 新增客戶資料
  - 設定追蹤日期
  - 查看銀行交涉紀錄 (唯讀)

### 權限分類 (Permission Categories)

#### 客戶管理權限 (Customer Management)
```javascript
'customer.view'      // 查看客戶資料
'customer.create'    // 新增客戶資料
'customer.edit'      // 編輯客戶資料
'customer.delete'    // 刪除客戶資料
'customer.assign'    // 分配客戶給業務
'customer.track'     // 設定追蹤日期
'customer.status'    // 更改客戶狀態
'customer.view-all'  // 查看所有客戶（不限負責人）
```

#### 用戶管理權限 (User Management)
```javascript
'user.view'        // 查看用戶列表
'user.create'      // 新增用戶
'user.edit'        // 編輯用戶資料
'user.delete'      // 刪除用戶
'user.roles'       // 管理用戶角色
'user.permissions' // 管理用戶權限
```

#### 案件管理權限 (Case Management)
```javascript
'case.view'      // 查看案件資料
'case.create'    // 新增案件
'case.edit'      // 編輯案件資料
'case.delete'    // 刪除案件
'case.submit'    // 送件處理
'case.approve'   // 案件核准
'case.disburse'  // 撥款處理
```

#### 報表權限 (Report Permissions)
```javascript
'report.daily'      // 查看日報表
'report.monthly'    // 查看月報表
'report.website'    // 查看網站統計
'report.region'     // 查看地區統計
'report.approval'   // 查看核准率統計
'report.accounting' // 查看會計報表
'report.export'     // 匯出報表
```

### 權限檢查機制

#### 前端權限檢查
```javascript
// 在 Vue 組件中檢查權限
const authStore = useAuthStore()

// 檢查特定權限
if (authStore.hasPermission('user.create')) {
  // 顯示新增用戶按鈕
}

// 檢查角色
if (authStore.isAdmin || authStore.isManager) {
  // 顯示管理功能
}
```

#### 後端權限檢查
```php
// Laravel 路由中的權限檢查
Route::middleware(['auth:api', 'role:admin|executive|manager'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
});

// 控制器中的權限檢查
public function index()
{
    $this->authorize('user.view');
    // 業務邏輯
}
```

---

## 用戶管理功能 (User Management)

### 用戶列表功能

#### 權限要求
- 需要 `user.view` 權限或 Admin/Manager 角色

#### 主要功能
1. **用戶列表顯示**
   - 顯示用戶基本資訊
   - 角色標籤顯示
   - 最後登入時間
   - 帳號狀態 (啟用/停用)

2. **搜尋功能**
   - 支援姓名、電子郵件搜尋
   - 即時搜尋 (300ms 防抖)

3. **狀態管理**
   - 啟用/停用帳號
   - 不能停用自己的帳號

#### API 端點
```javascript
GET /api/users?search=admin&status=active
```

### 新增用戶功能

#### 權限要求
- 需要 `user.create` 權限

#### 必填欄位
- 姓名 (2-100 字元)
- 使用者名稱 (唯一，最多50字元)
- 電子郵件 (唯一，有效格式)
- 密碼 (最少6字元)
- 角色選擇

#### API 端點
```javascript
POST /api/users
{
  \"name\": \"新用戶\",
  \"username\": \"newuser\",
  \"email\": \"newuser@example.com\",
  \"password\": \"password123\",
  \"role\": \"staff\"
}
```

### 編輯用戶功能

#### 權限要求
- 需要 `user.edit` 權限

#### 可編輯欄位
- 姓名
- 電子郵件
- 角色 (不能修改自己的角色)

#### API 端點
```javascript
PUT /api/users/2
{
  \"name\": \"更新的姓名\",
  \"email\": \"updated@example.com\"
}

POST /api/users/2/roles
{
  \"role\": \"manager\"
}
```

### 刪除用戶功能

#### 權限要求
- 需要 `user.delete` 權限

#### 限制
- 不能刪除自己的帳號
- 需要確認對話框
- 操作無法復原

#### API 端點
```javascript
DELETE /api/users/3
```

---

## 安全性措施 (Security Measures)

### 1. JWT Token 安全
- **儲存方式**：HTTP-Only Cookie (防止 XSS 攻擊)
- **傳輸方式**：HTTPS (防止中間人攻擊)
- **過期機制**：60分鐘自動過期
- **黑名單機制**：登出時加入黑名單

### 2. 密碼安全
- **雜湊演算法**：bcrypt (cost: 10)
- **最小長度**：6 字元
- **密碼更新**：記錄最後更改時間

### 3. CSRF 防護
- **SameSite Cookie**：防止跨站請求偽造
- **Origin 檢查**：驗證請求來源

### 4. XSS 防護
- **Content Security Policy**：限制腳本執行
- **輸入驗證**：過濾惡意腳本
- **輸出編碼**：防止腳本注入

### 5. 權限驗證
- **雙重檢查**：前端 + 後端權限驗證
- **最小權限原則**：用戶只獲得必要權限
- **角色繼承**：上級角色包含下級權限

---

## API 文檔 (API Documentation)

### 認證相關 API

#### POST /api/auth/login
**用途**：用戶登入
**權限**：公開
**請求體**：
```json
{
  \"username\": \"string\",
  \"password\": \"string\"
}
```

#### POST /api/auth/logout
**用途**：用戶登出
**權限**：需要認證
**請求體**：無

#### GET /api/auth/me
**用途**：獲取當前用戶資訊
**權限**：需要認證
**回應**：
```json
{
  \"user\": {
    \"id\": 1,
    \"name\": \"系統管理員\",
    \"username\": \"admin\",
    \"email\": \"admin@finance-crm.com\",
    \"roles\": [\"admin\"],
    \"permissions\": [\"user.view\", \"user.create\"]
  }
}
```

### 用戶管理 API

#### GET /api/users
**用途**：獲取用戶列表
**權限**：`user.view` 或 Admin/Manager 角色
**查詢參數**：
- `search`: 搜尋關鍵字
- `status`: 狀態過濾 (active/inactive)
- `role`: 角色過濾

#### POST /api/users
**用途**：新增用戶
**權限**：`user.create` 或 Admin/Manager 角色

#### PUT /api/users/{id}
**用途**：更新用戶資訊
**權限**：`user.edit` 或 Admin/Manager 角色

#### DELETE /api/users/{id}
**用途**：刪除用戶
**權限**：`user.delete` 或 Admin/Manager 角色

#### GET /api/roles
**用途**：獲取可用角色列表
**權限**：`user.view` 或 Admin/Manager 角色

---

## 測試說明 (Testing Documentation)

### 測試框架
- **測試工具**：Vitest
- **Vue 測試**：Vue Test Utils
- **模擬環境**：Happy DOM

### 測試覆蓋範圍

#### 1. 認證測試 (Authentication Tests)
- 登入成功流程
- 登入失敗處理
- 網路錯誤處理
- 權限驗證邏輯

#### 2. 用戶管理測試 (User Management Tests)
- 用戶列表載入
- 用戶新增功能
- 用戶更新功能
- 用戶刪除功能
- 角色指派功能

#### 3. 權限測試 (Permission Tests)
- Admin 權限檢查
- Manager 權限檢查
- Staff 權限檢查
- 權限拒絕處理

#### 4. 表單驗證測試 (Form Validation Tests)
- 登入表單驗證
- 用戶建立表單驗證
- 錯誤訊息顯示

### 運行測試
```bash
# 運行所有測試
npm run test

# 運行特定測試
npm run test tests/auth-integration.test.js

# 查看測試覆蓋率
npm run test:coverage
```

### 測試結果
目前所有認證和用戶管理相關測試均已通過：
- ✅ 基本功能測試 (2/2)
- ✅ 認證集成測試 (20/20)
- ✅ 總計通過率：100%

---

## 預設帳號資訊 (Default Accounts)

系統初始化後會自動建立以下測試帳號：

### 系統管理員
- **使用者名稱**：`admin`
- **密碼**：`admin123`
- **電子郵件**：`admin@finance-crm.com`
- **角色**：Admin
- **權限**：所有權限

### 業務主管
- **使用者名稱**：`manager`
- **密碼**：`password123`
- **電子郵件**：`manager@finance-crm.com`
- **角色**：Manager
- **權限**：大部分業務權限

### 業務人員
- **使用者名稱**：`staff`
- **密碼**：`password123`
- **電子郵件**：`staff@finance-crm.com`
- **角色**：Staff
- **權限**：基本業務權限

> ⚠️ **重要提醒**：正式環境部署前請務必更改所有預設密碼！

---

## 疑難排解 (Troubleshooting)

### 常見問題

#### 1. 登入後顯示「沒有權限」
**原因**：角色權限配置問題
**解決方案**：
```bash
# 重新執行權限 seeder
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=AdminUserSeeder
```

#### 2. API 請求回傳 401 錯誤
**原因**：JWT Token 過期或無效
**解決方案**：
1. 檢查 HTTP-Only Cookie 是否正確設定
2. 確認 JWT 密鑰配置正確
3. 重新登入更新 Token

#### 3. 用戶管理頁面無法載入
**原因**：權限檢查失敗
**解決方案**：
1. 確認用戶具有 `user.view` 權限
2. 檢查 `api/users` 路由是否正確
3. 確認中介軟體配置正確

#### 4. 角色指派失敗
**原因**：Guard 名稱不匹配
**解決方案**：
1. 確認 User Model 中 `guard_name = 'api'`
2. 確認所有 Role 和 Permission 使用相同的 guard
3. 重新執行 Migration 和 Seeder

### 日誌檢查
```bash
# Laravel 日誌
tail -f storage/logs/laravel.log

# Nginx 日誌
tail -f /var/log/nginx/error.log

# 前端開發者工具
# Network 標籤檢查 API 請求
# Console 檢查 JavaScript 錯誤
```

---

## 最佳實務 (Best Practices)

### 1. 開發建議
- **權限檢查**：前後端都要進行權限驗證
- **錯誤處理**：提供友善的錯誤訊息
- **日誌記錄**：記錄重要的操作和錯誤
- **資料驗證**：嚴格驗證輸入資料

### 2. 部署建議
- **HTTPS**：正式環境必須使用 HTTPS
- **環境變數**：敏感資訊使用環境變數
- **備份**：定期備份資料庫和設定檔
- **監控**：設置系統監控和告警

### 3. 維護建議
- **定期更新**：保持套件和框架最新版本
- **安全掃描**：定期進行安全漏洞掃描
- **密碼政策**：實施強密碼政策
- **存取日誌**：記錄和分析存取日誌

---

## 更新日誌 (Changelog)

### Version 1.0.0 (2024-01-15)
- ✅ 完成 JWT 認證系統
- ✅ 完成角色權限管理
- ✅ 完成用戶管理介面
- ✅ 完成認證狀態持久化
- ✅ 完成測試套件 (22 個測試案例)
- ✅ 修復 guard 名稱不匹配問題
- ✅ 完成系統說明文檔

### 已知問題
- 無重大已知問題

### 計劃功能
- 雙因子認證 (2FA)
- 密碼強度檢測
- 登入記錄查看
- 權限使用統計

---

**文檔版本**：v1.0.0  
**最後更新**：2024-01-15  
**維護者**：開發團隊