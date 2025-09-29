<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="bg-white rounded-lg shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold text-gray-900">
          Webhook 執行日誌
        </h2>
        <div class="flex items-center space-x-4">
          <!-- 自動刷新控制 -->
          <div class="flex items-center space-x-2">
            <input 
              id="auto-refresh" 
              v-model="autoRefresh" 
              type="checkbox"
              class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
            >
            <label for="auto-refresh" class="text-sm text-gray-600">
              自動刷新 ({{ refreshInterval }}s)
            </label>
          </div>
          
          <!-- 立即刷新按鈕 -->
          <button
            @click="fetchLogs"
            :disabled="loading"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2"
          >
            <svg v-if="loading" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ loading ? '載入中...' : '刷新' }}</span>
          </button>
        </div>
      </div>
      <p class="text-gray-600">
        監控 WordPress 與 LINE webhook 執行紀錄，追蹤每個請求的處理步驟和狀態
      </p>
    </div>

    <!-- 統計資訊 -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-600">總執行次數</p>
            <p class="text-2xl font-bold text-gray-900">{{ statistics?.total_executions || 0 }}</p>
          </div>
          <div class="p-3 bg-blue-100 rounded-full">
            <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/>
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-600">成功執行</p>
            <p class="text-2xl font-bold text-green-600">{{ statistics?.successful || 0 }}</p>
          </div>
          <div class="p-3 bg-green-100 rounded-full">
            <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-600">失敗執行</p>
            <p class="text-2xl font-bold text-red-600">{{ statistics?.failed || 0 }}</p>
          </div>
          <div class="p-3 bg-red-100 rounded-full">
            <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"/>
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-600">平均耗時</p>
            <p class="text-2xl font-bold text-gray-900">
              {{ statistics?.average_duration_ms ? Math.round(statistics.average_duration_ms) + 'ms' : '-' }}
            </p>
          </div>
          <div class="p-3 bg-yellow-100 rounded-full">
            <svg class="w-6 h-6 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- 篩選和搜尋 -->
    <div class="bg-white rounded-lg shadow-sm p-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">狀態篩選</label>
          <select 
            v-model="filters.status"
            @change="fetchLogs"
            class="w-full p-2 border border-gray-300 rounded-lg bg-white text-gray-900"
          >
            <option value="">全部狀態</option>
            <option value="completed">已完成</option>
            <option value="failed">失敗</option>
            <option value="processing">處理中</option>
            <option value="started">已開始</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">類型篩選</label>
          <select 
            v-model="filters.type"
            @change="fetchLogs"
            class="w-full p-2 border border-gray-300 rounded-lg bg-white text-gray-900"
          >
            <option value="">全部類型</option>
            <option value="line">LINE Webhook</option>
            <option value="wp">WordPress Webhook</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">時間範圍</label>
          <select 
            v-model="filters.days"
            @change="fetchLogs"
            class="w-full p-2 border border-gray-300 rounded-lg bg-white text-gray-900"
          >
            <option value="1">最近1天</option>
            <option value="7">最近7天</option>
            <option value="30">最近30天</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">搜尋</label>
          <input 
            v-model="filters.search"
            @input="debouncedSearch"
            type="text"
            placeholder="執行ID或IP地址"
            class="w-full p-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500"
          >
        </div>
      </div>
    </div>

    <!-- 執行日誌列表 -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                狀態
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                執行ID
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                類型/事件數
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                IP地址
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                開始時間
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                耗時
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                操作
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="loading && logs.length === 0" class="animate-pulse">
              <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                載入中...
              </td>
            </tr>
            <tr v-else-if="logs.length === 0">
              <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                沒有找到符合條件的日誌
              </td>
            </tr>
            <tr v-else v-for="log in logs" :key="log.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getStatusBadgeClass(log.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                  {{ getStatusText(log.status) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                {{ log.execution_id.substring(0, 8) }}...
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <div>{{ log.webhook_type.toUpperCase() }}</div>
                <div class="text-xs">{{ log.events_count }} 個事件</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                {{ log.ip_address }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ formatDateTime(log.started_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ log.duration_ms ? log.duration_ms + 'ms' : '-' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button
                  @click="viewDetails(log)"
                  class="text-blue-600 hover:text-blue-900"
                >
                  查看詳情
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 分頁 -->
      <div v-if="pagination && pagination.total > pagination.per_page" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
        <div class="flex items-center justify-between">
          <div class="flex-1 flex justify-between sm:hidden">
            <button
              @click="changePage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              上一頁
            </button>
            <button
              @click="changePage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              下一頁
            </button>
          </div>
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700">
                顯示第 <span class="font-medium">{{ (pagination.current_page - 1) * pagination.per_page + 1 }}</span> 
                到第 <span class="font-medium">{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}</span> 
                項，共 <span class="font-medium">{{ pagination.total }}</span> 項結果
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <button
                  @click="changePage(pagination.current_page - 1)"
                  :disabled="pagination.current_page <= 1"
                  class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  上一頁
                </button>
                <button
                  @click="changePage(pagination.current_page + 1)"
                  :disabled="pagination.current_page >= pagination.last_page"
                  class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  下一頁
                </button>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 詳情模態框 -->
    <div v-if="selectedLog" class="fixed inset-0 z-50 overflow-y-auto" @click="closeDetails">
      <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div 
          @click.stop
          class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full sm:p-6"
        >
          <div class="sm:flex sm:items-start">
            <div class="w-full">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                  Webhook 執行詳情
                </h3>
                <button 
                  @click="closeDetails"
                  class="text-gray-400 hover:text-gray-600"
                >
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                  </svg>
                </button>
              </div>

              <!-- 基本信息 -->
              <div class="mb-6">
                <h4 class="text-md font-medium text-gray-900 mb-3">基本信息</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <div><strong>執行ID:</strong> <span class="font-mono text-sm">{{ selectedLog.execution_id }}</span></div>
                    <div><strong>狀態:</strong> 
                      <span :class="getStatusBadgeClass(selectedLog.status)" class="px-2 py-1 text-xs font-medium rounded-full ml-2">
                        {{ getStatusText(selectedLog.status) }}
                      </span>
                    </div>
                    <div><strong>Webhook類型:</strong> {{ selectedLog.webhook_type.toUpperCase() }}</div>
                    <div><strong>事件數量:</strong> {{ selectedLog.events_count }}</div>
                  </div>
                  <div class="space-y-2">
                    <div><strong>IP地址:</strong> <span class="font-mono text-sm">{{ selectedLog.ip_address }}</span></div>
                    <div><strong>開始時間:</strong> {{ formatDateTime(selectedLog.started_at) }}</div>
                    <div><strong>完成時間:</strong> {{ selectedLog.completed_at ? formatDateTime(selectedLog.completed_at) : '-' }}</div>
                    <div><strong>執行耗時:</strong> {{ selectedLog.duration_ms ? selectedLog.duration_ms + 'ms' : '-' }}</div>
                  </div>
                </div>
              </div>

              <!-- 錯誤信息 -->
              <div v-if="selectedLog.error_message" class="mb-6">
                <h4 class="text-md font-medium text-gray-900 mb-3">錯誤信息</h4>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                  <p class="text-red-800">{{ selectedLog.error_message }}</p>
                  <div v-if="selectedLog.error_details" class="mt-2">
                    <pre class="text-xs text-red-700 overflow-x-auto">{{ JSON.stringify(selectedLog.error_details, null, 2) }}</pre>
                  </div>
                </div>
              </div>

              <!-- 執行步驟 -->
              <div v-if="selectedLog.execution_steps && selectedLog.execution_steps.length > 0" class="mb-6">
                <h4 class="text-md font-medium text-gray-900 mb-3">執行步驟</h4>
                <div class="space-y-2 max-h-96 overflow-y-auto">
                  <div 
                    v-for="(step, index) in selectedLog.execution_steps" 
                    :key="index"
                    class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg"
                  >
                    <div class="flex-shrink-0">
                      <div :class="[
                        'w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium',
                        step.status === 'completed' ? 'bg-green-100 text-green-800' :
                        step.status === 'failed' ? 'bg-red-100 text-red-800' :
                        'bg-blue-100 text-blue-800'
                      ]">
                        {{ index + 1 }}
                      </div>
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-900">{{ step.step }}</p>
                        <span class="text-xs text-gray-500">{{ formatDateTime(step.timestamp) }}</span>
                      </div>
                      <div v-if="step.details" class="mt-1">
                        <pre class="text-xs text-gray-600 overflow-x-auto max-h-32">{{ JSON.stringify(step.details, null, 2) }}</pre>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 請求數據 -->
              <div v-if="selectedLog.request_body" class="mb-6">
                <h4 class="text-md font-medium text-gray-900 mb-3">請求數據</h4>
                <div class="bg-gray-50 rounded-lg p-4">
                  <pre class="text-xs text-gray-600 overflow-x-auto max-h-64">{{ typeof selectedLog.request_body === 'string' ? selectedLog.request_body : JSON.stringify(selectedLog.request_body, null, 2) }}</pre>
                </div>
              </div>

              <!-- 事件數據 -->
              <div v-if="selectedLog.events_data && (Array.isArray(selectedLog.events_data) ? selectedLog.events_data.length > 0 : Object.keys(selectedLog.events_data).length > 0)" class="mb-6">
                <h4 class="text-md font-medium text-gray-900 mb-3">事件數據</h4>
                <div class="bg-gray-50 rounded-lg p-4">
                  <pre class="text-xs text-gray-600 overflow-x-auto max-h-64">{{ JSON.stringify(selectedLog.events_data, null, 2) }}</pre>
                </div>
              </div>

              <!-- 結果數據 -->
              <div v-if="selectedLog.results" class="mb-6">
                <h4 class="text-md font-medium text-gray-900 mb-3">執行結果</h4>
                <div class="bg-gray-50 rounded-lg p-4">
                  <pre class="text-xs text-gray-600 overflow-x-auto">{{ JSON.stringify(selectedLog.results, null, 2) }}</pre>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-5 sm:mt-6 sm:flex sm:flex-row-reverse">
            <button
              @click="closeDetails"
              type="button"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
            >
              關閉
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

// 頁面元數據
definePageMeta({
  title: 'Webhook 執行日誌',
  middleware: ['auth'],
  layout: 'default'
})

// Composables
const authStore = useAuthStore()
const { get, post } = useApi()
const { success: showSuccess, error: showError } = useNotification()

// 響應式數據
const loading = ref(false)
const logs = ref([])
const statistics = ref(null)
const pagination = ref(null)
const selectedLog = ref(null)
const autoRefresh = ref(false)
const refreshInterval = ref(30)
const refreshTimer = ref(null)
const error = ref(null)

// 篩選條件
const filters = ref({
  status: '',
  type: '',
  days: '7',
  search: '',
  page: 1,
  per_page: 20
})

// 計算屬性
const user = computed(() => authStore.user)
const canAccessLogs = computed(() => {
  // Point 19: 移除管理員權限限制，所有登入用戶都可以查看日誌
  return authStore.isLoggedIn && !!user.value
})

// 搜尋防抖
let searchDebounceTimer = null
const debouncedSearch = () => {
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    filters.value.page = 1
    fetchLogs()
  }, 500)
}

// 方法
const fetchLogs = async () => {
  if (!canAccessLogs.value) return
  
  loading.value = true
  error.value = null
  try {
    const params = new URLSearchParams()
    Object.entries(filters.value).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        // Map frontend filter names to API parameter names
        const apiKey = key === 'type' ? 'webhook_type' : 
                       key === 'search' ? 'execution_id' : key
        if (apiKey !== 'days') { // days is handled separately for date filtering
          params.append(apiKey, value)
        }
      }
    })
    
    // Handle date filtering based on days
    if (filters.value.days) {
      const daysAgo = new Date()
      daysAgo.setDate(daysAgo.getDate() - parseInt(filters.value.days))
      params.append('date_from', daysAgo.toISOString().split('T')[0])
    }

    const { data: response, error: apiError } = await get('/webhook/execution-logs', Object.fromEntries(params))

    if (apiError) {
      console.error('載入 webhook 日誌失敗:', apiError)
      logs.value = []
      if (apiError.status === 404) {
        error.value = '日誌資料表尚未建立，請聯絡系統管理員'
      } else {
        error.value = `載入日誌失敗: ${apiError.message || '未知錯誤'}`
      }
      return
    }

    if (response && response.success) {
      logs.value = response.data || []
      pagination.value = response.pagination || {
        current_page: 1,
        per_page: 20,
        total: (response.data || []).length,
        last_page: 1
      }
      // Update statistics after loading logs
      await fetchStatistics()
    } else {
      logs.value = []
      pagination.value = null
      error.value = response?.message || '載入日誌失敗'
    }
  } catch (error) {
    console.error('載入 webhook 日誌失敗:', error)
    logs.value = []
    error.value = `載入日誌失敗: ${error.message}`
  } finally {
    loading.value = false
  }
}

const fetchStatistics = async () => {
  if (!canAccessLogs.value || !logs.value.length) return
  
  try {
    // Calculate statistics from loaded logs
    const total = logs.value.length
    const completed = logs.value.filter(log => log.status === 'completed').length
    const failed = logs.value.filter(log => log.status === 'failed').length
    const durations = logs.value.filter(log => log.duration_ms).map(log => log.duration_ms)
    const averageDuration = durations.length > 0 ? durations.reduce((a, b) => a + b, 0) / durations.length : 0
    
    statistics.value = {
      total_executions: total,
      successful: completed,
      failed: failed,
      average_duration_ms: averageDuration
    }
  } catch (error) {
    console.error('載入統計資料失敗:', error)
  }
}

const viewDetails = (log) => {
  selectedLog.value = log
}

const closeDetails = () => {
  selectedLog.value = null
}

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    filters.value.page = page
    fetchLogs()
  }
}

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'completed':
      return 'bg-green-100 text-green-800'
    case 'failed':
      return 'bg-red-100 text-red-800'
    case 'processing':
      return 'bg-blue-100 text-blue-800'
    case 'started':
      return 'bg-yellow-100 text-yellow-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

const getStatusText = (status) => {
  switch (status) {
    case 'completed': return '已完成'
    case 'failed': return '失敗'
    case 'processing': return '處理中'
    case 'started': return '已開始'
    default: return status
  }
}

const formatDateTime = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString('zh-TW', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

const startAutoRefresh = () => {
  if (refreshTimer.value) {
    clearInterval(refreshTimer.value)
  }
  
  refreshTimer.value = setInterval(() => {
    if (autoRefresh.value) {
      fetchLogs()
    }
  }, refreshInterval.value * 1000)
}

const stopAutoRefresh = () => {
  if (refreshTimer.value) {
    clearInterval(refreshTimer.value)
    refreshTimer.value = null
  }
}

// 監聽器
watch(autoRefresh, (newValue) => {
  if (newValue) {
    startAutoRefresh()
  } else {
    stopAutoRefresh()
  }
})

// 生命週期
onMounted(async () => {
  // 確保認證已初始化
  await authStore.waitForInitialization()

  console.log('Settings/webhook-logs page - Auth debug info:', {
    isLoggedIn: authStore.isLoggedIn,
    isAdmin: authStore.isAdmin,
    isManager: authStore.isManager,
    isExecutive: authStore.isExecutive,
    userRole: authStore.user?.role,
    canAccessLogs: canAccessLogs.value
  })

  if (canAccessLogs.value) {
    await fetchLogs()
    await fetchStatistics()
  } else {
    console.warn('User does not have access to webhook logs page:', {
      isLoggedIn: authStore.isLoggedIn,
      userRole: authStore.user?.role,
      userRoles: authStore.user?.roles
    })
  }
})

onUnmounted(() => {
  stopAutoRefresh()
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }
})
</script>