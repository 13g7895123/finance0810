#!/bin/bash

# 正式環境前端重新部署腳本
# 此腳本會重新建置並啟動 frontend 容器以套用最新程式碼

echo "=========================================="
echo "正式環境 Frontend 重新部署"
echo "=========================================="
echo ""

# 停止 frontend 容器
echo "📦 停止 frontend 容器..."
docker-compose stop frontend

# 重新建置 frontend 映像檔
echo ""
echo "🔨 重新建置 frontend 映像檔..."
docker-compose build --no-cache frontend

# 啟動 frontend 容器
echo ""
echo "🚀 啟動 frontend 容器..."
docker-compose up -d frontend

# 檢查容器狀態
echo ""
echo "✅ 檢查容器狀態..."
docker-compose ps frontend

echo ""
echo "=========================================="
echo "✨ Frontend 重新部署完成！"
echo "=========================================="
