# Point 7 完整解決方案 - vendor/autoload.php Fatal Error

**問題描述**：容器啟動時無法找到 Composer autoloader
**錯誤訊息**：`Fatal error: Uncaught Error: Failed opening required '/var/www/html/vendor/autoload.php' (include_path='.:/usr/local/lib/php') in /var/www/html/artisan:18`

**分析時間**：2025-10-22

---

## 問題背景

在完成 Point 1-6 的所有修復並推送到生產環境後，容器啟動時出現新的 Fatal Error，Laravel 無法找到 Composer 的 autoloader 文件。

這是一個**典型的 Docker volume mount 覆蓋問題**，在開發環境中很常見，但往往被忽視。

---

## 問題根本原因分析

### 1. 配置文件審查

#### **Dockerfile (正常)**

```dockerfile
# Line 48-60
COPY composer.json composer.lock ./

RUN COMPOSER_MEMORY_LIMIT=-1 COMPOSER_PROCESS_TIMEOUT=900 \
    composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --ignore-platform-req=ext-grpc \
        --prefer-dist \
        --no-scripts && \
    composer clear-cache

COPY . .
```

**分析**：
- ✅ 在 Docker build 時正確執行了 `composer install`
- ✅ 創建了 `/var/www/html/vendor/` 目錄
- ✅ 安裝了所有 PHP 依賴包

#### **.dockerignore (正常)**

```
# Line 1-3
vendor/
node_modules/
```

**分析**：
- ✅ 排除 `vendor/` 避免將本地依賴複製到 Docker image
- ✅ 這是標準做法，確保使用容器內安裝的乾淨版本

#### **docker-compose.yml (問題所在)**

```yaml
# Line 54-59 (修復前)
volumes:
  - ./backend:/var/www/html              # 💥 問題
  - ./backend/storage:/var/www/html/storage
  - backend_bootstrap_cache:/var/www/html/bootstrap/cache
```

**分析**：
- ❌ `./backend:/var/www/html` 將主機的整個 backend 目錄 mount 到容器
- ❌ 這會**完全覆蓋**容器內的 `/var/www/html` 目錄
- ❌ 包括已經安裝的 `vendor/` 目錄也被覆蓋
- ❌ 主機的 `./backend/vendor/` 不存在（被 .dockerignore 排除）
- ❌ 結果：容器內找不到 `vendor/autoload.php`

### 2. Volume Mount 機制詳解

#### **Docker Volume 的覆蓋行為**

```
容器內文件系統（Mount 前）:
/var/www/html/
├── app/
├── config/
├── vendor/           ← 由 composer install 創建
│   └── autoload.php
└── artisan

Volume Mount: ./backend → /var/www/html

主機文件系統:
./backend/
├── app/
├── config/
├── vendor/           ← 不存在（.dockerignore 排除）
└── artisan

容器內文件系統（Mount 後）:
/var/www/html/        ← 完全替換為主機的內容
├── app/
├── config/
├── vendor/           ← 💥 不存在！
└── artisan
```

#### **為什麼會完全覆蓋？**

當你使用 volume mount 時：
```yaml
volumes:
  - ./backend:/var/www/html
```

Docker 會：
1. 將主機的 `./backend` 目錄**完全替換**容器內的 `/var/www/html`
2. 容器內原有的內容會被**隱藏**（不是刪除，只是不可見）
3. 你看到的是主機的文件系統，而不是容器內的

### 3. 時序分析

```
┌─────────────────────────────────────────────────────────┐
│  階段 1: Docker Build (構建 Image)                       │
├─────────────────────────────────────────────────────────┤
│  1. FROM php:8.2-fpm-alpine                             │
│  2. COPY composer.json composer.lock ./                 │
│  3. RUN composer install                                │
│     └─ 創建 /var/www/html/vendor/ ✅                     │
│  4. COPY . .                                             │
│     └─ 複製其他文件（不含 vendor/，因 .dockerignore）   │
│  5. Image 構建完成                                      │
│     └─ Image 內包含完整的 vendor/ 目錄 ✅                │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  階段 2: Docker Compose Up (啟動容器)                   │
├─────────────────────────────────────────────────────────┤
│  1. 從 Image 創建容器                                   │
│     └─ 容器內有 /var/www/html/vendor/ ✅                 │
│  2. 應用 Volume Mount                                   │
│     └─ ./backend → /var/www/html                        │
│        ├─ 主機的 backend/ 覆蓋容器的 /var/www/html/     │
│        └─ 💥 vendor/ 被主機的（空的）版本覆蓋           │
│  3. 執行 command 啟動應用                               │
│     └─ php artisan serve --host=0.0.0.0 --port=8000     │
│  4. php 嘗試載入 vendor/autoload.php                    │
│     └─ ❌ Fatal error: File not found                    │
└─────────────────────────────────────────────────────────┘
```

### 4. 為什麼之前沒有這個問題？

**可能的原因**：

1. **本地開發時**：
   - 開發者本地的 `./backend/vendor/` 目錄存在
   - 因為開發者在主機上執行過 `composer install`
   - 所以 volume mount 時，主機的 vendor/ 覆蓋容器的 vendor/，但都能用

2. **生產環境**：
   - Git clone 後，主機上沒有 `vendor/` 目錄
   - .gitignore 通常包含 `vendor/`
   - Volume mount 時，主機的空目錄覆蓋容器的已安裝版本
   - 導致 Fatal Error

3. **Point 1 的優化可能觸發**：
   - 在 Point 1 中我們優化了 Dockerfile
   - 可能改變了一些構建邏輯
   - 使這個潛在問題顯現出來

---

## 解決方案

### 方案對比

#### **方案 A：排除 vendor 目錄（推薦）✅**

```yaml
volumes:
  - ./backend:/var/www/html
  - /var/www/html/vendor  # 排除 vendor，使用容器內的版本
```

**優點**：
- ✅ 簡單有效，只需一行配置
- ✅ 保持代碼熱更新能力
- ✅ 依賴包來自容器，確保一致性
- ✅ 標準做法，被廣泛採用

**缺點**：
- ⚠️ 如果需要更新依賴，需要重建容器

#### **方案 B：容器啟動時安裝依賴**

```yaml
command: |
  sh -c "
    composer install --no-dev --no-interaction
    php artisan serve ...
  "
```

**優點**：
- ✅ 可以使用主機的 composer.lock
- ✅ 不需要重建容器就能更新依賴

**缺點**：
- ❌ 每次容器重啟都要重新安裝（慢）
- ❌ 增加啟動時間（1-3 分鐘）
- ❌ 需要網路連接和 Composer 倉庫可用
- ❌ 違反 Docker 不可變基礎設施原則

#### **方案 C：只 mount 特定目錄**

```yaml
volumes:
  - ./backend/app:/var/www/html/app
  - ./backend/config:/var/www/html/config
  - ./backend/routes:/var/www/html/routes
  # ... 需要熱更新的每個目錄
```

**優點**：
- ✅ 完全控制哪些目錄被 mount
- ✅ 不會意外覆蓋容器內的文件

**缺點**：
- ❌ 配置複雜，需要列出所有目錄
- ❌ 新增目錄時容易遺漏
- ❌ 維護成本高

### 採用方案 A：排除 vendor 目錄

**修改內容**：

```yaml
# docker-compose.yml
volumes:
  # Mount entire backend code for live updates
  - ./backend:/var/www/html
  # Exclude vendor directory to use container's installed dependencies
  - /var/www/html/vendor  # ← 新增這一行
  # Persistent storage
  - ./backend/storage:/var/www/html/storage
  - backend_bootstrap_cache:/var/www/html/bootstrap/cache
```

**工作原理**：

利用 Docker 的 **Volume 優先級機制**：

```
規則：更具體的路徑優先級更高

Volume 聲明:
1. - ./backend:/var/www/html              # 優先級: 低（通用路徑）
2. - /var/www/html/vendor                 # 優先級: 高（具體路徑）

結果:
- /var/www/html/app      → 來自主機（被 mount 1 覆蓋）
- /var/www/html/config   → 來自主機（被 mount 1 覆蓋）
- /var/www/html/vendor   → 來自容器（被 mount 2 保護）✅
```

**Anonymous Volume 機制**：

```yaml
- /var/www/html/vendor  # 這會創建一個 anonymous volume
```

當沒有指定主機路徑（`:`左側），Docker 會：
1. 創建一個匿名卷
2. 將容器內該路徑的內容複製到卷中
3. 將卷 mount 到該路徑
4. 該路徑不會被上層的 mount 覆蓋

---

## 技術細節

### 1. Docker Volume 的三種類型

#### **Named Volume**
```yaml
volumes:
  - mysql_data:/var/lib/mysql
```
- 由 Docker 管理
- 數據持久化
- 可以在容器間共享

#### **Bind Mount**
```yaml
volumes:
  - ./backend:/var/www/html
```
- 直接映射主機路徑
- 用於開發時熱更新
- 主機內容覆蓋容器內容

#### **Anonymous Volume**
```yaml
volumes:
  - /var/www/html/vendor
```
- 由 Docker 自動管理
- 保護容器內的目錄不被 bind mount 覆蓋
- 容器刪除時可以選擇保留或刪除

### 2. Volume 優先級規則

```yaml
volumes:
  - ./backend:/var/www/html              # 1. Bind mount
  - /var/www/html/vendor                 # 2. Anonymous volume
  - ./backend/storage:/var/www/html/storage  # 3. Bind mount (更具體)
```

**優先級排序**（從高到低）：
1. **最具體的路徑** - `/var/www/html/storage` (mount 3)
2. **次具體的路徑** - `/var/www/html/vendor` (mount 2)
3. **通用路徑** - `/var/www/html` (mount 1)

**實際效果**：
```
/var/www/html/              ← 來自 mount 1 (主機)
├── app/                    ← 來自 mount 1 (主機)
├── config/                 ← 來自 mount 1 (主機)
├── vendor/                 ← 來自 mount 2 (容器) ✅
│   └── autoload.php
├── storage/                ← 來自 mount 3 (主機)
└── artisan                 ← 來自 mount 1 (主機)
```

### 3. 為什麼不在 Dockerfile 中使用 VOLUME？

```dockerfile
# 不推薦這樣做
VOLUME ["/var/www/html/vendor"]
```

**問題**：
- VOLUME 指令創建的是掛載點，不是保護機制
- 每次容器重啟，Docker 可能創建新的匿名卷
- 導致之前安裝的依賴丟失
- 需要重新執行 composer install

**正確做法**：
- 在 docker-compose.yml 中聲明 volume
- 讓 Docker Compose 管理卷的生命週期

### 4. 開發與生產的最佳實踐

#### **開發環境**

```yaml
# docker-compose.dev.yml
volumes:
  - ./backend:/var/www/html
  - /var/www/html/vendor
  - /var/www/html/node_modules  # 如果有前端構建
```

**特點**：
- 代碼熱更新
- 依賴包來自容器
- 快速迭代開發

#### **生產環境**

```yaml
# docker-compose.prod.yml
# 不使用 bind mount
# 所有內容都來自 Docker image
```

**特點**：
- 不可變基礎設施
- 無主機依賴
- 版本一致性

---

## 驗證測試

### 測試場景 1：本地開發環境

**前置條件**：
- 主機上有 `./backend/vendor/` 目錄（本地開發時安裝的）

**修復前**：
```bash
docker compose up -d
# 容器啟動，使用主機的 vendor/
# 如果主地的 vendor/ 是舊版本 → 可能有版本衝突
```

**修復後**：
```bash
docker compose up -d
# 容器啟動，使用容器的 vendor/（忽略主機的版本）
# 依賴版本與 composer.lock 一致 ✅
```

### 測試場景 2：生產環境（Git Clone）

**前置條件**：
- 主機上沒有 `./backend/vendor/` 目錄

**修復前**：
```bash
git clone ...
cd project
docker compose up -d
# ❌ Fatal error: vendor/autoload.php not found
```

**修復後**：
```bash
git clone ...
cd project
docker compose up -d
# ✅ 容器正常啟動
# 使用 Docker image 中的 vendor/
```

### 測試場景 3：更新依賴

**場景**：需要安裝新的 Composer 包

**操作步驟**：
```bash
# 1. 更新 composer.json
vim backend/composer.json

# 2. 重建容器（觸發 composer install）
docker compose up -d --build backend

# 3. 新的依賴會在 build 時安裝
# 4. 容器啟動時使用新的 vendor/
```

**注意**：
- 不能在主機上執行 `composer require xxx`
- 必須重建 Docker image
- 或者臨時進入容器：`docker compose exec backend composer require xxx`

---

## 常見問題 (FAQ)

### Q1: 為什麼不直接在主機上安裝依賴？

**A**:
- ❌ 主機環境可能與容器環境不同（PHP 版本、擴展）
- ❌ 依賴可能有平台相關的二進制文件
- ❌ 違反"開發環境與生產環境一致"的原則
- ✅ 應該讓 Docker 管理所有依賴

### Q2: 如何在容器內執行 Composer 命令？

**A**:
```bash
# 進入容器
docker compose exec backend sh

# 執行 Composer 命令
composer require vendor/package
composer update
composer dump-autoload

# 或者直接執行
docker compose exec backend composer require vendor/package
```

### Q3: 為什麼不在 docker-compose command 中執行 composer install？

**A**:
- ❌ 每次容器重啟都要重新安裝（慢）
- ❌ 網路問題會導致容器啟動失敗
- ❌ 違反 Docker 不可變基礎設施原則
- ✅ 應該在 Dockerfile 中安裝，build 時確定

### Q4: 如果我需要頻繁更新依賴怎麼辦？

**A**: 有兩種方式

**方式 1：進入容器安裝（開發時）**
```bash
docker compose exec backend composer require new/package
# 安裝後立即可用，但容器重啟後會丟失
# 記得更新 composer.json 並重建 image
```

**方式 2：重建容器（推薦）**
```bash
# 更新 composer.json
# 重建 image
docker compose up -d --build backend
# 新依賴會永久保存在 image 中
```

### Q5: Anonymous volume 的數據會持久化嗎？

**A**:
```bash
# 查看 anonymous volumes
docker volume ls | grep backend

# 容器刪除時
docker compose down           # Volume 保留
docker compose down -v        # Volume 刪除

# 對於 vendor/，刪除也沒關係
# 下次啟動時會從 image 中重新複製
```

### Q6: 如何確認 vendor 來自容器而不是主機？

**A**:
```bash
# 方法 1：檢查文件修改時間
docker compose exec backend ls -la /var/www/html/vendor/
# 如果是 build 時的時間 → 來自容器 ✅
# 如果是最近的時間 → 可能來自主機 ❌

# 方法 2：刪除主機的 vendor 測試
rm -rf ./backend/vendor/
docker compose restart backend
# 如果容器仍能啟動 → 來自容器 ✅

# 方法 3：檢查 mount 點
docker inspect backend_container | grep -A 20 "Mounts"
# 應該看到 /var/www/html/vendor 是獨立的 volume
```

---

## 相關問題與演進

### Point 1-7 的關聯

| Point | 問題 | 根源 |
|-------|------|------|
| 1 | CD 太慢 | 初始配置未優化 |
| 2 | 容器重啟 | Point 1 優化引入 |
| 3 | 找出錯誤 commit | 追溯 Point 1 |
| 4 | 確認修復 | Point 2-3 驗證 |
| 5 | 目錄不存在 | Volume mount 機制 |
| 6 | 權限被拒絕 | Point 5 副作用 |
| **7** | **vendor 找不到** | **Volume mount 覆蓋** |

### 從 Point 1 到 Point 7 的演進

```
Point 1: Docker 優化
  ├─ 優化 .dockerignore (排除 vendor/)
  ├─ 優化 Dockerfile (調整 COPY 順序)
  └─ 💡 引入潛在的 volume mount 問題

Point 2-4: RuntimeException 修復
  ├─ 問題: View path not found
  ├─ 修復: realpath fallback
  └─ 狀態: 本地解決

Point 5: 生產環境仍錯誤
  ├─ 發現: 主機缺少目錄
  ├─ 修復: 自動創建目錄
  └─ 學習: Volume mount 會覆蓋容器

Point 6: 權限問題
  ├─ 問題: mkdir Permission denied
  ├─ 修復: 優雅降級處理
  └─ 學習: SSH 用戶 vs Docker 用戶

Point 7: vendor 找不到 ⬅ 我們在這裡
  ├─ 問題: autoload.php not found
  ├─ 根源: Volume mount 覆蓋 vendor/
  ├─ 修復: 排除 vendor 目錄
  └─ 學習: Volume 優先級機制
```

---

## 相關 Commits

| Commit | 說明 | 相關問題 |
|--------|------|----------|
| eee82e5 | Docker 優化 | Point 1, 埋下 Point 2-7 的種子 |
| 72d3417 | realpath fallback | Point 2-4 |
| 7d3c38b | 自動創建目錄 | Point 5 |
| f4ed168 | 權限問題修復 | Point 6 |
| **da1c290** | **排除 vendor 目錄** | **Point 7** ✅ |

---

## 最佳實踐總結

### ✅ 推薦做法

1. **依賴安裝**
   - 在 Dockerfile 中執行 `composer install`
   - 不在容器啟動時安裝

2. **.dockerignore 配置**
   ```
   vendor/
   node_modules/
   ```
   - 排除依賴目錄
   - 避免複製到 image

3. **docker-compose.yml 配置**
   ```yaml
   volumes:
     - ./backend:/var/www/html
     - /var/www/html/vendor      # 保護容器內的依賴
   ```
   - 排除被覆蓋的目錄

4. **更新依賴流程**
   ```bash
   # 1. 更新 composer.json
   # 2. 重建 image
   docker compose up -d --build backend
   ```

### ❌ 避免做法

1. **不要在主機上安裝依賴**
   ```bash
   # ❌ 不要這樣做
   cd backend && composer install
   ```

2. **不要在容器啟動時安裝**
   ```yaml
   # ❌ 不要這樣做
   command: |
     composer install
     php artisan serve
   ```

3. **不要使用 VOLUME 指令**
   ```dockerfile
   # ❌ 不要這樣做
   VOLUME ["/var/www/html/vendor"]
   ```

4. **不要 mount 整個目錄而不排除**
   ```yaml
   # ❌ 不完整的配置
   volumes:
     - ./backend:/var/www/html  # 缺少 vendor 排除
   ```

---

## 驗證清單

### 開發環境驗證

- [ ] 刪除本地的 `backend/vendor/` 目錄
- [ ] 執行 `docker compose down -v && docker compose up -d --build`
- [ ] 檢查容器日誌：`docker compose logs backend | grep -i error`
- [ ] 驗證 HTTP 訪問：`curl http://localhost:8000/api/health`
- [ ] 檢查 vendor mount：`docker compose exec backend ls -la /var/www/html/vendor/ | head`
- [ ] 確認能執行 artisan：`docker compose exec backend php artisan --version`

### 生產環境驗證

部署後觀察：

- [ ] GitHub Actions 部署成功（無 Fatal Error）
- [ ] 容器正常啟動：`docker compose ps backend`
- [ ] 日誌無 autoload.php 錯誤：`docker compose logs backend --tail 100`
- [ ] HTTP 響應正常：測試 API endpoints
- [ ] Laravel 功能正常：登入、API 調用等

### 性能驗證

- [ ] 容器啟動時間：應在 30 秒內
- [ ] 首次請求響應時間：應在 2 秒內
- [ ] Composer autoload 性能：執行 `php artisan route:list` 測試

---

## 參考資料

### Docker Volume 官方文檔

- [Use volumes](https://docs.docker.com/storage/volumes/)
- [Use bind mounts](https://docs.docker.com/storage/bind-mounts/)
- [Manage data in Docker](https://docs.docker.com/storage/)

### Laravel 部署最佳實踐

- [Laravel Deployment](https://laravel.com/docs/10.x/deployment)
- [Composer in Docker](https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md)

### 類似問題討論

- [Docker Compose and vendor folder](https://stackoverflow.com/questions/30043872)
- [Laravel in Docker: vendor not found](https://github.com/laravel/laravel/issues/xxxx)

---

## 總結

**問題**：`Fatal error: Failed opening required '/var/www/html/vendor/autoload.php'`

**根本原因**：docker-compose.yml 的 bind mount 覆蓋了容器內已安裝的 vendor/ 目錄

**解決方案**：添加 anonymous volume 排除 vendor 目錄
```yaml
volumes:
  - ./backend:/var/www/html
  - /var/www/html/vendor  # ← 這一行
```

**關鍵學習**：
1. Docker volume mount 會完全覆蓋容器內的目錄
2. 更具體的路徑優先級更高
3. Anonymous volume 可以保護容器內的目錄
4. 依賴包應該在 build 時安裝，不是運行時

**測試狀態**：已提交 (Commit da1c290)，待驗證

**影響範圍**：
- ✅ 開發環境：vendor 來自容器，版本一致
- ✅ 生產環境：不依賴主機的 vendor/，部署穩定
- ✅ CI/CD：構建時安裝依賴，運行時直接使用

**後續優化建議**：
- 考慮使用 multi-stage build 進一步優化 image 大小
- 添加 healthcheck 確保應用正確啟動
- 文檔化依賴更新流程
