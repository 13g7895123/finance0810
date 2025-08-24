<template>
  <div 
    v-if="isDebugEnabled && canShowDebugPanel" 
    :class="[
      'fixed bg-white shadow-2xl border border-gray-200 rounded-lg transition-all duration-300 z-50',
      isCollapsed ? 'w-12 h-12' : 'w-96 max-h-[80vh] overflow-hidden',
      positionClasses
    ]"
  >
    <!-- Header -->
    <div 
      class="flex items-center justify-between p-3 bg-gray-50 border-b border-gray-200 cursor-pointer"
      @click="toggleCollapse"
    >
      <div v-if="!isCollapsed" class="flex items-center space-x-2">
        <div :class="[
          'w-3 h-3 rounded-full',
          systemHealth?.overall_status === 'healthy' ? 'bg-green-500' : 
          systemHealth?.overall_status === 'warning' ? 'bg-yellow-500' : 'bg-red-500'
        ]"></div>
        <h3 class="text-sm font-semibold text-gray-700">除錯面板</h3>
      </div>
      
      <button
        class="p-1 hover:bg-gray-200 rounded transition-colors"
        :class="isCollapsed ? 'w-full h-full flex items-center justify-center' : ''"
      >
        <svg 
          :class="[
            'w-4 h-4 text-gray-600 transition-transform',
            isCollapsed ? '' : (isCollapsed ? 'rotate-180' : '')
          ]"
          fill="none" 
          stroke="currentColor" 
          viewBox="0 0 24 24"
        >
          <path 
            stroke-linecap="round" 
            stroke-linejoin="round" 
            stroke-width="2" 
            :d="isCollapsed ? 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' : 'M19 9l-7 7-7-7'"
          />
        </svg>
      </button>
    </div>

    <!-- Content -->
    <div v-if="!isCollapsed" class="overflow-y-auto max-h-[calc(80vh-3rem)]">
      <!-- Status Overview -->
      <div class="p-3 border-b border-gray-100">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">系統狀態</div>
        <div class="grid grid-cols-2 gap-2">
          <div class="bg-gray-50 rounded p-2">
            <div class="text-xs text-gray-500">Firebase</div>
            <div :class="[
              'text-sm font-medium',
              systemHealth?.firebase_connection ? 'text-green-600' : 'text-red-600'
            ]">
              {{ systemHealth?.firebase_connection ? '正常' : '異常' }}
            </div>
          </div>
          <div class="bg-gray-50 rounded p-2">
            <div class="text-xs text-gray-500">MySQL</div>
            <div :class="[
              'text-sm font-medium',
              systemHealth?.mysql?.connection ? 'text-green-600' : 'text-red-600'
            ]">
              {{ systemHealth?.mysql?.connection ? '正常' : '異常' }}
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="p-3 border-b border-gray-100">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">快速操作</div>
        <div class="space-y-2">
          <button
            @click="refreshHealthCheck"
            :disabled="loading.healthCheck"
            class="w-full text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded hover:bg-blue-100 disabled:opacity-50 transition-colors flex items-center justify-center space-x-1"
          >
            <svg v-if="loading.healthCheck" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <span>{{ loading.healthCheck ? '檢查中...' : '健康檢查' }}</span>
          </button>
          
          <button
            @click="syncToFirebase"
            :disabled="loading.sync"
            class="w-full text-xs bg-green-50 text-green-700 px-2 py-1 rounded hover:bg-green-100 disabled:opacity-50 transition-colors flex items-center justify-center space-x-1"
          >
            <svg v-if="loading.sync" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <span>{{ loading.sync ? '同步中...' : 'Firebase 同步' }}</span>
          </button>
          
          <button
            @click="validateData"
            :disabled="loading.validation"
            class="w-full text-xs bg-yellow-50 text-yellow-700 px-2 py-1 rounded hover:bg-yellow-100 disabled:opacity-50 transition-colors flex items-center justify-center space-x-1"
          >
            <svg v-if="loading.validation" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <span>{{ loading.validation ? '驗證中...' : '資料驗證' }}</span>
          </button>
        </div>
      </div>

      <!-- Detailed Information -->
      <div v-if="showDetails" class="p-3 space-y-3">
        <!-- Firebase Details -->
        <div v-if="systemHealth?.firebase" class="bg-gray-50 rounded p-2">
          <div class="text-xs font-medium text-gray-700 mb-1">Firebase 詳細資訊</div>
          <div class="space-y-1 text-xs">
            <div class="flex justify-between">
              <span class="text-gray-500">連接狀態：</span>
              <span :class="systemHealth.firebase_connection ? 'text-green-600' : 'text-red-600'">
                {{ systemHealth.firebase_connection ? '已連接' : '斷線' }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">專案ID：</span>
              <span class="text-gray-700">{{ systemHealth.firebase?.configuration?.project_id ? '已設定' : '未設定' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">資料庫URL：</span>
              <span class="text-gray-700">{{ systemHealth.firebase?.configuration?.database_url ? '已設定' : '未設定' }}</span>
            </div>
          </div>
        </div>

        <!-- MySQL Details -->
        <div v-if="systemHealth?.mysql" class="bg-gray-50 rounded p-2">
          <div class="text-xs font-medium text-gray-700 mb-1">MySQL 資訊</div>
          <div class="space-y-1 text-xs">
            <div class="flex justify-between">
              <span class="text-gray-500">對話總數：</span>
              <span class="text-gray-700">{{ systemHealth.mysql?.conversations_count || 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">客戶總數：</span>
              <span class="text-gray-700">{{ systemHealth.mysql?.customers_count || 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">LINE客戶：</span>
              <span class="text-gray-700">{{ systemHealth.mysql?.line_customers || 0 }}</span>
            </div>
          </div>
        </div>

        <!-- Sync Results -->
        <div v-if="lastSyncResult" class="bg-gray-50 rounded p-2">
          <div class="text-xs font-medium text-gray-700 mb-1">上次同步結果</div>
          <div class="space-y-1 text-xs">
            <div class="flex justify-between">
              <span class="text-gray-500">成功：</span>
              <span class="text-green-600">{{ lastSyncResult.synced || 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">失敗：</span>
              <span class="text-red-600">{{ lastSyncResult.failed || 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">總計：</span>
              <span class="text-gray-700">{{ lastSyncResult.total_found || 0 }}</span>
            </div>
          </div>
        </div>

        <!-- Validation Results -->
        <div v-if="lastValidationResult" class="bg-gray-50 rounded p-2">
          <div class="text-xs font-medium text-gray-700 mb-1">資料驗證結果</div>
          <div class="space-y-1 text-xs">
            <div class="flex justify-between">
              <span class="text-gray-500">MySQL：</span>
              <span class="text-gray-700">{{ lastValidationResult.mysql_count || 0 }} 筆</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Firebase：</span>
              <span class="text-gray-700">{{ lastValidationResult.firebase_count || 0 }} 筆</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">一致性：</span>
              <span :class="lastValidationResult.is_consistent ? 'text-green-600' : 'text-red-600'">
                {{ lastValidationResult.is_consistent ? '一致' : '不一致' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer with Settings -->
      <div class="border-t border-gray-100 p-2 bg-gray-50">
        <button
          @click="showDetails = !showDetails"
          class="w-full text-xs text-gray-500 hover:text-gray-700 py-1"
        >
          {{ showDetails ? '隱藏詳細資訊' : '顯示詳細資訊' }}
        </button>
      </div>
    </div>

    <!-- Toast Notifications -->
    <div
      v-if="notification.show"
      :class="[
        'fixed top-4 right-4 p-3 rounded-lg shadow-lg transition-all duration-300 z-[60]',
        notification.type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        notification.type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        notification.type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
      ]"
    >
      <div class="flex items-center space-x-2">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path v-if="notification.type === 'success'" fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
          <path v-else-if="notification.type === 'error'" fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          <path v-else fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <span class="text-sm">{{ notification.message }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

// Composables
const { get, post } = useApi()
const { user } = useAuth()

// Reactive data
const isCollapsed = ref(true)
const showDetails = ref(false)
const systemHealth = ref(null)
const lastSyncResult = ref(null)
const lastValidationResult = ref(null)

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

// Position (could be configurable)
const position = ref('bottom-right')

// Computed properties
const isDebugEnabled = computed(() => {
  // Check if debug mode is enabled (could be from config or environment)
  return process.dev || localStorage.getItem('debug_panel_enabled') === 'true'
})

const canShowDebugPanel = computed(() => {
  // Check if user has admin/manager/executive role
  if (!user.value) return false
  
  const allowedRoles = ['admin', 'manager', 'executive']
  const userRoles = user.value.roles || []
  
  return allowedRoles.some(role => 
    userRoles.some(userRole => userRole.name === role)
  )
})

const positionClasses = computed(() => {
  const positions = {
    'top-left': 'top-4 left-4',
    'top-right': 'top-4 right-4',
    'bottom-left': 'bottom-4 left-4',
    'bottom-right': 'bottom-4 right-4'
  }
  return positions[position.value] || positions['bottom-right']
})

// Methods
const toggleCollapse = () => {
  isCollapsed.value = !isCollapsed.value
  
  // Auto-refresh health data when expanding
  if (!isCollapsed.value && !systemHealth.value) {
    refreshHealthCheck()
  }
}

const showNotification = (type, message, duration = 3000) => {
  notification.value = { show: true, type, message }
  setTimeout(() => {
    notification.value.show = false
  }, duration)
}

const refreshHealthCheck = async () => {
  loading.value.healthCheck = true
  try {
    const { data: response, error } = await get('/debug/system/health')
    
    if (error) {
      throw new Error(error.message || 'Health check failed')
    }
    
    if (response.success) {
      systemHealth.value = response.health
      showNotification('success', '系統健康檢查完成')
    } else {
      throw new Error(response.error || 'Health check failed')
    }
  } catch (error) {
    console.error('Health check failed:', error)
    showNotification('error', '健康檢查失敗：' + error.message)
  } finally {
    loading.value.healthCheck = false
  }
}

const syncToFirebase = async () => {
  loading.value.sync = true
  try {
    const { data: response, error } = await post('/chat/batch-sync', {
      limit: 50,
      force: false
    })
    
    if (error) {
      throw new Error(error.message || '同步失敗')
    }
    
    if (response.success) {
      lastSyncResult.value = response.data
      showNotification('success', `同步完成：${response.data.synced} 成功，${response.data.failed} 失敗`)
    } else {
      throw new Error(response.error || 'Sync failed')
    }
  } catch (error) {
    console.error('Firebase sync failed:', error)
    showNotification('error', 'Firebase 同步失敗：' + error.message)
  } finally {
    loading.value.sync = false
  }
}

const validateData = async () => {
  loading.value.validation = true
  try {
    const { data: response, error } = await post('/chat/validate-integrity', {
      check_all: true
    })
    
    if (error) {
      throw new Error(error.message || '驗證失敗')
    }
    
    if (response.success) {
      lastValidationResult.value = response.data
      const status = response.data.is_consistent ? 'success' : 'warning'
      const message = response.data.is_consistent ? '資料驗證通過' : '發現資料不一致'
      showNotification(status, message)
    } else {
      throw new Error(response.error || 'Validation failed')
    }
  } catch (error) {
    console.error('Data validation failed:', error)
    showNotification('error', '資料驗證失敗：' + error.message)
  } finally {
    loading.value.validation = false
  }
}

// Auto refresh health check every 5 minutes when panel is expanded
let healthCheckInterval = null

onMounted(() => {
  // Load saved position
  const savedPosition = localStorage.getItem('debug_panel_position')
  if (savedPosition) {
    position.value = savedPosition
  }
  
  // Start auto refresh if panel is enabled
  if (isDebugEnabled.value && canShowDebugPanel.value) {
    healthCheckInterval = setInterval(() => {
      if (!isCollapsed.value) {
        refreshHealthCheck()
      }
    }, 5 * 60 * 1000) // 5 minutes
  }
})

onUnmounted(() => {
  if (healthCheckInterval) {
    clearInterval(healthCheckInterval)
  }
})

// Watch for position changes and save to localStorage
watch(position, (newPosition) => {
  localStorage.setItem('debug_panel_position', newPosition)
}, { immediate: false })

// Expose methods for external access (e.g., from chat page)
defineExpose({
  refreshHealthCheck,
  syncToFirebase,
  validateData,
  toggleCollapse,
  isCollapsed
})
</script>