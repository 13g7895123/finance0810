<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="bg-white rounded-lg shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold text-gray-900">
          應用程式錯誤日誌管理
        </h2>
        <div class="flex items-center space-x-2">
          <div
            :class="[
              'w-3 h-3 rounded-full',
              errorStats?.firebase_issues === 0 && errorStats?.database_issues === 0 ? 'bg-green-500' :
              'bg-red-500 animate-pulse'
            ]"
          ></div>
          <span class="text-sm text-gray-600">
            {{ getSystemStatusText() }}
          </span>
        </div>
      </div>
      <p class="text-gray-600">
        監控和分析應用程式錯誤日誌，追蹤 "An unexpected error occurred" 錯誤原因
      </p>

      <!-- 權限檢查 -->
      <div v-if="!canAccessLogs" class="mt-4 p-4 bg-yellow-100 border border-yellow-300 rounded-lg">
        <div class="flex items-center">
          <svg class="w-5 h-5 text-yellow-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
          <span class="text-yellow-800 font-medium">您需要管理員權限才能查看錯誤日誌</span>
        </div>
      </div>
    </div>

    <!-- 監控儀表板 -->
    <div v-if="canAccessLogs">
      <LogMonitoringDashboard
        @view-all-logs="handleViewAllLogs"
        @view-critical-only="handleViewCriticalOnly"
      />
    </div>

    <!-- 錯誤統計總覽 -->
    <div v-if="canAccessLogs" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 14.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="text-sm font-medium text-gray-500 truncate">嚴重錯誤</dt>
              <dd class="text-lg font-medium text-gray-900">{{ errorStats?.error_types?.error || 0 }}</dd>
            </dl>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016zM12 9v2m0 4h.01"/>
            </svg>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="text-sm font-medium text-gray-500 truncate">Firebase 問題</dt>
              <dd class="text-lg font-medium text-gray-900">{{ errorStats?.firebase_issues || 0 }}</dd>
            </dl>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
            </svg>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="text-sm font-medium text-gray-500 truncate">資料庫問題</dt>
              <dd class="text-lg font-medium text-gray-900">{{ errorStats?.database_issues || 0 }}</dd>
            </dl>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"/>
            </svg>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="text-sm font-medium text-gray-500 truncate">API 問題</dt>
              <dd class="text-lg font-medium text-gray-900">{{ errorStats?.api_issues || 0 }}</dd>
            </dl>
          </div>
        </div>
      </div>
    </div>

    <!-- 搜索和過濾器 -->
    <div v-if="canAccessLogs" class="bg-white rounded-lg shadow-sm p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">搜索和過濾</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">錯誤級別</label>
          <select v-model="filters.level" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">全部</option>
            <option value="error">ERROR</option>
            <option value="critical">CRITICAL</option>
            <option value="emergency">EMERGENCY</option>
            <option value="warning">WARNING</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">搜索關鍵字</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="搜索錯誤訊息..."
            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">開始日期</label>
          <input
            v-model="filters.start_date"
            type="date"
            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">結束日期</label>
          <input
            v-model="filters.end_date"
            type="date"
            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          @click="loadErrorLogs"
          :disabled="loading"
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
        >
          <span v-if="loading">載入中...</span>
          <span v-else>搜索日誌</span>
        </button>

        <button
          @click="loadCriticalErrors"
          :disabled="loading"
          class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50"
        >
          查看嚴重錯誤
        </button>

        <button
          @click="clearFilters"
          class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600"
        >
          清除篩選
        </button>

        <button
          @click="loadErrorStats"
          :disabled="loading"
          class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
        >
          重新載入統計
        </button>
      </div>
    </div>

    <!-- 錯誤日誌列表 -->
    <div v-if="canAccessLogs" class="bg-white rounded-lg shadow-sm">
      <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">錯誤日誌 ({{ logs.length }})</h3>
          <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-500">
              {{ logsSummary?.time_range?.earliest ? `${logsSummary.time_range.earliest} ~ ${logsSummary.time_range.latest}` : '無資料' }}
            </span>
          </div>
        </div>
      </div>

      <div class="divide-y divide-gray-200">
        <div v-if="loading" class="p-6 text-center text-gray-500">
          載入日誌中...
        </div>

        <div v-else-if="logs.length === 0" class="p-6 text-center text-gray-500">
          未找到符合條件的錯誤日誌
        </div>

        <div v-else v-for="(log, index) in logs" :key="index" class="p-4 hover:bg-gray-50">
          <div class="flex items-start justify-between mb-2">
            <div class="flex items-center space-x-2">
              <span
                :class="[
                  'px-2 py-1 rounded-full text-xs font-medium',
                  getLogLevelClass(log.level)
                ]"
              >
                {{ log.level }}
              </span>
              <span class="text-sm text-gray-500">{{ formatTimestamp(log.timestamp) }}</span>
            </div>
            <button
              @click="toggleLogDetails(index)"
              class="text-blue-600 hover:text-blue-800 text-sm"
            >
              {{ expandedLogs.has(index) ? '收起' : '展開' }}
            </button>
          </div>

          <div class="mb-2">
            <p class="text-gray-900 font-medium">{{ log.message }}</p>
          </div>

          <!-- 錯誤詳情 -->
          <div v-if="expandedLogs.has(index)" class="mt-3 space-y-3">
            <!-- 異常信息 -->
            <div v-if="log.exception" class="bg-red-50 border border-red-200 rounded-lg p-3">
              <h4 class="text-sm font-medium text-red-800 mb-2">異常詳情</h4>
              <div class="text-sm text-red-700 space-y-1">
                <div><strong>類型:</strong> {{ log.exception.type }}</div>
                <div v-if="log.exception.file"><strong>文件:</strong> {{ log.exception.file }}:{{ log.exception.line }}</div>
                <div v-if="log.exception.code"><strong>代碼:</strong> {{ log.exception.code }}</div>
                <div v-if="log.exception.message"><strong>訊息:</strong> {{ log.exception.message }}</div>
              </div>
            </div>

            <!-- 上下文信息 -->
            <div v-if="log.context" class="bg-gray-50 border border-gray-200 rounded-lg p-3">
              <h4 class="text-sm font-medium text-gray-800 mb-2">上下文信息</h4>
              <pre class="text-xs text-gray-700 whitespace-pre-wrap overflow-x-auto">{{ JSON.stringify(log.context, null, 2) }}</pre>
            </div>

            <!-- 堆棧跟踪 -->
            <div v-if="log.trace && log.trace.length > 0" class="bg-gray-50 border border-gray-200 rounded-lg p-3">
              <h4 class="text-sm font-medium text-gray-800 mb-2">堆棧跟踪</h4>
              <pre class="text-xs text-gray-700 whitespace-pre-wrap overflow-x-auto">{{ log.trace.join('\n') }}</pre>
            </div>

            <!-- 額外行 -->
            <div v-if="log.additional_lines && log.additional_lines.length > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-3">
              <h4 class="text-sm font-medium text-blue-800 mb-2">額外信息</h4>
              <div class="text-xs text-blue-700 space-y-1">
                <div v-for="(line, lineIndex) in log.additional_lines" :key="lineIndex">{{ line }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 關鍵錯誤模式分析 -->
    <div v-if="canAccessLogs && criticalPatterns" class="bg-white rounded-lg shadow-sm p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">嚴重錯誤模式分析</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <h4 class="text-md font-medium text-gray-800 mb-2">錯誤類型統計</h4>
          <div class="space-y-2">
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">資料庫連接問題</span>
              <span class="text-sm font-medium">{{ criticalPatterns.database_connection_issues }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Firebase 認證問題</span>
              <span class="text-sm font-medium">{{ criticalPatterns.firebase_authentication_issues }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">API 路由錯誤</span>
              <span class="text-sm font-medium">{{ criticalPatterns.api_route_errors }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Migration 錯誤</span>
              <span class="text-sm font-medium">{{ criticalPatterns.migration_errors }}</span>
            </div>
          </div>
        </div>

        <div>
          <h4 class="text-md font-medium text-gray-800 mb-2">缺失的資料表</h4>
          <div v-if="criticalPatterns.missing_tables && criticalPatterns.missing_tables.length > 0" class="space-y-1">
            <div v-for="table in criticalPatterns.missing_tables" :key="table" class="text-sm text-red-600 bg-red-50 px-2 py-1 rounded">
              {{ table }}
            </div>
          </div>
          <div v-else class="text-sm text-gray-500">無缺失資料表</div>
        </div>
      </div>
    </div>

    <!-- 日誌清理 -->
    <div v-if="canAccessLogs" class="bg-white rounded-lg shadow-sm p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">日誌管理</h3>

      <div class="flex items-center space-x-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">保留天數</label>
          <input
            v-model.number="cleanupDays"
            type="number"
            min="1"
            max="365"
            class="w-24 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <div class="pt-6">
          <button
            @click="cleanupOldLogs"
            :disabled="loading || !cleanupDays"
            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50"
          >
            清理舊日誌
          </button>
        </div>
      </div>

      <p class="text-sm text-gray-500 mt-2">
        * 此操作將永久刪除 {{ cleanupDays }} 天前的日誌記錄，請謹慎操作
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAuthStore } from '~/stores/auth'
import LogMonitoringDashboard from '~/components/LogMonitoringDashboard.vue'

// 設定頁面中介軟體和佈局
definePageMeta({
  middleware: 'auth',
  layout: 'dashboard'
})

// 使用stores
const authStore = useAuthStore()
const { $api } = useNuxtApp()

// 響應式資料
const logs = ref([])
const errorStats = ref(null)
const criticalPatterns = ref(null)
const logsSummary = ref(null)
const loading = ref(false)
const expandedLogs = ref(new Set())
const cleanupDays = ref(30)

// 過濾器
const filters = ref({
  level: '',
  search: '',
  start_date: '',
  end_date: '',
  limit: 100,
  include_trace: true
})

// 計算屬性
const user = computed(() => authStore.user)
const canAccessLogs = computed(() => {
  return user.value && (user.value.isAdmin || user.value.isManager || user.value.isExecutive)
})

// 方法
const getSystemStatusText = () => {
  if (!errorStats.value) return '載入中...'

  const totalIssues = (errorStats.value.firebase_issues || 0) +
                     (errorStats.value.database_issues || 0) +
                     (errorStats.value.api_issues || 0)

  if (totalIssues === 0) return '系統正常'
  if (totalIssues < 10) return '輕微問題'
  if (totalIssues < 50) return '需要注意'
  return '嚴重問題'
}

const getLogLevelClass = (level) => {
  const classes = {
    'ERROR': 'bg-red-100 text-red-800',
    'CRITICAL': 'bg-red-200 text-red-900',
    'EMERGENCY': 'bg-red-300 text-red-900',
    'WARNING': 'bg-yellow-100 text-yellow-800',
    'INFO': 'bg-blue-100 text-blue-800',
    'DEBUG': 'bg-gray-100 text-gray-800'
  }
  return classes[level] || 'bg-gray-100 text-gray-800'
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

const toggleLogDetails = (index) => {
  if (expandedLogs.value.has(index)) {
    expandedLogs.value.delete(index)
  } else {
    expandedLogs.value.add(index)
  }
}

const loadErrorLogs = async () => {
  if (!canAccessLogs.value) return

  loading.value = true
  try {
    const params = Object.fromEntries(
      Object.entries(filters.value).filter(([_, value]) => value !== '' && value !== null)
    )

    const response = await $api('/debug/logs/errors', {
      method: 'GET',
      params
    })

    if (response.success) {
      logs.value = response.data.logs || []
      logsSummary.value = response.data.summary
    } else {
      throw new Error(response.message || '載入錯誤日誌失敗')
    }
  } catch (error) {
    console.error('載入錯誤日誌失敗:', error)
    logs.value = []

    // 使用 Nuxt 的通知系統
    $toast?.error(`載入錯誤日誌失敗: ${error.message}`)
  } finally {
    loading.value = false
  }
}

const loadErrorStats = async () => {
  if (!canAccessLogs.value) return

  try {
    const response = await $api('/debug/logs/stats', {
      method: 'GET',
      params: { days: 7 }
    })

    if (response.success) {
      errorStats.value = response.data
    }
  } catch (error) {
    console.error('載入錯誤統計失敗:', error)
  }
}

const loadCriticalErrors = async () => {
  if (!canAccessLogs.value) return

  loading.value = true
  try {
    const response = await $api('/debug/logs/critical', {
      method: 'GET',
      params: { limit: 50 }
    })

    if (response.success) {
      logs.value = response.data.errors || []
      criticalPatterns.value = response.data.analyzed_patterns
      logsSummary.value = {
        total_entries: logs.value.length,
        by_level: {},
        common_errors: {}
      }
    } else {
      throw new Error(response.message || '載入嚴重錯誤失敗')
    }
  } catch (error) {
    console.error('載入嚴重錯誤失敗:', error)
    logs.value = []

    $toast?.error(`載入嚴重錯誤失敗: ${error.message}`)
  } finally {
    loading.value = false
  }
}

const cleanupOldLogs = async () => {
  if (!canAccessLogs.value || !cleanupDays.value) return

  if (!confirm(`確定要清理 ${cleanupDays.value} 天前的日誌嗎？此操作無法復原。`)) {
    return
  }

  loading.value = true
  try {
    const response = await $api('/debug/logs/cleanup', {
      method: 'POST',
      body: {
        days_to_keep: cleanupDays.value
      }
    })

    if (response.success) {
      $toast?.success(`成功清理 ${response.data.removed_entries} 筆舊日誌記錄`)

      // 重新載入統計和日誌
      await loadErrorStats()
      await loadErrorLogs()
    } else {
      throw new Error(response.message || '清理日誌失敗')
    }
  } catch (error) {
    console.error('清理日誌失敗:', error)
    $toast?.error(`清理日誌失敗: ${error.message}`)
  } finally {
    loading.value = false
  }
}

const clearFilters = () => {
  filters.value = {
    level: '',
    search: '',
    start_date: '',
    end_date: '',
    limit: 100,
    include_trace: true
  }
  logs.value = []
  logsSummary.value = null
  expandedLogs.value.clear()
}

const handleViewAllLogs = () => {
  clearFilters()
  loadErrorLogs()
}

const handleViewCriticalOnly = () => {
  clearFilters()
  filters.value.level = 'error'
  loadCriticalErrors()
}

// 生命週期
onMounted(async () => {
  if (canAccessLogs.value) {
    await loadErrorStats()
    // 預設載入最近的錯誤日誌
    filters.value.level = 'error'
    await loadErrorLogs()
  }
})

// 監聽過濾器變化自動搜索
watch(() => filters.value.level, () => {
  if (filters.value.level && canAccessLogs.value) {
    loadErrorLogs()
  }
})
</script>

<style scoped>
/* 額外的樣式如果需要 */
</style>