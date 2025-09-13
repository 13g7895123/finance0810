# 開發環境設置指南

## Point 81 解決方案：VOLUME 掛載開發環境

### 問題分析
1. **500 錯誤原因**：Docker 服務未啟動，後端 API 無法在 port 9221 上運行
2. **COPY vs VOLUME**：
   - **COPY**（生產環境）：將代碼打包進 Docker 映像，適合部署
   - **VOLUME**（開發環境）：掛載本地代碼目錄，支援即時更新

### 解決方案實施

#### 1. Docker 開發環境（推薦）
已創建 `docker-compose.dev.yml` 使用 VOLUME 掛載：
- 後端代碼即時同步，無需重建映像
- 前端支援 hot-reload
- 完整的資料庫和 Redis 支援

**啟動步驟**：
```bash
# 1. 確保 Docker Desktop 已啟動
# 2. 執行開發腳本
cd script
./dev.bat

# 或直接使用 docker-compose
docker-compose -f docker-compose.dev.yml up -d
```

**服務端點**：
- 前端：http://localhost:3301
- 後端 API：http://localhost:9221
- phpMyAdmin：http://localhost:8080
- MySQL：localhost:3306
- Redis：localhost:6379

#### 2. 替代方案（無 Docker）
如果無法使用 Docker，可以：

**選項 A：使用遠端開發環境**
- 連接到 https://dev-finance.mercylife.cc/api
- 已配置 CORS 支援本地前端開發

**選項 B：安裝本地 PHP 環境**
1. 安裝 XAMPP 或 Laragon（包含 PHP、MySQL）
2. 在 backend 目錄執行：
   ```bash
   composer install
   php artisan serve --port=9221
   ```

### 檔案變更說明

#### docker-compose.dev.yml
- 新增 VOLUME 掛載配置
- 後端：`./backend:/var/www/html`
- 前端：`./frontend:/app`
- 保留 vendor 和 node_modules 在容器內

#### script/dev.bat
- 更新使用 docker-compose.dev.yml
- 簡化啟動流程
- 更新端口資訊

### 測試 API
```bash
# 測試後端是否正常運行
curl http://localhost:9221/api/websites?page=1&per_page=15

# 如果返回 500，檢查：
# 1. Docker 是否運行
# 2. 資料庫連接是否正常
# 3. Laravel 日誌：backend/storage/logs/laravel.log
```

### 常見問題

**Q: Docker 未啟動怎麼辦？**
A: 開啟 Docker Desktop，等待完全啟動後再執行 dev.bat

**Q: 為什麼要用 VOLUME？**
A: 開發環境使用 VOLUME 可以即時反映代碼變更，無需重建 Docker 映像

**Q: 生產環境應該用什麼？**
A: 生產環境應使用 COPY，將代碼打包進映像，確保部署一致性

### 下一步
1. 啟動 Docker Desktop
2. 執行 `script\dev.bat`
3. 訪問 http://localhost:3301 測試前端
4. 確認 API 正常運行