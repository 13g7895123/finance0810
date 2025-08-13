# 開發環境使用說明

## 啟動開發伺服器

### 基本啟動（推薦）
```bash
npm run dev
```
- **自動環境檢測**：開發環境自動使用 `http://finance.local/api`
- **生產環境保護**：不會影響正式環境設定
- 預設使用 port 3000，如果被佔用會自動找其他可用 port

### 不同 API 環境

#### 強制使用本地 API
```bash
npm run dev:local
```
- API URL: http://finance.local/api
- 適合：本地 Docker 開發環境

#### 使用生產 API（測試用）
```bash
npm run dev:prod-api
```
- API URL: https://dev-finance.mercylife.cc/api
- 適合：前端開發但使用遠端 API 測試

## 智能環境檢測

系統會自動根據以下順序決定 API 基礎 URL：

1. **環境變數優先**：`NUXT_PUBLIC_API_BASE_URL`
2. **開發環境檢測**：NODE_ENV=development 時使用 `http://finance.local/api`
3. **生產環境預設**：使用 `https://dev-finance.mercylife.cc/api`

## 環境設定檔說明

### .env.development
開發環境專用設定檔，`npm run dev` 時自動載入

### .env.production
生產環境設定檔，`npm run build` 時使用

### .env
通用設定檔，作為備援設定

## 故障排除

### URL 拼接錯誤
如果出現類似 `http://localhost:3002https://dev-finance.mercylife.cc` 的錯誤 URL：
1. 檢查 .env 檔案中是否有 `NUXT_APP_BASE_URL` 設定
2. 移除該設定或確保格式正確
3. 重新啟動開發伺服器

### Cross-env 錯誤
如果出現 `'cross-env' 不是內部或外部命令` 錯誤：
```bash
npm install cross-env --save-dev
```

### Port 被佔用
開發伺服器會自動尋找可用的 port，通常從 3000 開始嘗試。