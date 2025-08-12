# 聊天室功能測試文檔

## 概述

本文檔詳細描述了融資貸款公司 CRM 系統聊天室功能的測試方案、測試用例和執行指南。聊天室功能整合了 LINE BOT 對話記錄，支援後台人員與客戶的即時溝通。

## 系統架構

### 前端架構
- **框架**: Nuxt 3 + Vue 3
- **狀態管理**: Pinia
- **API 通信**: 自定義 useChat composable
- **UI 組件**: 
  - `pages/chat/index.vue` - 主聊天界面
  - `components/ChatUserList.vue` - 用戶列表組件
  - `components/ChatMessageArea.vue` - 訊息顯示組件

### 後端架構
- **框架**: Laravel 11
- **控制器**: `ChatController`
- **模型**: `ChatConversation`, `Customer`, `User`
- **資料庫**: PostgreSQL
- **權限控制**: Spatie Laravel Permission

### API 端點
```
GET    /api/chats                     - 獲取對話列表
GET    /api/chats/search              - 搜尋對話
GET    /api/chats/stats               - 獲取聊天統計
GET    /api/chats/unread/count        - 獲取未讀訊息數量
GET    /api/chats/{userId}            - 獲取特定對話內容
POST   /api/chats/{userId}/reply      - 回覆訊息
POST   /api/chats/{userId}/read       - 標記為已讀
DELETE /api/chats/{userId}            - 刪除對話
POST   /api/line/webhook              - LINE Bot Webhook
```

## 測試環境設定

### 前端測試環境
```bash
# 安裝測試依賴
npm install -D vitest @vue/test-utils @pinia/testing

# 運行測試
npm run test

# 運行特定測試文件
npm run test frontend/tests/chat/chat.test.js

# 生成測試覆蓋率報告
npm run test:coverage
```

### 後端測試環境
```bash
# 安裝測試依賴
composer install --dev

# 設定測試資料庫
cp .env .env.testing
# 修改 .env.testing 中的資料庫配置

# 運行測試
php artisan test

# 運行特定測試類
php artisan test tests/Feature/ChatControllerTest.php

# 生成測試覆蓋率報告
php artisan test --coverage
```

## 測試用例詳細說明

### 1. API 連接測試

#### 1.1 對話列表 API 測試
```javascript
// 測試目標: 驗證能夠正確獲取對話列表
// 測試文件: frontend/tests/chat/chat.test.js
test('應該能夠連接聊天 API', async () => {
  const { getConversations } = useChat()
  const result = await getConversations()
  expect(result).toBeDefined()
  expect(result.data).toHaveLength(1)
})
```

#### 1.2 API 錯誤處理測試
```javascript
// 測試目標: 驗證 API 失敗時的容錯機制
test('應該能夠處理 API 連接失敗', async () => {
  const { getConversations } = useChat()
  vi.mocked(getConversations).mockRejectedValue(new Error('Network error'))
  
  try {
    await getConversations()
  } catch (error) {
    expect(error.message).toBe('Network error')
  }
})
```

### 2. 權限控制測試

#### 2.1 管理員權限測試
```php
// 測試目標: 驗證管理員可以查看所有對話
// 測試文件: backend/tests/Feature/ChatControllerTest.php
public function test_admin_can_get_all_conversations()
{
    Sanctum::actingAs($this->admin);
    
    $response = $this->getJson('/api/chats');
    
    $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'line_user_id',
                        'customer_id',
                        'last_message_time',
                        'unread_count'
                    ]
                ]
            ]);
}
```

#### 2.2 業務人員權限測試
```php
// 測試目標: 驗證業務人員只能查看分配給自己的客戶對話
public function test_staff_can_only_see_assigned_conversations()
{
    Sanctum::actingAs($this->staff);
    
    $response = $this->getJson('/api/chats');
    
    $conversations = $response->json('data');
    foreach ($conversations as $conversation) {
        $this->assertEquals($this->staff->id, $conversation['customer']['assigned_to']);
    }
}
```

### 3. 訊息發送與接收測試

#### 3.1 訊息發送測試
```javascript
// 測試目標: 驗證能夠成功發送訊息
test('應該能夠發送訊息', async () => {
  const { replyMessage } = useChat()
  const mockMessage = '測試訊息'
  const mockResponse = {
    message: '訊息已送出',
    conversation: {
      id: 1,
      message_content: mockMessage,
      message_timestamp: new Date()
    }
  }
  
  vi.mocked(replyMessage).mockResolvedValue(mockResponse)
  
  const result = await replyMessage('100', mockMessage)
  expect(result.message).toBe('訊息已送出')
})
```

#### 3.2 後端訊息回覆測試
```php
// 測試目標: 驗證後端能正確處理訊息回覆
public function test_can_reply_to_message()
{
    Sanctum::actingAs($this->staff);
    
    $replyContent = '感謝您的詢問，我們會盡快回覆您。';
    
    $response = $this->postJson("/api/chats/{$this->customer->line_user_id}/reply", [
        'message' => $replyContent
    ]);
    
    $response->assertStatus(200)
            ->assertJson(['message' => '訊息已送出']);
            
    // 確認資料庫記錄
    $this->assertDatabaseHas('chat_conversations', [
        'line_user_id' => $this->customer->line_user_id,
        'message_content' => $replyContent,
        'is_from_customer' => false,
        'replied_by' => $this->staff->id
    ]);
}
```

### 4. 搜尋功能測試

#### 4.1 客戶名稱搜尋測試
```javascript
test('應該能夠搜尋對話', async () => {
  const { searchConversations } = useChat()
  const mockSearchResults = {
    data: [{
      line_user_id: '100',
      customer: { name: '測試客戶', phone: '0912345678' }
    }]
  }
  
  vi.mocked(searchConversations).mockResolvedValue(mockSearchResults)
  
  const result = await searchConversations('測試')
  expect(result.data).toHaveLength(1)
  expect(result.data[0].customer.name).toBe('測試客戶')
})
```

#### 4.2 電話號碼搜尋測試
```php
public function test_can_search_by_phone_number()
{
    Sanctum::actingAs($this->admin);
    
    $response = $this->getJson('/api/chats/search?q=0912345678');
    
    $response->assertStatus(200);
    
    $conversations = $response->json('data');
    foreach ($conversations as $conversation) {
        $this->assertStringContainsString('0912345678', $conversation['customer']['phone']);
    }
}
```

### 5. LINE Webhook 測試

#### 5.1 接收訊息測試
```php
public function test_line_webhook_can_receive_messages()
{
    $webhookData = [
        'events' => [
            [
                'type' => 'message',
                'message' => [
                    'type' => 'text',
                    'text' => '您好，我想詢問汽車貸款相關資訊',
                    'id' => 'message123'
                ],
                'source' => [
                    'userId' => 'U999888777'
                ],
                'timestamp' => now()->timestamp * 1000
            ]
        ]
    ];
    
    $response = $this->postJson('/api/line/webhook', $webhookData);
    
    $response->assertStatus(200)->assertJson(['status' => 'ok']);
    
    // 確認訊息已儲存到資料庫
    $this->assertDatabaseHas('chat_conversations', [
        'line_user_id' => 'U999888777',
        'message_content' => '您好，我想詢問汽車貸款相關資訊',
        'is_from_customer' => true
    ]);
}
```

### 6. 整合測試

#### 6.1 完整聊天流程測試
```javascript
// 測試目標: 驗證從載入對話列表到發送訊息的完整流程
test('應該能完成完整的聊天交互流程', async () => {
  // 1. 載入對話列表
  const mockConversations = { /* ... */ }
  fetch.mockResolvedValueOnce({
    ok: true,
    json: async () => mockConversations
  })
  
  await flushPromises()
  
  // 2. 選擇用戶
  const testUser = { id: 100, name: '測試客戶' }
  await wrapper.vm.selectUser(testUser)
  
  // 3. 發送訊息
  const mockReply = { /* ... */ }
  fetch.mockResolvedValueOnce({
    ok: true,
    json: async () => mockReply
  })
  
  await wrapper.vm.sendMessage('感謝您的詢問')
  
  // 驗證結果
  expect(wrapper.vm.selectedUser).toBeDefined()
})
```

## 測試數據準備

### 1. 測試用戶數據
```javascript
const mockUsers = [
  {
    id: 1,
    name: '經銷商王總',
    role: 'dealer_executive',
    permissions: ['all_access']
  },
  {
    id: 2,
    name: '業務員李小姐',
    role: 'sales_staff',
    permissions: ['personal_customers', 'chat']
  }
]
```

### 2. 測試客戶數據
```php
$testCustomer = Customer::factory()->create([
    'name' => '測試客戶',
    'phone' => '0912345678',
    'line_user_id' => 'U123456789',
    'assigned_to' => $this->staff->id,
    'region' => '台北',
    'source' => '熊好貸',
    'status' => Customer::STATUS_NEW
]);
```

### 3. 測試對話數據
```php
ChatConversation::factory()->create([
    'customer_id' => $testCustomer->id,
    'user_id' => $this->staff->id,
    'line_user_id' => 'U123456789',
    'message_content' => '您好，我想詢問汽車貸款',
    'is_from_customer' => true,
    'status' => 'unread'
]);
```

## 性能測試

### 1. 大量數據處理測試
```javascript
test('應該能處理大量對話數據', async () => {
  const startTime = performance.now()
  
  // 創建 1000 個對話記錄
  const largeDataSet = Array(1000).fill().map((_, index) => ({
    id: index,
    name: `客戶${index}`,
    lastMessage: `訊息${index}`
  }))
  
  // 處理數據
  await processConversations(largeDataSet)
  
  const endTime = performance.now()
  const processingTime = endTime - startTime
  
  // 處理時間應該小於 1 秒
  expect(processingTime).toBeLessThan(1000)
})
```

### 2. API 響應時間測試
```php
public function test_api_response_time()
{
    Sanctum::actingAs($this->admin);
    
    $startTime = microtime(true);
    
    $response = $this->getJson('/api/chats');
    
    $endTime = microtime(true);
    $responseTime = ($endTime - $startTime) * 1000; // 轉換為毫秒
    
    $response->assertStatus(200);
    
    // API 響應時間應該小於 500ms
    $this->assertLessThan(500, $responseTime);
}
```

## 錯誤處理測試

### 1. 網路錯誤處理
```javascript
test('應該優雅地處理網路錯誤', async () => {
  const { getConversations } = useChat()
  
  // Mock 網路錯誤
  fetch.mockRejectedValueOnce(new Error('網路連接失敗'))
  
  try {
    await getConversations()
  } catch (error) {
    expect(error.message).toBe('網路連接失敗')
  }
  
  // 應用應該仍然可用
  expect(wrapper.vm.allUsers).toBeDefined()
})
```

### 2. 驗證錯誤處理
```php
public function test_reply_validation()
{
    Sanctum::actingAs($this->staff);
    
    // 測試空訊息
    $response = $this->postJson("/api/chats/{$this->customer->line_user_id}/reply", [
        'message' => ''
    ]);
    
    $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    
    // 測試過長訊息
    $longMessage = str_repeat('A', 1001);
    $response = $this->postJson("/api/chats/{$this->customer->line_user_id}/reply", [
        'message' => $longMessage
    ]);
    
    $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
}
```

## 執行測試

### 1. 自動化測試執行

#### 前端測試
```bash
# 執行所有前端測試
npm run test

# 執行聊天室特定測試
npm run test frontend/tests/chat/

# 監視模式（開發時使用）
npm run test:watch

# 生成詳細報告
npm run test:verbose
```

#### 後端測試
```bash
# 執行所有後端測試
php artisan test

# 執行聊天室特定測試
php artisan test --filter ChatController

# 平行執行測試（提高速度）
php artisan test --parallel

# 生成測試報告
php artisan test --coverage-html reports/
```

### 2. 持續整合 (CI) 設定

#### GitHub Actions 配置
```yaml
name: Chat Room Tests

on: [push, pull_request]

jobs:
  frontend-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - run: npm ci
        working-directory: ./frontend
      - run: npm run test
        working-directory: ./frontend

  backend-tests:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_PASSWORD: postgres
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
        working-directory: ./backend
      - run: php artisan test
        working-directory: ./backend
```

## 測試覆蓋率目標

### 目標覆蓋率
- **整體覆蓋率**: ≥ 90%
- **函數覆蓋率**: ≥ 95%
- **分支覆蓋率**: ≥ 85%
- **行覆蓋率**: ≥ 90%

### 關鍵組件覆蓋率
- `useChat.js`: ≥ 95%
- `ChatController.php`: ≥ 95%
- `ChatUserList.vue`: ≥ 90%
- `ChatMessageArea.vue`: ≥ 90%
- `chat/index.vue`: ≥ 85%

## 測試報告

### 生成測試報告
```bash
# 前端測試報告
npm run test:coverage
open coverage/index.html

# 後端測試報告
php artisan test --coverage-html reports/
open reports/index.html
```

### 報告內容
1. **測試執行摘要**
   - 總測試數量
   - 通過/失敗數量
   - 執行時間

2. **覆蓋率分析**
   - 文件級別覆蓋率
   - 函數級別覆蓋率
   - 未覆蓋程式碼區域

3. **性能分析**
   - 慢速測試識別
   - 記憶體使用情況
   - API 響應時間

## 問題排查指南

### 常見問題

#### 1. API 連接失敗
```bash
# 檢查後端服務是否運行
php artisan serve

# 檢查資料庫連接
php artisan migrate:status

# 檢查 API 路由
php artisan route:list | grep chat
```

#### 2. 權限測試失敗
```bash
# 重新運行 seeder
php artisan db:seed --class=RolesAndPermissionsSeeder

# 檢查用戶權限
php artisan tinker
>>> User::find(1)->roles
```

#### 3. 前端組件測試失敗
```bash
# 清除 npm 快取
npm cache clean --force

# 重新安裝依賴
rm -rf node_modules package-lock.json
npm install
```

### 除錯技巧

#### 1. 使用測試除錯
```javascript
// 在測試中添加除錯點
test('debug test', async () => {
  console.log('Debug info:', wrapper.vm.selectedUser)
  await wrapper.vm.loadConversations()
  // 使用 debugger 暫停執行
  debugger
})
```

#### 2. 後端 API 除錯
```php
// 在控制器中添加日誌
Log::info('Chat conversations loaded', ['count' => $conversations->count()]);

// 使用 dd() 除錯
dd($request->all());
```

## 最佳實踐

### 1. 測試設計原則
- **單一職責**: 每個測試只驗證一個功能點
- **獨立性**: 測試之間不相互依賴
- **可重複性**: 測試結果應該一致
- **易讀性**: 測試程式碼應該清晰易懂

### 2. Mock 使用指南
- 優先 mock 外部依賴
- 保持 mock 數據的真實性
- 適當使用 spy 驗證函數調用
- 清理 mock 狀態避免影響其他測試

### 3. 測試維護
- 定期更新測試用例
- 移除過時或重複的測試
- 保持測試與產品代碼同步更新
- 監控測試執行時間，優化慢速測試

## 結論

本測試文檔提供了聊天室功能的完整測試方案，涵蓋了前端、後端、整合測試和性能測試。通過執行這些測試用例，可以確保聊天室功能的穩定性、安全性和效能。

建議開發團隊：
1. 在每次程式碼變更後執行相關測試
2. 定期執行完整測試套件
3. 監控測試覆蓋率並持續改進
4. 將測試結果整合到 CI/CD 流程中

透過系統化的測試方法，可以提早發現問題，提高系統品質，確保使用者獲得良好的聊天體驗。