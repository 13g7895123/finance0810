<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
          系統除錯面板
        </h2>
        <div class="flex items-center space-x-2">
          <div 
            :class="[
              'w-3 h-3 rounded-full',
              systemHealth?.overall_status === 'healthy' ? 'bg-green-500 animate-pulse' : 
              systemHealth?.overall_status === 'warning' ? 'bg-yellow-500 animate-pulse' : 
              'bg-red-500 animate-pulse'
            ]"
          ></div>
          <span class="text-sm text-gray-600 dark:text-gray-300">
            {{ getOverallStatusText() }}
          </span>
        </div>
      </div>
      <p class="text-gray-600 dark:text-gray-300">
        診斷系統狀態、管理Firebase同步、監控資料庫連接
      </p>
      
      <!-- 權限提示 -->
      <div v-if="!canAccessDebug" class="mt-4 p-4 bg-yellow-100 border border-yellow-300 rounded-lg">
        <div class="flex items-center">
          <svg class="w-5 h-5 text-yellow-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
          <span class="text-yellow-800">您需要管理員權限才能使用除錯功能</span>
        </div>
      </div>
    </div>

    <div v-if="canAccessDebug" class="space-y-6">
      <!-- 快速動作區 -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">快速動作</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <button
            @click="refreshHealthCheck"
            :disabled="loading.healthCheck"
            class="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-blue-500 dark:hover:border-blue-400 transition-colors disabled:opacity-50"
          >
            <svg v-if="loading.healthCheck" class="animate-spin w-8 h-8 text-blue-500 mb-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <svg v-else class="w-8 h-8 text-blue-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-sm font-medium text-gray-900 dark:text-white">
              {{ loading.healthCheck ? '檢查中...' : '系統健康檢查' }}
            </span>
          </button>

          <button
            @click="syncToFirebase"
            :disabled="loading.sync"
            class="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-green-500 dark:hover:border-green-400 transition-colors disabled:opacity-50"
          >
            <svg v-if="loading.sync" class="animate-spin w-8 h-8 text-green-500 mb-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <svg v-else class="w-8 h-8 text-green-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <span class="text-sm font-medium text-gray-900 dark:text-white">
              {{ loading.sync ? '同步中...' : 'Firebase 同步' }}
            </span>
          </button>

          <button
            @click="validateData"
            :disabled="loading.validation"
            class="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-yellow-500 dark:hover:border-yellow-400 transition-colors disabled:opacity-50"
          >
            <svg v-if="loading.validation" class="animate-spin w-8 h-8 text-yellow-500 mb-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <svg v-else class="w-8 h-8 text-yellow-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <span class="text-sm font-medium text-gray-900 dark:text-white">
              {{ loading.validation ? '驗證中...' : '資料完整性驗證' }}
            </span>
          </button>

          <button
            @click="enableDebugMode"
            class="flex flex-col items-center p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-purple-500 dark:hover:border-purple-400 transition-colors"
          >
            <svg class="w-8 h-8 text-purple-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="text-sm font-medium text-gray-900 dark:text-white">
              {{ debugModeEnabled ? '除錯模式：已啟用' : '啟用除錯模式' }}
            </span>
          </button>
        </div>
      </div>

      <!-- 系統狀態總覽 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Firebase 狀態 -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"/>
            </svg>
            Firebase Realtime Database
          </h3>
          
          <div class="space-y-3">
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <span class="text-sm text-gray-600 dark:text-gray-300">連接狀態</span>
              <span :class="[
                'text-sm font-medium px-2 py-1 rounded',
                systemHealth?.firebase_connection ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
              ]">
                {{ systemHealth?.firebase_connection ? '已連接' : '未連接' }}
              </span>
            </div>
            
            <div v-if="systemHealth?.configuration" class="space-y-2">
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-300">專案ID</span>
                <span :class="[
                  'text-xs px-2 py-1 rounded',
                  systemHealth.configuration.project_id ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                ]">
                  {{ systemHealth.configuration.project_id ? '已配置' : '未配置' }}
                </span>
              </div>
              
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-300">資料庫URL</span>
                <span :class="[
                  'text-xs px-2 py-1 rounded',
                  systemHealth.configuration.database_url ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                ]">
                  {{ systemHealth.configuration.database_url ? '已配置' : '未配置' }}
                </span>
              </div>
              
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-300">憑證檔案</span>
                <span :class="[
                  'text-xs px-2 py-1 rounded',
                  systemHealth.configuration.credentials_file_exists ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                ]">
                  {{ systemHealth.configuration.credentials_file_exists ? '存在' : '缺失' }}
                </span>
              </div>
            </div>

            <div v-if="systemHealth?.database_connectivity" class="p-3 bg-blue-50 dark:bg-blue-900 rounded-lg">
              <div class="text-sm text-blue-800 dark:text-blue-300">
                資料庫資料: {{ systemHealth.database_connectivity.data_count || 0 }} 筆對話
              </div>
            </div>
          </div>
        </div>

        <!-- MySQL 狀態 -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
              <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
            </svg>
            MySQL 資料庫
          </h3>
          
          <div class="space-y-3">
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <span class="text-sm text-gray-600 dark:text-gray-300">連接狀態</span>
              <span :class="[
                'text-sm font-medium px-2 py-1 rounded',
                systemHealth?.mysql?.connection ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
              ]">
                {{ systemHealth?.mysql?.connection ? '已連接' : '未連接' }}
              </span>
            </div>
            
            <div v-if="systemHealth?.mysql" class="grid grid-cols-2 gap-3">
              <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div class="text-xs text-gray-500 dark:text-gray-400">總對話數</div>
                <div class="text-lg font-semibold text-gray-900 dark:text-white">
                  {{ systemHealth.mysql.conversations_count || 0 }}
                </div>
              </div>
              
              <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div class="text-xs text-gray-500 dark:text-gray-400">總客戶數</div>
                <div class="text-lg font-semibold text-gray-900 dark:text-white">
                  {{ systemHealth.mysql.customers_count || 0 }}
                </div>
              </div>
              
              <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div class="text-xs text-gray-500 dark:text-gray-400">LINE客戶</div>
                <div class="text-lg font-semibold text-gray-900 dark:text-white">
                  {{ systemHealth.mysql.line_customers || 0 }}
                </div>
              </div>
              
              <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <div class="text-xs text-gray-500 dark:text-gray-400">已分配客戶</div>
                <div class="text-lg font-semibold text-gray-900 dark:text-white">
                  {{ systemHealth.mysql.assigned_customers || 0 }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 同步結果與驗證結果 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 上次同步結果 -->
        <div v-if="lastSyncResult" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">上次同步結果</h3>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 dark:text-gray-300">處理總數</span>
              <span class="text-sm font-medium text-gray-900 dark:text-white">{{ lastSyncResult.total_found || 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 dark:text-gray-300">成功同步</span>
              <span class="text-sm font-medium text-green-600 dark:text-green-400">{{ lastSyncResult.synced || 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 dark:text-gray-300">同步失敗</span>
              <span class="text-sm font-medium text-red-600 dark:text-red-400">{{ lastSyncResult.failed || 0 }}</span>
            </div>
            <div class="mt-4 w-full bg-gray-200 rounded-full h-2">
              <div 
                class="bg-green-600 h-2 rounded-full"
                :style="{ width: `${getSyncSuccessRate()}%` }"
              ></div>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 text-center">
              成功率: {{ getSyncSuccessRate() }}%
            </div>
          </div>
        </div>

        <!-- 資料驗證結果 -->
        <div v-if="lastValidationResult" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">資料完整性驗證</h3>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 dark:text-gray-300">MySQL 記錄</span>
              <span class="text-sm font-medium text-gray-900 dark:text-white">{{ lastValidationResult.mysql_count || 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 dark:text-gray-300">Firebase 記錄</span>
              <span class="text-sm font-medium text-gray-900 dark:text-white">{{ lastValidationResult.firebase_count || 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 dark:text-gray-300">資料一致性</span>
              <span :class="[
                'text-sm font-medium px-2 py-1 rounded',
                lastValidationResult.is_consistent ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
              ]">
                {{ lastValidationResult.is_consistent ? '一致' : '不一致' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- 建議操作 -->
      <div v-if="systemHealth?.recommendations?.length" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">系統建議</h3>
        <div class="space-y-2">
          <div 
            v-for="(recommendation, index) in systemHealth.recommendations" 
            :key="index"
            class="flex items-start space-x-3 p-3 bg-blue-50 dark:bg-blue-900 rounded-lg"
          >
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <span class="text-sm text-blue-800 dark:text-blue-300">{{ recommendation }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast 通知 -->
    <div
      v-if="notification.show"
      :class="[
        'fixed top-4 right-4 p-4 rounded-lg shadow-lg transition-all duration-300 z-50',
        notification.type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        notification.type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        notification.type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
      ]"
    >
      <div class="flex items-center space-x-2">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
          <path v-if="notification.type === 'success'" fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
          <path v-else-if="notification.type === 'error'" fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          <path v-else fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <span>{{ notification.message }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ['auth', 'role:admin,manager,executive']
})

// Composables
const { $api } = useNuxtApp()
const { user } = useAuth()

// Reactive data
const systemHealth = ref(null)
const lastSyncResult = ref(null)
const lastValidationResult = ref(null)
const debugModeEnabled = ref(false)

// Loading states
const loading = ref({
  healthCheck: false,
  sync: false,
  validation: false
})

// Notification state
const notification = ref({
  show: false,
  type: 'info',
  message: ''
})

// Computed
const canAccessDebug = computed(() => {
  if (!user.value) return false
  const allowedRoles = ['admin', 'manager', 'executive']
  const userRoles = user.value.roles || []
  return allowedRoles.some(role => 
    userRoles.some(userRole => userRole.name === role)
  )
})

// Methods
const showNotification = (type, message, duration = 5000) => {
  notification.value = { show: true, type, message }
  setTimeout(() => {
    notification.value.show = false
  }, duration)
}

const getOverallStatusText = () => {
  if (!systemHealth.value) return '未檢查'
  switch (systemHealth.value.overall_status) {
    case 'healthy': return '系統正常'
    case 'warning': return '系統警告'
    case 'critical': return '系統異常'
    default: return '狀態未知'
  }
}

const getSyncSuccessRate = () => {
  if (!lastSyncResult.value) return 0
  const total = lastSyncResult.value.total_found || 0
  const success = lastSyncResult.value.synced || 0
  return total > 0 ? Math.round((success / total) * 100) : 0
}

const refreshHealthCheck = async () => {
  loading.value.healthCheck = true
  try {
    const response = await $api('/debug/system/health', {
      method: 'GET'
    })
    
    if (response.success) {
      systemHealth.value = response.health
      showNotification('success', '系統健康檢查完成')
    } else {
      throw new Error(response.error || '健康檢查失敗')
    }
  } catch (error) {
    console.error('健康檢查失敗:', error)
    showNotification('error', '健康檢查失敗：' + error.message)
  } finally {
    loading.value.healthCheck = false
  }
}

const syncToFirebase = async () => {
  loading.value.sync = true
  try {
    const response = await $api('/debug/chat/batch-sync', {
      method: 'POST',
      body: JSON.stringify({
        limit: 100,
        force: false
      })
    })
    
    if (response.success) {
      lastSyncResult.value = response.data
      showNotification('success', `Firebase同步完成：${response.data.synced} 成功，${response.data.failed} 失敗`)
      
      // 同步完成後自動刷新健康檢查
      setTimeout(() => {
        refreshHealthCheck()
      }, 2000)
    } else {
      throw new Error(response.error || '同步失敗')
    }
  } catch (error) {
    console.error('Firebase 同步失敗:', error)
    showNotification('error', 'Firebase 同步失敗：' + error.message)
  } finally {
    loading.value.sync = false
  }
}

const validateData = async () => {
  loading.value.validation = true
  try {
    const response = await $api('/debug/chat/validate-integrity', {
      method: 'POST',
      body: JSON.stringify({
        check_all: true
      })
    })
    
    if (response.success) {
      lastValidationResult.value = response.data
      const status = response.data.is_consistent ? 'success' : 'warning'
      const message = response.data.is_consistent ? '資料驗證通過，資料一致' : '發現資料不一致問題'
      showNotification(status, message)
    } else {
      throw new Error(response.error || '驗證失敗')
    }
  } catch (error) {
    console.error('資料驗證失敗:', error)
    showNotification('error', '資料驗證失敗：' + error.message)
  } finally {
    loading.value.validation = false
  }
}

const enableDebugMode = () => {
  debugModeEnabled.value = !debugModeEnabled.value
  localStorage.setItem('debug_panel_enabled', debugModeEnabled.value.toString())
  localStorage.setItem('firebase_debug_mode', debugModeEnabled.value.toString())
  
  const message = debugModeEnabled.value ? 
    'Firebase 除錯模式已啟用，聊天室頁面將顯示除錯面板' : 
    'Firebase 除錯模式已關閉'
    
  showNotification('success', message)
}

// 頁面初始化
onMounted(() => {
  // 檢查當前除錯模式狀態
  debugModeEnabled.value = localStorage.getItem('firebase_debug_mode') === 'true'
  
  // 如果有權限，自動執行健康檢查
  if (canAccessDebug.value) {
    refreshHealthCheck()
  }
})

// 頁面標題
useHead({
  title: '系統除錯 - 設定'
})
</script>