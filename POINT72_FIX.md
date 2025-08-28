# Point 72 Firebase 權限修復指南

## 問題描述
出現 `Kreait\\Firebase\\Exception\\Database\\PermissionDenied` 錯誤。

## 根本原因
1. **專案配置不一致**：Backend 配置指向 `finance0810new`，但服務帳戶屬於 `finance0810-692ec`
2. **權限規則不足**：Firebase Realtime Database 權限規則缺乏服務帳戶認證

## 已修復項目

### 1. 統一專案配置
- ✅ Backend `.env`：`FIREBASE_PROJECT_ID=finance0810-692ec`
- ✅ Backend `.env`：`FIREBASE_DATABASE_URL=https://finance0810-692ec-default-rtdb.asia-southeast1.firebasedatabase.app/`
- ✅ Frontend `.env`：更新 Database URL 為正確專案

### 2. 更新 Firebase Realtime Database 權限規則
```json
{
  "rules": {
    // 允許服務帳戶完全存取
    ".read": "auth != null",
    ".write": "auth != null",
    
    // 對話資料結構權限
    "conversations": {
      "$lineUserId": {
        ".read": "auth != null",
        ".write": "auth != null",
        ".validate": "newData.hasChildren(['id', 'customerName', 'lastMessage'])",
        
        "messages": {
          "$messageId": {
            ".read": "auth != null", 
            ".write": "auth != null",
            ".validate": "newData.hasChildren(['senderId', 'content', 'timestamp'])"
          }
        }
      }
    },
    
    // 系統診斷和測試路徑
    "system": {
      ".read": "auth != null",
      ".write": "auth != null"
    },
    
    "diagnostic_tests": {
      ".read": "auth != null",
      ".write": "auth != null"  
    }
  }
}
```

## ⚠️ 需要手動執行的步驟

為了完全解決權限問題，需要手動部署新的權限規則到 Firebase Console：

### 方法一：Firebase CLI 部署
```bash
cd firebase/
firebase login
firebase deploy --only database
```

### 方法二：Firebase Console 手動更新
1. 前往 [Firebase Console](https://console.firebase.google.com/project/finance0810-692ec/database/finance0810-692ec-default-rtdb/rules)
2. 選擇 Realtime Database > Rules
3. 將 `firebase/database.rules.json` 的內容複製到 Rules 編輯器
4. 點擊 "發布"

## 驗證修復
修復完成後，可以測試：

1. **API 測試**：
```bash
curl -X GET https://dev-finance.mercylife.cc/api/debug/firebase/health
```

2. **Laravel Artisan 測試**：
```bash
php artisan firebase:test-service
```

3. **聊天室功能測試**：
   - 發送 LINE 訊息到機器人
   - 檢查後台聊天室是否即時更新

## 修復完成後的系統架構
- ✅ 統一使用 `finance0810-692ec` Firebase 專案
- ✅ 服務帳戶權限正確配置
- ✅ Realtime Database 權限規則允許後端存取
- ✅ 前後端配置一致