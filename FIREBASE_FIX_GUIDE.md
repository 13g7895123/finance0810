# Firebase Authorization Error Fix Guide

## 問題診斷
目前Firebase連接錯誤是因為**專案ID不匹配**：
- 服務帳號JSON: `finance0810-692ec`
- 系統配置: `finance0810new`

## 解決方案 A: 使用現有專案 finance0810-692ec (快速修復)

### Step 1: 前往 Firebase Console
1. 打開 https://console.firebase.google.com/
2. 選擇專案 `finance0810-692ec`

### Step 2: 設定 Realtime Database 規則
在 Realtime Database → Rules 設定：
```json
{
  "rules": {
    ".read": true,
    ".write": true
  }
}
```

### Step 3: 取得 Web App 配置
1. 在專案設定 → 一般 → 您的應用程式
2. 如果沒有 Web 應用程式，點擊「新增應用程式」→ 選擇 Web
3. 複製配置資訊：
```javascript
const firebaseConfig = {
  apiKey: "your-api-key",
  authDomain: "finance0810-692ec.firebaseapp.com",
  databaseURL: "https://finance0810-692ec-default-rtdb.asia-southeast1.firebasedatabase.app/",
  projectId: "finance0810-692ec",
  storageBucket: "finance0810-692ec.appspot.com",
  messagingSenderId: "your-sender-id",
  appId: "your-app-id"
};
```

### Step 4: 更新前端配置
編輯 `frontend/plugins/firebase.client.js`，將上面的配置資訊填入

## 解決方案 B: 使用 finance0810new 專案 (完整遷移)

### Step 1: 下載新的服務帳號
1. 打開 https://console.firebase.google.com/
2. 選擇專案 `finance0810new`
3. 專案設定 → 服務帳號
4. 點擊「產生新的私密金鑰」
5. 下載 JSON 檔案

### Step 2: 替換服務帳號檔案
將下載的 JSON 檔案放到：
`backend/storage/app/firebase-service-account.json`

### Step 3: 更新後端配置
編輯 `backend/config/services.php`：
```php
'firebase' => [
    'project_id' => env('FIREBASE_PROJECT_ID', 'finance0810new'),
    'database_url' => env('FIREBASE_DATABASE_URL', 'https://finance0810new-default-rtdb.asia-southeast1.firebasedatabase.app/'),
    // ... 其他配置
]
```

### Step 4: 設定 Realtime Database 規則
在 Firebase Console → Realtime Database → Rules：
```json
{
  "rules": {
    ".read": true,
    ".write": true
  }
}
```

## 測試連接

### 1. 測試後端連接
```bash
curl https://dev-finance.mercylife.cc/api/firebase/diagnostic
```

### 2. 檢查回應
成功應該顯示：
```json
{
  "connection_test": {
    "connection_successful": true,
    "write_test": "passed",
    "read_test": "passed"
  }
}
```

## 重要提醒
- 確保前後端使用相同的 Firebase 專案
- 服務帳號 JSON 的 project_id 必須與配置相符
- Realtime Database 必須啟用且設定正確的規則
- 部署後可能需要等待 1-2 分鐘生效