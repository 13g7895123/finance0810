<template>
  <div class="bg-white rounded-lg shadow-sm p-6">
    <div class="flex items-center justify-between mb-6">
      <h3 class="text-lg font-semibold text-gray-900">系統錯誤監控儀表板</h3>
      <div class="flex items-center space-x-2">
        <div
          :class="[
            'w-3 h-3 rounded-full',
            systemStatus === 'healthy' ? 'bg-green-500 animate-pulse' :
            systemStatus === 'warning' ? 'bg-yellow-500 animate-pulse' :
            'bg-red-500 animate-pulse'
          ]"
        ></div>
        <span class="text-sm text-gray-600">
          {{ getStatusText() }}
        </span>
        <button
          @click="refreshData"
          :disabled="loading"
          class="ml-2 p-1 text-gray-400 hover:text-gray-600 disabled:opacity-50"
        >
          <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- 重要指標卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <!-- 嚴重錯誤計數 -->
      <div class="bg-gradient-to-r from-red-50 to-red-100 border border-red-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-red-600 text-sm font-medium">嚴重錯誤</p>
            <p class="text-2xl font-bold text-red-700">{{ errorStats?.error_types?.error || 0 }}</p>
          </div>
          <div class="w-8 h-8 bg-red-200 rounded-full flex items-center justify-center">
            <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
          </div>
        </div>
        <div class="mt-2 text-xs text-red-600">
          過去24小時
        </div>
      </div>

      <!-- Firebase 問題 -->
      <div class="bg-gradient-to-r from-orange-50 to-orange-100 border border-orange-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-orange-600 text-sm font-medium">Firebase 問題</p>
            <p class="text-2xl font-bold text-orange-700">{{ errorStats?.firebase_issues || 0 }}</p>
          </div>
          <div class="w-8 h-8 bg-orange-200 rounded-full flex items-center justify-center">
            <svg class="w-4 h-4 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732L14.146 12.8l-1.179 4.456a1 1 0 01-1.934 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732L9.854 7.2l1.179-4.456A1 1 0 0112 2z" clip-rule="evenodd"/>
            </svg>
          </div>
        </div>
        <div class="mt-2 text-xs text-orange-600">
          認證和連線問題
        </div>
      </div>

      <!-- 資料庫問題 -->
      <div class="bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-blue-600 text-sm font-medium">資料庫問題</p>
            <p class="text-2xl font-bold text-blue-700">{{ errorStats?.database_issues || 0 }}</p>
          </div>
          <div class="w-8 h-8 bg-blue-200 rounded-full flex items-center justify-center">
            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
              <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
            </svg>
          </div>
        </div>
        <div class="mt-2 text-xs text-blue-600">
          SQL 和連線問題
        </div>
      </div>

      <!-- API 問題 -->
      <div class="bg-gradient-to-r from-purple-50 to-purple-100 border border-purple-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-purple-600 text-sm font-medium">API 問題</p>
            <p class="text-2xl font-bold text-purple-700">{{ errorStats?.api_issues || 0 }}</p>
          </div>
          <div class="w-8 h-8 bg-purple-200 rounded-full flex items-center justify-center">
            <svg class="w-4 h-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
          </div>
        </div>
        <div class="mt-2 text-xs text-purple-600">
          路由和端點問題
        </div>
      </div>
    </div>

    <!-- 最近錯誤活動 -->
    <div class="border-t pt-6">
      <h4 class="text-md font-medium text-gray-900 mb-4 flex items-center">
        <svg class="w-5 h-5 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        最近錯誤活動
      </h4>

      <div v-if="recentErrors && recentErrors.length > 0" class="space-y-3">
        <div
          v-for="(error, index) in recentErrors.slice(0, 5)"
          :key="index"
          class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg border border-gray-200"
        >
          <div
            :class="[
              'w-2 h-2 rounded-full mt-2 flex-shrink-0',
              error.level === 'ERROR' ? 'bg-red-500' :
              error.level === 'CRITICAL' ? 'bg-red-600' :
              error.level === 'WARNING' ? 'bg-yellow-500' :
              'bg-gray-400'
            ]"
          ></div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-1">
              <span
                :class="[
                  'px-2 py-1 rounded-full text-xs font-medium',
                  error.level === 'ERROR' ? 'bg-red-100 text-red-800' :
                  error.level === 'CRITICAL' ? 'bg-red-200 text-red-900' :
                  error.level === 'WARNING' ? 'bg-yellow-100 text-yellow-800' :
                  'bg-gray-100 text-gray-800'
                ]"
              >
                {{ error.level }}
              </span>
              <span class="text-xs text-gray-500">{{ formatTimestamp(error.timestamp) }}</span>
            </div>
            <p class="text-sm text-gray-900 font-medium truncate">{{ error.message }}</p>
            <div v-if="error.exception && error.exception.file" class="text-xs text-gray-500 mt-1">
              {{ error.exception.file }}:{{ error.exception.line }}
            </div>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-8 text-gray-500">
        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm">目前沒有錯誤記錄</p>
        <p class="text-xs text-gray-400 mt-1">系統運行正常</p>
      </div>
    </div>

    <!-- 快速操作按鈕 -->
    <div class="border-t pt-6 mt-6">
      <div class="flex flex-wrap gap-2">
        <button
          @click="$emit('view-all-logs')"
          class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"
        >
          查看所有日誌
        </button>
        <button
          @click="$emit('view-critical-only')"
          class="px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm"
        >
          查看嚴重錯誤
        </button>
        <button
          @click="refreshData"
          :disabled="loading"
          class="px-3 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm disabled:opacity-50"
        >
          重新整理
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

// Props and emits
defineEmits(['view-all-logs', 'view-critical-only'])

// Composables
const { get } = useApi()
const { error: showError } = useNotification()

// Reactive data
const errorStats = ref(null)
const recentErrors = ref([])
const loading = ref(false)
const autoRefresh = ref(null)

// Computed properties
const systemStatus = computed(() => {
  if (!errorStats.value) return 'unknown'

  const criticalIssues = (errorStats.value.error_types?.error || 0) +
                        (errorStats.value.error_types?.critical || 0)

  if (criticalIssues === 0) return 'healthy'
  if (criticalIssues < 5) return 'warning'
  return 'critical'
})

// Methods
const getStatusText = () => {
  switch (systemStatus.value) {
    case 'healthy': return '系統健康'
    case 'warning': return '需要注意'
    case 'critical': return '嚴重問題'
    default: return '狀態檢查中'
  }
}

const formatTimestamp = (timestamp) => {
  try {
    const date = new Date(timestamp)
    const now = new Date()
    const diffInSeconds = Math.floor((now - date) / 1000)

    if (diffInSeconds < 60) {
      return `${diffInSeconds} 秒前`
    } else if (diffInSeconds < 3600) {
      const minutes = Math.floor(diffInSeconds / 60)
      return `${minutes} 分鐘前`
    } else if (diffInSeconds < 86400) {
      const hours = Math.floor(diffInSeconds / 3600)
      return `${hours} 小時前`
    } else {
      const days = Math.floor(diffInSeconds / 86400)
      return `${days} 天前`
    }
  } catch (e) {
    return timestamp
  }
}

const loadErrorStats = async () => {
  try {
    const { data: response, error: apiError } = await get('/debug/logs/stats', { days: 1 }) // 只查看過去24小時

    if (apiError) {
      console.error('載入錯誤統計失敗:', apiError)
      showError(`載入錯誤統計失敗: ${apiError.message || '未知錯誤'}`)
      return
    }

    if (response && response.success) {
      errorStats.value = response.data
    }
  } catch (error) {
    console.error('載入錯誤統計失敗:', error)
    showError(`載入錯誤統計失敗: ${error.message}`)
  }
}

const loadRecentErrors = async () => {
  try {
    const { data: response, error: apiError } = await get('/debug/logs/critical', { limit: 10 })

    if (apiError) {
      console.error('載入最近錯誤失敗:', apiError)
      showError(`載入最近錯誤失敗: ${apiError.message || '未知錯誤'}`)
      return
    }

    if (response && response.success) {
      recentErrors.value = response.data.errors || []
    }
  } catch (error) {
    console.error('載入最近錯誤失敗:', error)
    showError(`載入最近錯誤失敗: ${error.message}`)
  }
}

const refreshData = async () => {
  loading.value = true
  try {
    await Promise.all([
      loadErrorStats(),
      loadRecentErrors()
    ])
  } finally {
    loading.value = false
  }
}

const startAutoRefresh = () => {
  // 每30秒自動重新整理
  autoRefresh.value = setInterval(refreshData, 30000)
}

const stopAutoRefresh = () => {
  if (autoRefresh.value) {
    clearInterval(autoRefresh.value)
    autoRefresh.value = null
  }
}

// Lifecycle
onMounted(async () => {
  await refreshData()
  startAutoRefresh()
})

onUnmounted(() => {
  stopAutoRefresh()
})
</script>

<style scoped>
/* 任何額外的樣式 */
</style>