# Point 6 完整解決方案 - Storage 目錄權限問題

**問題描述**：GitHub Actions 部署時出現權限錯誤
**錯誤訊息**：`mkdir: cannot create directory 'backend/storage/framework/testing': Permission denied`

**分析時間**：2025-10-22

---

## 問題背景

### 來龍去脈

在 Point 5 的解決方案中（Commit 7d3c38b），我們在 `deploy.yml` 中添加了自動創建 storage 目錄的邏輯：

```bash
mkdir -p backend/storage/framework/{views,sessions,cache,data,testing}
mkdir -p backend/storage/logs
mkdir -p backend/bootstrap/cache
```

**目的**：解決 Git 不追蹤空目錄導致生產環境缺少目錄結構的問題

**結果**：在部署時出現權限錯誤

---

## 問題根本原因分析

### 1. Docker 容器與主機的權限映射

```
Docker 容器內部                  主機系統
━━━━━━━━━━━━━━━━                ━━━━━━━━━━
www-data (UID: 82)    →  映射  →  82 (非 SSH 用戶)
/var/www/html/storage  ↔ mount ↔  ./backend/storage
```

**問題**：
- Docker 容器內的 `www-data` 用戶（UID 82）創建目錄
- 通過 volume mount，主機上的目錄擁有者也是 UID 82
- SSH 部署用戶（例如 `jarvis`）沒有權限修改這些目錄

### 2. 部署流程與權限衝突

**第一次部署**（容器不存在）：
```bash
# SSH 用戶成功創建目錄
mkdir -p backend/storage/framework/views  # ✅ 成功，擁有者: jarvis
docker compose up  # 容器啟動，chown 改為 www-data
```

**第二次部署**（容器已存在）：
```bash
# 目錄已由 Docker 創建，擁有者是 www-data (UID 82)
ls -la backend/storage/framework/
# drwxrwxr-x 82 82 ... views/
# drwxrwxr-x 82 82 ... sessions/

# SSH 用戶嘗試創建新子目錄
mkdir -p backend/storage/framework/testing  # ❌ Permission denied
# 原因：父目錄 framework/ 擁有者是 UID 82，SSH 用戶無寫入權限
```

### 3. 為什麼 docker-compose.yml 缺少這些目錄？

查看 docker-compose.yml line 63（修復前）：
```yaml
mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions
```

**缺少**：
- `storage/framework/data`
- `storage/framework/testing`

這些是 Laravel 10+ 需要的完整目錄結構，但之前的配置沒有包含。

---

## 完整解決方案

### 策略：多層防護 + 優雅降級

```
┌─────────────────────────────────────────────────┐
│  第一層：SSH 用戶嘗試創建（首次部署/有權限時）    │
│  ↓ 如果失敗                                      │
│  第二層：Docker 容器創建（volume mount 後）      │
│  ↓                                              │
│  結果：確保目錄存在，無論哪種方式               │
└─────────────────────────────────────────────────┘
```

### 修改 1：docker-compose.yml

**位置**：`docker-compose.yml:63`

**修改前**：
```yaml
mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/logs bootstrap/cache
```

**修改後**：
```yaml
mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/framework/data storage/framework/testing storage/logs bootstrap/cache
```

**作用**：
- ✅ 確保容器啟動時創建所有必要的子目錄
- ✅ 作為主要的目錄創建機制（因為容器有完整權限）

### 修改 2：backend/docker/startup.sh

**位置**：`backend/docker/startup.sh:11-17`

**添加**：
```bash
mkdir -p storage/framework/data
mkdir -p storage/framework/testing
```

**作用**：
- ✅ 當 Dockerfile CMD 執行時也創建完整結構
- ✅ 與 docker-compose.yml 保持一致

### 修改 3：.github/workflows/deploy.yml

**位置**：`.github/workflows/deploy.yml:110-137`

**修改前**（會導致錯誤）：
```bash
mkdir -p backend/storage/framework/{views,sessions,cache,data,testing}
mkdir -p backend/storage/logs
mkdir -p backend/bootstrap/cache
chmod -R 775 backend/storage backend/bootstrap/cache 2>/dev/null || true
```

**修改後**（優雅錯誤處理）：
```bash
# 嘗試創建目錄，如果失敗（權限問題）則跳過，Docker 容器會處理
if mkdir -p backend/storage/framework/{views,sessions,cache,data,testing} 2>/dev/null; then
  echo "✅ 成功創建 storage/framework 子目錄"
else
  echo "⚠️  無法創建 storage/framework 子目錄（可能已存在或權限限制），Docker 容器將處理"
fi

if mkdir -p backend/storage/logs 2>/dev/null; then
  echo "✅ 成功創建 storage/logs"
else
  echo "⚠️  無法創建 storage/logs（可能已存在）"
fi

if mkdir -p backend/bootstrap/cache 2>/dev/null; then
  echo "✅ 成功創建 bootstrap/cache"
else
  echo "⚠️  無法創建 bootstrap/cache（可能已存在）"
fi

# 嘗試設置權限，如果失敗則跳過
chmod -R 775 backend/storage backend/bootstrap/cache 2>/dev/null || echo "⚠️  無法修改權限（將由 Docker 容器處理）"

echo "✅ Storage 目錄結構檢查完成"
```

**改進點**：
1. ✅ 不再因權限問題導致部署失敗
2. ✅ 提供清晰的成功/警告訊息
3. ✅ 首次部署時能創建目錄（提前優化）
4. ✅ 後續部署時優雅降級（依賴 Docker）

---

## 技術細節

### 為什麼不使用 sudo？

**選項 A：使用 sudo**
```bash
sudo mkdir -p backend/storage/framework/testing
```

**問題**：
- ❌ 需要 SSH 用戶有 sudo 權限（安全風險）
- ❌ 可能需要配置 NOPASSWD（更大的安全風險）
- ❌ 不同環境的 sudo 配置可能不一致
- ❌ 違反最小權限原則

**選項 B：優雅降級（已採用）**
```bash
if mkdir -p ...; then
  # 成功
else
  # 失敗但不中斷部署
fi
```

**優點**：
- ✅ 不需要額外權限
- ✅ 自動適應不同環境
- ✅ 保持安全性
- ✅ Docker 容器會確保目錄存在

### 為什麼不在容器啟動前用 docker exec？

```bash
# 理論方案
docker compose up -d backend  # 先啟動容器
docker exec backend mkdir -p storage/framework/testing  # 再創建目錄
```

**問題**：
- ❌ 容器可能還在初始化，exec 會失敗
- ❌ 增加部署時間（需要等待容器啟動）
- ❌ 如果容器啟動失敗，exec 無法執行
- ❌ 不如直接在容器啟動命令中處理

### Volume Mount 的時序

```
部署流程：
1. git pull                          # 更新代碼
2. [嘗試] mkdir on host             # SSH 用戶嘗試創建目錄
3. docker compose up
   ├─ 3.1 Volume mount               # ./backend/storage → /var/www/html/storage
   ├─ 3.2 Container command          # 容器內 mkdir + chown
   └─ 3.3 Application start          # php artisan serve

關鍵：
- 步驟 2 失敗也沒關係，步驟 3.2 會創建
- 步驟 3.2 一定成功（容器內有完整權限）
```

---

## 測試場景

### 場景 1：首次部署（全新環境）

**環境**：backend/storage/ 目錄不存在

**執行流程**：
```bash
# 1. SSH 用戶創建目錄
mkdir -p backend/storage/framework/{views,sessions,cache,data,testing}
# ✅ 成功，擁有者：jarvis

# 2. Docker 容器啟動
docker compose up -d backend
# 容器內執行：chown -R www-data:www-data /var/www/html/storage
# ✅ 目錄擁有者變更為 www-data (UID 82)

# 結果：✅ 目錄存在且權限正確
```

### 場景 2：第二次部署（目錄已存在）

**環境**：backend/storage/framework/ 擁有者是 www-data (UID 82)

**執行流程**：
```bash
# 1. SSH 用戶嘗試創建新子目錄
mkdir -p backend/storage/framework/testing 2>/dev/null
# ❌ Permission denied (但不會中斷部署)
# 輸出：⚠️  無法創建 storage/framework 子目錄...

# 2. Docker 容器啟動
docker compose up -d backend
# 容器內執行：mkdir -p storage/framework/testing
# ✅ 成功創建（容器有權限）

# 結果：✅ 目錄存在且權限正確
```

### 場景 3：容器已在運行（熱部署）

**環境**：容器已經運行，只是更新代碼

**執行流程**：
```bash
# 1. git pull（代碼更新）
# 2. SSH 用戶嘗試創建目錄（可能失敗，但不影響）
# 3. docker compose up（檢測到已運行，可能不重啟）

# 結果：✅ 目錄在之前的部署中已創建，仍然可用
```

---

## 部署流程對比

### 修復前（會失敗）

```
┌──────────────────────────────────┐
│ 1. git pull                      │
├──────────────────────────────────┤
│ 2. mkdir backend/storage/...    │
│    └─ ❌ Permission denied       │
│       └─ 💥 部署中斷             │
└──────────────────────────────────┘
```

### 修復後（不會失敗）

```
┌──────────────────────────────────┐
│ 1. git pull                      │
├──────────────────────────────────┤
│ 2. if mkdir backend/storage/...  │
│    ├─ ✅ 成功 → 繼續              │
│    └─ ⚠️  失敗 → 輸出警告，繼續   │
├──────────────────────────────────┤
│ 3. docker compose up             │
│    └─ ✅ 容器創建目錄             │
├──────────────────────────────────┤
│ 4. Application running           │
│    └─ ✅ 部署成功                 │
└──────────────────────────────────┘
```

---

## 相關 Commits

| Commit | 說明 | 狀態 |
|--------|------|------|
| 7d3c38b | Point 5: 添加自動目錄創建（引入權限問題） | 已識別 |
| **f4ed168** | **Point 6: 修復權限問題 + 完善目錄結構** | **已完成** |

---

## 驗證清單

### 開發環境驗證

- [ ] 清除本地 backend/storage/framework/ 目錄
- [ ] 執行 `git pull` 獲取最新變更
- [ ] 執行 `docker compose down && docker compose up -d`
- [ ] 檢查目錄是否正確創建：`ls -la backend/storage/framework/`
- [ ] 檢查應用是否正常啟動：`docker compose logs backend`
- [ ] 驗證 HTTP 訪問：`curl http://localhost:8000/api/health`

### 生產環境驗證

部署後觀察 GitHub Actions 日誌：

**首次部署（全新環境）**：
```
✅ 成功創建 storage/framework 子目錄
✅ 成功創建 storage/logs
✅ 成功創建 bootstrap/cache
✅ Storage 目錄結構檢查完成
```

**後續部署（目錄已存在）**：
```
⚠️  無法創建 storage/framework 子目錄（可能已存在或權限限制），Docker 容器將處理
⚠️  無法創建 storage/logs（可能已存在）
⚠️  無法創建 bootstrap/cache（可能已存在）
⚠️  無法修改權限（將由 Docker 容器處理）
✅ Storage 目錄結構檢查完成
```

**關鍵驗證點**：
- [ ] 部署流程沒有因權限錯誤而中斷
- [ ] GitHub Actions 顯示 "✅ Storage 目錄結構檢查完成"
- [ ] 容器成功啟動（`docker compose ps` 顯示 Up）
- [ ] 應用正常運行（HTTP 200 響應）

---

## 與 Point 5 的關聯

| 問題點 | Point 5 | Point 6 |
|--------|---------|---------|
| **問題** | 目錄不存在 | 無權限創建目錄 |
| **原因** | Git 不追蹤空目錄 | Docker 擁有目錄 |
| **解決** | 部署時創建目錄 | 優雅降級處理 |
| **層級** | 主機缺少目錄 | 權限衝突 |

**演進過程**：
1. **原始問題**：容器重啟循環（Point 2-3）
2. **優化導致**：Docker build 流程優化（Point 1）
3. **首次修復**：添加 realpath fallback（Commit 72d3417）
4. **根因修復**：自動創建目錄結構（Commit 7d3c38b - Point 5）
5. **權限問題**：mkdir 權限被拒絕（Point 6）
6. **完整解決**：多層防護 + 優雅降級（Commit f4ed168）

---

## 經驗總結

### 關鍵學習

1. **Docker 與主機的權限隔離**
   - 容器內的用戶（www-data）在主機上是數字 UID
   - Volume mount 會保留所有權信息
   - SSH 用戶與容器用戶的權限衝突是常見問題

2. **多層防護設計**
   - 不要依賴單一故障點
   - 提供主要方案 + 備用方案
   - 確保任一環節失敗都不影響整體

3. **優雅錯誤處理**
   - 使用 `if ... then ... else ...` 而非 `||` 簡單跳過
   - 提供清晰的成功/警告訊息
   - 讓運維人員理解發生了什麼

4. **避免過度依賴特權**
   - sudo 不是解決方案，是新的安全風險
   - 利用 Docker 的隔離特性
   - 讓容器管理自己的文件系統

### 最佳實踐

1. **目錄創建時機**
   ```
   優先級：容器啟動命令 > 主機預創建 > 應用運行時
   ```

2. **權限管理**
   ```
   原則：容器內管理容器資源，主機不越權
   ```

3. **錯誤處理**
   ```
   策略：嘗試 → 降級 → 記錄 → 繼續
   ```

4. **部署腳本設計**
   ```
   核心：冪等性 + 容錯性 + 可觀測性
   ```

---

## 後續建議

### 短期行動
1. ✅ 推送 commit f4ed168 到生產環境
2. ✅ 觀察下次部署的日誌輸出
3. ✅ 驗證容器啟動無誤

### 長期優化
1. **考慮使用 Docker volumes 而非 bind mounts**
   ```yaml
   volumes:
     - storage_data:/var/www/html/storage
   ```
   優點：Docker 完全管理，無主機權限問題

2. **添加健康檢查**
   ```yaml
   healthcheck:
     test: ["CMD", "test", "-d", "/var/www/html/storage/framework/views"]
     interval: 10s
   ```
   確保目錄結構在容器啟動後存在

3. **監控部署日誌**
   定期檢查是否有 "⚠️  無法創建" 訊息
   如果頻繁出現，考慮調整權限策略

---

## 總結

**問題**：`mkdir: cannot create directory 'backend/storage/framework/testing': Permission denied`

**根本原因**：SSH 用戶無權修改 Docker 容器創建的目錄

**解決方案**：
1. ✅ Docker 容器創建所有必要目錄（主要方案）
2. ✅ SSH 用戶嘗試創建但允許失敗（備用方案）
3. ✅ 詳細的日誌輸出（可觀測性）

**結果**：
- 部署不再因權限問題失敗
- 無論環境狀態如何都能正確創建目錄
- 保持安全性，不依賴 sudo 或特殊權限

**測試狀態**：已提交 (Commit f4ed168)，待生產環境驗證
