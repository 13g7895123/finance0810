# Point 5 完整解決方案 - Storage 目錄結構問題

**問題描述**：Commit 72d3417 推送到生產環境後仍出現容器重啟錯誤

**分析時間**：2025-10-22

---

## 問題回顧

### 時間線
1. **Commit eee82e5**：Docker 優化導致 RuntimeException: View path not found
2. **Commit 09d946e**：移除 Dockerfile 的 build-time cache clearing
3. **Commit 72d3417**：添加 config/view.php 的 realpath fallback
4. **生產環境**：推送 72d3417 後仍出現相同錯誤

### 為什麼本地正常但生產環境失敗？

#### 環境差異分析

**本地開發環境**：
```bash
# 在問題診斷過程中，執行了：
docker exec backend mkdir -p storage/framework/views
```
- Docker exec 在容器內創建目錄
- Volume mount 將目錄同步到主機
- **結果**：主機機器的 `backend/storage/framework/*` 目錄存在

**生產環境**：
```bash
# Git clone 流程：
git clone repo
cd project
git checkout develop
git pull
```
- Git **不追蹤空目錄**（即使有 .gitkeep）
- 主機機器的 `backend/storage/framework/views` 目錄不存在
- **結果**：Volume mount 時缺少目錄結構

#### Volume Mount 時序問題

```yaml
# docker-compose.yml
volumes:
  - ./backend:/var/www/html
  - ./backend/storage:/var/www/html/storage
```

**執行順序**：
1. Docker 啟動容器前，先 mount 主機目錄到容器
2. 主機的 `./backend/storage` mount 到容器的 `/var/www/html/storage`
3. 容器啟動時執行 command 中的 mkdir
4. **問題**：mkdir 在容器的 overlay 層創建目錄，但 mount 層已經覆蓋

**結果**：
```
主機: backend/storage/framework/ (空)
  ↓ volume mount 覆蓋
容器: /var/www/html/storage/framework/ (也是空)
  ↓ mkdir 執行
容器 overlay: /var/www/html/storage/framework/views (存在但不持久)
  ↓ realpath() 檢查 mount 層
返回: false (mount 層沒有這個目錄)
```

---

## 完整解決方案

### 方案 A：立即修復（需在生產服務器執行）

**適用場景**：目前生產環境已部署但仍出錯，需要立即修復

**執行步驟**：
```bash
# 1. SSH 連接到生產服務器
ssh -p 8022 user@server

# 2. 切換到部署目錄
cd project/bonus/develop/finance0810_D

# 3. 創建必要的 storage 目錄結構
mkdir -p backend/storage/framework/{views,sessions,cache,data,testing}
mkdir -p backend/storage/logs
mkdir -p backend/bootstrap/cache

# 4. 設置正確的權限
chmod -R 775 backend/storage backend/bootstrap/cache

# 5. 重啟後端容器
docker compose restart backend

# 6. 驗證修復
docker compose logs backend --tail 50
docker compose ps
```

**預期結果**：
```
backend container status: Up 1 minute (healthy)
HTTP response: 200 OK
```

### 方案 B：長期自動化（已實施）✅

**適用場景**：避免未來部署時再次出現此問題

**實施內容**：

修改 `.github/workflows/deploy.yml` (Commit 7d3c38b)：
```yaml
# 在 git pull 之後、docker compose 之前
# 確保必要的 storage 目錄結構存在
echo "========================================="
echo "📁 確保 Laravel storage 目錄結構..."
echo "========================================="
mkdir -p backend/storage/framework/{views,sessions,cache,data,testing}
mkdir -p backend/storage/logs
mkdir -p backend/bootstrap/cache
chmod -R 775 backend/storage backend/bootstrap/cache 2>/dev/null || true
echo "✅ Storage 目錄結構已就緒"
```

**優點**：
- 每次部署自動執行
- 無需手動干預
- 確保目錄結構一致性
- 預防性解決問題

**部署流程**：
```
git pull
  ↓
創建 storage 目錄 (NEW!)
  ↓
檢測文件變更
  ↓
docker compose up
  ↓
volume mount (目錄已存在)
  ↓
容器啟動成功
```

---

## 技術細節

### 為什麼 .gitkeep 無法解決問題？

```bash
# .gitkeep 的作用
storage/framework/views/.gitkeep    # 追蹤 views 目錄
storage/framework/sessions/.gitkeep # 追蹤 sessions 目錄
```

**問題**：
1. Git clone 時會創建 `storage/framework/views/` 目錄
2. 但 `.dockerignore` 排除了這些目錄：
   ```
   storage/framework/views/*
   !storage/framework/views/.gitkeep
   ```
3. 雖然 git 追蹤 `.gitkeep`，但在 Docker build context 中被排除
4. 最終主機機器上沒有這些子目錄

### realpath() 行為

```php
// backend/config/view.php (修復前)
'compiled' => realpath(storage_path('framework/views'))

// 當目錄不存在或在不同層時
realpath('/var/www/html/storage/framework/views')  // false

// backend/config/view.php (修復後 - commit 72d3417)
'compiled' => realpath(storage_path('framework/views'))
           ?: storage_path('framework/views')

// 提供 fallback
realpath('/var/www/html/storage/framework/views')  // false
  ↓ fallback
storage_path('framework/views')  // '/var/www/html/storage/framework/views'
```

**72d3417 的修復**：
- ✅ 解決了配置錯誤問題
- ✅ 本地環境（已有目錄）正常運行
- ❌ 但無法解決生產環境缺少目錄的根本問題

---

## 相關 Commits

| Commit | 說明 | 狀態 |
|--------|------|------|
| eee82e5 | Docker 優化，引入問題 | 已識別 |
| 09d946e | 移除 build-time cache clearing | 部分修復 |
| 72d3417 | 添加 realpath fallback | 修復配置 |
| **7d3c38b** | **自動化創建目錄結構** | **完整解決** |

---

## 驗證清單

### 生產環境修復後驗證（方案 A 執行後）

- [ ] SSH 到生產服務器並執行目錄創建命令
- [ ] 驗證目錄存在：`ls -la backend/storage/framework/`
- [ ] 驗證權限正確：`ls -ld backend/storage`（應顯示 drwxrwxr-x）
- [ ] 重啟容器：`docker compose restart backend`
- [ ] 檢查容器狀態：`docker compose ps backend`（應顯示 Up 且 healthy）
- [ ] 檢查容器日誌：`docker compose logs backend --tail 50`（無錯誤）
- [ ] HTTP 測試：`curl http://localhost:8000/api/health`（應返回 200）

### 下次部署驗證（方案 B 已生效）

在推送 commit 7d3c38b 後的下一次部署中觀察：

- [ ] GitHub Actions 日誌顯示「📁 確保 Laravel storage 目錄結構...」
- [ ] 日誌顯示「✅ Storage 目錄結構已就緒」
- [ ] 部署成功完成
- [ ] 容器正常啟動，無重啟循環
- [ ] 應用程式可正常訪問

---

## 總結

### 根本原因
生產環境的主機機器缺少 `storage/framework/` 子目錄，導致 volume mount 時容器內也缺少這些目錄。

### 完整解決方案
1. **立即修復**：SSH 到生產服務器手動創建目錄（方案 A）
2. **長期預防**：CI/CD 自動創建目錄結構（方案 B - 已實施）

### 關鍵學習
- Git 不追蹤空目錄，即使有 .gitkeep
- Volume mount 的時序很重要
- Docker build context 和 .dockerignore 的交互作用
- 生產環境和開發環境的差異需要仔細考慮
- realpath() 在處理新創建目錄時的行為

### 後續行動
1. 執行方案 A 修復當前生產環境
2. 推送 commit 7d3c38b 到遠端
3. 未來部署將自動包含目錄創建步驟
