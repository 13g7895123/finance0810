# Webhook 驗證指南

## 📋 概述

此指南提供完整的方法來驗證 LINE Bot webhook 是否正確執行並將資料寫入 MySQL 和 Firebase。

## 🛠️ 驗證工具

### 1. 視覺化監控面板

**文件位置**: `webhook-monitor.html`

**使用方法**:
1. 在瀏覽器中開啟 `webhook-monitor.html`
2. 點擊「完整驗證」檢查整體狀況
3. 發送 LINE 訊息到機器人
4. 點擊「即時狀態」查看新活動

**功能特色**:
- 🔄 自動刷新功能
- 📊 即時系統狀態顯示
- 💬 最近訊息列表
- 📝 詳細執行日誌

### 2. API 端點

#### 完整驗證端點
```
GET https://dev-finance.mercylife.cc/api/verify/webhook-execution
```

**驗證步驟**:
1. ✅ 檢查最近 Webhook 活動
2. ✅ 檢查 Firebase 連接狀態
3. ✅ 檢查 Webhook 日誌
4. ✅ 測試資料同步功能

#### 即時狀態端點
```
GET https://dev-finance.mercylife.cc/api/webhook/status?since=2025-01-15T10:00:00Z
```

**參數**:
- `since`: 檢查從何時開始的活動 (可選)

## 🧪 測試流程

### 標準測試步驟

1. **準備階段**
   ```bash
   # 開啟監控面板
   open webhook-monitor.html
   
   # 或使用 API
   curl "https://dev-finance.mercylife.cc/api/verify/webhook-execution"
   ```

2. **執行測試**
   - 使用 LINE App 發送訊息到機器人
   - 等待 5-10 秒讓系統處理
   - 檢查監控面板或再次呼叫 API

3. **驗證結果**
   - ✅ MySQL 中出現新的對話記錄
   - ✅ Firebase 同步成功
   - ✅ 日誌顯示完整處理流程

### 快速檢查命令

```bash
# 檢查最近活動
curl "https://dev-finance.mercylife.cc/api/webhook/status"

# 完整系統診斷
curl "https://dev-finance.mercylife.cc/api/diagnose/data-flow"

# 驗證 webhook 執行
curl "https://dev-finance.mercylife.cc/api/verify/webhook-execution"
```

## 🔍 故障排除

### 常見問題診斷

#### 1. 沒有新的對話記錄
**可能原因**:
- Webhook 未被 LINE 正確呼叫
- 簽章驗證失敗
- 資料庫連接問題

**檢查方法**:
```bash
# 檢查最近的資料庫活動
curl "https://dev-finance.mercylife.cc/api/webhook/status"
```

#### 2. Firebase 同步失敗
**可能原因**:
- Firebase 配置錯誤
- 網路連接問題
- 權限設定問題

**檢查方法**:
```bash
# 檢查 Firebase 連接
curl "https://dev-finance.mercylife.cc/api/verify/webhook-execution" | jq '.verification.steps[1]'
```

#### 3. 日誌文件不存在
**可能原因**:
- 日誌目錄權限問題
- Webhook 從未被觸發
- 日誌路徑配置錯誤

**檢查方法**:
- 查看監控面板的「Webhook 日誌」部分
- 檢查 `storage/logs/` 目錄權限

## 📊 狀態指示器

### 系統狀態
- 🟢 **healthy**: 所有功能正常
- 🟡 **partial**: 部分功能異常
- 🔴 **critical**: 多個關鍵功能失敗

### 連接狀態
- 🟢 **正常**: 連接成功
- 🔴 **異常**: 連接失敗
- ⚪ **未知**: 未測試

## 📈 監控建議

### 定期檢查
1. **每日**: 檢查是否有新的對話活動
2. **每週**: 執行完整驗證
3. **異常時**: 立即使用故障排除工具

### 警報設定
- 超過 1 小時無活動 → 檢查 webhook 狀態
- Firebase 連接失敗 → 檢查配置和網路
- 資料同步延遲 → 檢查隊列處理

## 🔧 進階診斷

### 手動測試 Webhook
```bash
# 模擬 LINE webhook 呼叫（需要有效簽章）
curl -X POST "https://dev-finance.mercylife.cc/api/line/webhook" \
  -H "Content-Type: application/json" \
  -H "x-line-signature: [有效簽章]" \
  -d '{
    "destination": "your-bot-id",
    "events": [{
      "type": "message",
      "message": {
        "type": "text",
        "id": "test-message-id",
        "text": "測試訊息"
      },
      "source": {
        "type": "user",
        "userId": "test-user-id"
      },
      "timestamp": 1640995200000,
      "mode": "active"
    }]
  }'
```

### 檢查具體錯誤
```bash
# 獲取詳細錯誤資訊
curl "https://dev-finance.mercylife.cc/api/verify/webhook-execution" | jq '.verification.steps[] | select(.status == "failed")'
```

## 📞 支援

如果上述方法都無法解決問題，請提供以下資訊：

1. 監控面板的完整日誌輸出
2. API 回應的 JSON 內容
3. 發送 LINE 訊息的確切時間
4. 任何錯誤訊息截圖

---

**最後更新**: 2025-08-27
**適用版本**: finance0810 develop branch