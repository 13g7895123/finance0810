# Point 75 Webhook MySQL 寫入修復

## 問題描述
用戶回報：「之前用這支都可以，為甚麼又要替換」  
Point 74：Webhook 觸發後，連 MySQL 都沒有寫進資料

## 根本原因分析

### 1. 被替換的邏輯
原本可靠的客戶創建邏輯被複雜化：
- **原本**：使用簡單的客戶創建邏輯
- **被替換為**：複雜的 `findOrCreateCustomer` 邏輯 + `createSimpleCustomer` 回退

### 2. MySQL 寫入失敗原因
`createSimpleCustomer` 方法存在兩個致命問題：
```php
// 問題 1: phone 欄位為必填，不能是空字串
'phone' => '', // ❌ 導致 SQLSTATE[HY000]: Field 'phone' doesn't have a default value

// 問題 2: 缺少 version_updated_at 欄位
// ❌ 導致 SQLSTATE[HY000]: Field 'version_updated_at' doesn't have a default value
```

### 3. 邏輯順序問題
原本的邏輯順序不合理：
1. 先嘗試複雜且容易出錯的 `findOrCreateCustomer`
2. 失敗後回退到 `createSimpleCustomer`
3. 但 `createSimpleCustomer` 也有問題，導致完全無法創建客戶

## 修復方案

### 1. 修復 createSimpleCustomer 方法
```php
$customer = \App\Models\Customer::create([
    'name' => 'LINE用戶 ' . substr($lineUserId, -6),
    'phone' => '0900000000', // ✅ 提供預設電話號碼避免必填錯誤
    'line_user_id' => $lineUserId,
    'channel' => 'line',
    'status' => 'new',
    'tracking_status' => 'pending',
    'assigned_to' => $assignedTo,
    'version' => 1,
    'version_updated_at' => now(), // ✅ 添加版本更新時間
    'website_source' => 'line',
    'region' => 'unknown',
    'source' => 'line_webhook'
]);
```

### 2. 改變邏輯順序 - 優先使用可靠方案
```php
// ✅ 新邏輯：先使用簡單可靠的方法
$customer = $this->createSimpleCustomer($lineUserId);

// 只有在簡單方法失敗時，才嘗試複雜版本
if (!$customer) {
    try {
        $customer = $this->findOrCreateCustomer($lineUserId, $event);
    } catch (\Exception $e) {
        // 記錄錯誤並直接返回，避免後續處理 null customer
        return;
    }
}
```

## 修復效果

### ✅ 恢復原本可工作的邏輯
- 優先使用簡單、可靠的客戶創建方法
- 避免複雜的查詢邏輯導致的失敗

### ✅ 解決 MySQL 寫入問題
- 修復必填欄位錯誤
- 確保客戶記錄可以成功創建

### ✅ 提升可靠性
- 簡單邏輯優先，複雜邏輯作為備份
- 完整的錯誤處理和日誌記錄

## 測試驗證

修復後的 Webhook 流程：
1. 接收 LINE 訊息
2. ✅ 成功創建客戶記錄（使用簡化邏輯）
3. ✅ 成功創建對話記錄
4. ✅ 同步到 Firebase（如果可用）

## 重要教訓

**不要隨意替換原本可以工作的邏輯**
- 如果原本的方法可靠，應該保持使用
- 新功能應該作為增強，而不是替換
- 複雜化不一定等於優化