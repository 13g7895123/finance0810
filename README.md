# 融資貸款公司 CRM 系統

專為中租經銷商設計的融資貸款客戶關係管理系統，整合汽車、機車、手機貸款業務。

## 技術架構

- **前端**: Nuxt.js 3 + Tailwind CSS
- **後端**: Laravel 10
- **資料庫**: MySQL 8.0
- **快取**: Redis 7
- **容器化**: Docker + Docker Compose
- **CI/CD**: GitHub Actions

## 功能特色

### 🔐 權限分級管理
- **經銷商/公司高層**: 擁有系統所有權限
- **行政人員/主管**: 可編輯大部分資料，無法修改銀行交涉紀錄
- **業務人員**: 僅能編輯查詢自己負責的客戶資料

### 📊 資料整合與自動化
- 多管道資料匯入 (WordPress 網站表單、Line OA、Email)
- 自動同步 Line 顯示名稱與客戶資訊
- 網站名稱自定義編輯功能

### 💬 聊天室功能
- 整合 LINE BOT 機器人對話記錄
- 權限分級的使用者列表顯示
- 即時訊息傳送與接收

### 📈 統計報表
- 進件與撥款統計分析
- 會計財務報表生成
- 銀行交涉紀錄管理

## 快速開始

### 1. 環境要求
- Docker & Docker Compose
- Git

### 2. 複製專案
```bash
git clone <repository-url>
cd project
```

### 3. 環境設定
```bash
# 複製環境變數檔案
cp .env.example .env

# 編輯環境變數 (修改密碼等敏感資訊)
nano .env
```

### 4. 啟動服務
```bash
# 建構並啟動所有容器
docker-compose up -d

# 查看服務狀態
docker-compose ps
```

### 5. 初始化資料庫
```bash
# 執行資料庫遷移
docker-compose exec backend php artisan migrate

# 建立初始資料
docker-compose exec backend php artisan db:seed
```

## 服務端口

根據 `.env` 檔案設定：

- **前端應用**: http://localhost:3000
- **後端 API**: http://localhost:8000
- **資料庫管理**: http://localhost:8080 (phpMyAdmin)
- **MySQL**: localhost:3306
- **Redis**: localhost:6379

## 開發指令

### 前端開發
```bash
# 進入前端容器
docker-compose exec frontend sh

# 安裝套件
npm install

# 開發模式
npm run dev

# 建構生產版本
npm run build
```

### 後端開發
```bash
# 進入後端容器
docker-compose exec backend sh

# 安裝套件
composer install

# 執行測試
php artisan test

# 清除快取
php artisan cache:clear
php artisan config:clear
```

## 部署說明

### GitHub Actions 自動部署

專案包含完整的 CI/CD 流程：

1. **測試階段**: 自動執行前後端測試
2. **建構階段**: 建立 Docker 映像檔
3. **部署階段**: 自動部署到測試/生產環境

### 環境變數設定

在 GitHub Repository Settings > Secrets 中設定：

```
# 測試環境
STAGING_HOST=your-staging-server
STAGING_USER=deploy-user
STAGING_SSH_KEY=your-private-key

# 生產環境  
PRODUCTION_HOST=your-production-server
PRODUCTION_USER=deploy-user
PRODUCTION_SSH_KEY=your-private-key
PRODUCTION_URL=https://your-domain.com
DB_ROOT_PASSWORD=your-db-password

# 通知設定
SLACK_WEBHOOK=your-slack-webhook-url
```

## 資料庫結構

### 主要資料表
- `users`: 使用者帳號與權限
- `customers`: 客戶基本資料
- `cases`: 案件申請記錄
- `chat_messages`: 聊天室對話記錄
- `bank_negotiations`: 銀行交涉記錄

## API 文件

後端 API 文件位於：http://localhost:8000/api/documentation

## 安全性考量

- JWT Token 認證機制
- 三級權限控制
- Docker 容器隔離
- 定期安全性掃描 (Trivy)
- 資料庫定期備份

## 故障排除

### 常見問題

1. **容器無法啟動**
   ```bash
   # 檢查端口是否被占用
   netstat -tulpn | grep :3000
   
   # 重新建構容器
   docker-compose down
   docker-compose up --build
   ```

2. **資料庫連線失敗**
   ```bash
   # 檢查 MySQL 容器狀態
   docker-compose logs mysql
   
   # 重設資料庫
   docker-compose down -v
   docker-compose up -d
   ```

3. **前端編譯錯誤**
   ```bash
   # 清除 node_modules
   docker-compose exec frontend rm -rf node_modules
   docker-compose exec frontend npm install
   ```

## 授權條款

此專案採用 MIT 授權條款。