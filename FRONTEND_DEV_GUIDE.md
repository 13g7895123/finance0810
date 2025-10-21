# Frontend Development Server Guide

## Point 2: 前端開發伺服器啟動指南

本指南說明如何使用提供的腳本來啟動前端開發伺服器，並確保正確載入 `.env.development` 設定檔。

## 快速開始

### 方法 1: 使用完整啟動腳本（推薦）

執行完整的啟動腳本，包含所有檢查和設定：

```bash
./start-frontend-dev.sh
```

**特點：**
- ✅ 自動檢查 `.env.development` 是否存在
- ✅ 顯示當前環境變數設定（不含敏感資訊）
- ✅ 自動安裝依賴（如果 `node_modules` 不存在）
- ✅ 檢查 Node.js 和 npm 版本
- ✅ 檢查 port 3301 是否被佔用
- ✅ 如果 port 被佔用，詢問是否要終止現有程序
- ✅ 彩色輸出，清楚顯示各種狀態訊息

### 方法 2: 使用快速啟動腳本

如果你確定環境已經設定好，可使用簡化版本：

```bash
./dev.sh
```

**特點：**
- 快速啟動，最少的檢查
- 自動安裝依賴（如果需要）
- 適合日常開發使用

### 方法 3: 直接使用 npm

進入 frontend 目錄後直接執行：

```bash
cd frontend
npm run dev
```

## 環境設定

### .env.development 檔案內容

確保 `frontend/.env.development` 檔案包含以下設定：

```env
# 開發環境配置
NODE_ENV=development
NUXT_PUBLIC_API_BASE_URL=http://localhost:9221/api

# 啟用除錯模式
NUXT_DEBUG=true
NUXT_SKIP_AUTH=false

# Firebase Web SDK 配置
NUXT_FIREBASE_API_KEY=your_api_key_here
NUXT_FIREBASE_DATABASE_URL=your_database_url_here
NUXT_FIREBASE_MESSAGING_SENDER_ID=your_sender_id_here
NUXT_FIREBASE_APP_ID=your_app_id_here
```

### 開發伺服器資訊

- **Port:** 3301
- **Host:** 0.0.0.0（允許外部存取）
- **API Backend:** http://localhost:9221/api
- **前端 URL:**
  - Local: http://localhost:3301
  - Network: http://0.0.0.0:3301

## npm scripts 說明

在 `frontend/package.json` 中定義的可用指令：

```json
{
  "scripts": {
    "dev": "cross-env NODE_ENV=development nuxt dev --port 3301 --host 0.0.0.0",
    "dev:local": "cross-env NODE_ENV=development NUXT_PUBLIC_API_BASE_URL=http://localhost:9221/api nuxt dev --port 3301 --host localhost",
    "dev:prod-api": "cross-env NODE_ENV=development NUXT_PUBLIC_API_BASE_URL=https://dev-finance.mercylife.cc/api nuxt dev --port 3301 --host localhost",
    "build": "nuxt build",
    "build:prod": "cross-env NODE_ENV=production nuxt build",
    "preview": "nuxt preview"
  }
}
```

### 指令說明

- `npm run dev` - 啟動開發伺服器（預設，使用 .env.development）
- `npm run dev:local` - 本地開發模式（僅 localhost 存取）
- `npm run dev:prod-api` - 使用正式環境 API 進行前端開發
- `npm run build` - 建置生產版本
- `npm run preview` - 預覽建置結果

## 疑難排解

### Port 3301 已被佔用

如果看到 `Port 3301 is already in use` 錯誤：

**方法 1: 使用完整腳本自動處理**
```bash
./start-frontend-dev.sh
# 腳本會詢問是否終止現有程序
```

**方法 2: 手動終止程序**
```bash
# 找出佔用 port 的程序
lsof -i :3301

# 終止該程序
kill -9 <PID>
```

### 依賴安裝失敗

```bash
cd frontend
rm -rf node_modules package-lock.json
npm install
```

### .env.development 不存在

從 `.env.development.example` 複製（如果有）：
```bash
cp frontend/.env.development.example frontend/.env.development
```

或手動建立 `.env.development` 檔案並填入上述環境變數。

### Node.js 版本問題

本專案需要 Node.js 18.x 或更高版本。檢查版本：

```bash
node -v
npm -v
```

如需更新 Node.js，建議使用 nvm：
```bash
nvm install 18
nvm use 18
```

## 開發工作流程

1. **啟動後端服務**（如果尚未啟動）
   ```bash
   docker compose up -d
   ```

2. **啟動前端開發伺服器**
   ```bash
   ./start-frontend-dev.sh
   ```

3. **開啟瀏覽器**
   - 訪問 http://localhost:3301

4. **開始開發**
   - 修改程式碼後會自動熱重載
   - 查看終端機輸出以了解編譯狀態

5. **停止伺服器**
   - 按 `Ctrl + C`

## 進階設定

### 使用不同的 API 端點

編輯 `frontend/.env.development` 並修改：
```env
NUXT_PUBLIC_API_BASE_URL=http://your-api-url/api
```

或使用命令行覆蓋：
```bash
cd frontend
cross-env NUXT_PUBLIC_API_BASE_URL=http://custom-api/api npm run dev
```

### 變更開發伺服器 Port

修改 `frontend/package.json` 中的 `dev` script：
```json
"dev": "cross-env NODE_ENV=development nuxt dev --port 3301 --host 0.0.0.0"
```

將 `3301` 改為你想要的 port。

## 相關檔案

- `start-frontend-dev.sh` - 完整功能的啟動腳本
- `dev.sh` - 快速啟動腳本
- `frontend/.env.development` - 開發環境變數設定
- `frontend/package.json` - npm 指令定義
- `frontend/nuxt.config.ts` - Nuxt 設定檔

## 更多資訊

- [Nuxt 3 Documentation](https://nuxt.com/docs)
- [Project README](./README.md)
- [Development Setup Guide](./DEV_SETUP.md)
