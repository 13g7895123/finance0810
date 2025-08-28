# Point 73 緊急修復 - 恢復正確配置

## 問題根源
Point 72 中錯誤地將 Firebase 專案從 `finance0810new` 改成 `finance0810-692ec`，違反了 Point 31 中的明確指示。

## Point 31 原始需求回顧
> "請統一用這個 finance0810new，並告訴我有哪些需要配置的參數"

## 已恢復的配置

### 1. Backend 配置 (.env)
```env
FIREBASE_PROJECT_ID=finance0810new
FIREBASE_DATABASE_URL=https://finance0810new-default-rtdb.asia-southeast1.firebasedatabase.app/
```

### 2. Frontend 配置 (.env)  
```env
NUXT_FIREBASE_DATABASE_URL=https://finance0810new-default-rtdb.asia-southeast1.firebasedatabase.app/
```

### 3. Firebase 權限規則 (簡化)
恢復為簡單的時間限制規則，避免複雜的認證問題：
```json
{
  "rules": {
    ".read": "now < 1758556800000",  // 2025-9-23
    ".write": "now < 1758556800000"  // 2025-9-23
  }
}
```

## ⚠️ 仍需解決的問題

### 服務帳戶檔案不匹配
- **當前服務帳戶**：`finance0810-692ec` 專案
- **目標專案**：`finance0810new`

**解決方案**：
1. **方案一**：從 Firebase Console 獲取 `finance0810new` 專案的服務帳戶金鑰
2. **方案二**：確認 `finance0810-692ec` 的服務帳戶是否有權限存取 `finance0810new` 資料庫

## 需要手動操作

### 獲取正確的服務帳戶金鑰：
1. 前往 [Firebase Console - finance0810new](https://console.firebase.google.com/project/finance0810new/settings/serviceaccounts/adminsdk)
2. 生成新的私人金鑰
3. 下載 JSON 檔案
4. 替換 `backend/storage/app/firebase-service-account.json`

### 或者部署權限規則：
```bash
cd firebase/
firebase use finance0810new
firebase deploy --only database
```

## 修復後的期望結果
- ✅ 統一使用 `finance0810new` Firebase 專案
- ✅ 前後端配置一致
- ✅ 簡化權限規則避免認證問題
- 🔄 等待正確的服務帳戶金鑰