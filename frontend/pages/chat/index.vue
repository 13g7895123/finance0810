<template>
  <div class="flex bg-gray-50" style="height: calc(100vh - 120px); max-height: calc(100vh - 120px);">
    <!-- 左側用戶列表 -->
    <div class="w-80 bg-white border-r border-gray-300 flex flex-col">
      <!-- 標題和篩選 -->
      <div class="p-4 border-b border-gray-200">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center space-x-3">
            <h2 class="text-lg font-semibold text-gray-900">聊天室</h2>
            <!-- 連線狀態指示器 - 使用新的 Long Polling 狀態 -->
            <ClientOnly>
              <div class="flex items-center space-x-1">
                <div 
                  class="w-2 h-2 rounded-full"
                  :class="{
                    'bg-green-400': connectionStatus === 'connected',
                    'bg-yellow-400': connectionStatus === 'connecting',
                    'bg-red-400': connectionStatus === 'error',
                    'bg-gray-400': connectionStatus === 'disconnected'
                  }"
                ></div>
                <span class="text-xs text-gray-500">
                  {{ getPollingStatusText() }}
                </span>
              </div>
            </ClientOnly>
            
            <!-- 手動刷新按鈕 -->
            <ClientOnly>
              <div class="flex space-x-1" style="display: none;">
                <button 
                  @click="performManualRefresh"
                  :disabled="isRefreshing"
                  class="text-xs px-2 py-1 bg-blue-100 text-blue-600 rounded hover:bg-blue-200 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <span v-if="isRefreshing">刷新中...</span>
                  <span v-else>手動刷新</span>
                </button>
                
                <!-- Long Polling 控制按鈕 -->
                <button 
                  @click="toggleLongPolling"
                  :class="[
                    'text-xs px-2 py-1 rounded transition-colors',
                    isPolling 
                      ? 'bg-green-100 text-green-600 hover:bg-green-200' 
                      : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                  ]"
                >
                  <span v-if="isPolling">停止連接</span>
                  <span v-else>開始連接</span>
                </button>
                
                <!-- 連接狀態信息 -->
                <span class="text-xs text-gray-500">
                  <span v-if="lastError && retryCount > 0">
                    重試 {{ retryCount }}/5
                  </span>
                  <span v-else>
                    {{ formatTime(lastRefreshTime) }}
                  </span>
                </span>
              </div>
            </ClientOnly>
          </div>
          <button class="p-2 text-gray-500 hover:bg-gray-100 rounded-lg">
            <PlusIcon class="w-5 h-5" />
          </button>
        </div>
        
        <!-- 搜尋框 -->
        <div class="relative">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="搜尋用戶..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300  rounded-lg bg-white  text-gray-900  placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
        </div>

        <!-- 篩選按鈕 -->
        <div class="flex gap-2 mt-3">
          <button
            v-for="filter in filters"
            :key="filter.key"
            @click="activeFilter = filter.key"
            class="px-3 py-1 text-sm rounded-full transition-colors duration-200"
            :class="activeFilter === filter.key 
              ? 'bg-blue-500 text-white' 
              : 'bg-gray-100  text-gray-700  hover:bg-gray-200 '"
          >
            {{ filter.label }}
          </button>
        </div>
      </div>

      <!-- 用戶列表 -->
      <div class="flex-1 overflow-y-auto custom-scrollbar-left">
        <!-- 搜尋中載入狀態 -->
        <div v-if="isSearching" class="p-4 text-center">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mx-auto mb-2"></div>
          <p class="text-sm text-gray-500">搜尋中...</p>
        </div>
        
        
        <!-- 搜尋無結果 -->
        <div v-else-if="searchQuery.trim() && filteredUsers.length === 0" class="p-4 text-center">
          <p class="text-sm text-gray-500">沒有找到符合 "{{ searchQuery }}" 的對話</p>
        </div>
        
        <!-- 用戶列表 -->
        <ChatUserList
          v-else
          :users="filteredUsers"
          :activeUserId="activeUserId"
          @userSelect="selectUserWithRealtime"
        />
      </div>
    </div>

    <!-- 右側聊天區域 -->
    <div class="flex-1 flex flex-col">
      <ChatMessageArea
        v-if="selectedUser"
        :user="selectedUser"
        :messages="currentMessages"
        @sendMessage="sendMessage"
      />
      
      <!-- 未選擇用戶時的預設畫面 -->
      <div v-else class="flex-1 flex items-center justify-center bg-gray-50">
        <div class="text-center">
          <ChatBubbleLeftRightIcon class="w-16 h-16 text-gray-400 mx-auto mb-4" />
          <h3 class="text-xl font-medium text-gray-900 mb-2">選擇聊天對象</h3>
          <p class="text-gray-500">從左側列表選擇要聊天的用戶開始對話</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { 
  PlusIcon, 
  MagnifyingGlassIcon,
  ChatBubbleLeftRightIcon 
} from '@heroicons/vue/24/outline'

import { DataValidator } from '~/utils/dataValidator'
import { PerformanceMonitorService } from '~/services/PerformanceMonitorService'
import { useSimplePolling } from '~/composables/useSimplePolling'

definePageMeta({
  middleware: 'auth'
})

const { error: showError } = useNotification()

const authStore = useAuthStore()
const { getConversations, getConversation, replyMessage, getChatStats, searchConversations } = useChat()

// 性能監控服務
const performanceMonitor = new PerformanceMonitorService()

// 性能監控數據查看方法（用於調試）
const getPerformanceMetrics = () => {
  const metrics = performanceMonitor.getMetrics()
  console.log('性能監控數據:', metrics)
  return metrics
}

// 在開發環境下暴露給 window 對象，便於調試
if (process.dev) {
  window.getPerformanceMetrics = getPerformanceMetrics
}

// 使用簡化的輪詢機制
const {
  isPolling,
  connectionStatus,
  lastUpdateTime,
  errorCount,
  startPolling,
  stopPolling,
  manualRefresh,
  resetErrors,
  getConnectionStatusText
} = useSimplePolling()

// 向下兼容的狀態
const isRefreshing = ref(false)
const lastRefreshTime = computed(() => lastUpdateTime.value || new Date())
const autoRefreshEnabled = ref(true)
const lastError = ref(null)
const retryCount = computed(() => errorCount.value)

// 定時輪詢配置
const pollingConfig = ref({
  enabled: false,
  interval: 1000, // 1秒默認，可調整為500ms
  timer: null,
  retryCount: 0,
  maxRetries: 3
})

// 頁面狀態管理
const pageState = ref({
  isActive: true,
  isUnloading: false,
  pendingTimeouts: new Set() // 追蹤所有待處理的計時器
})

// 安全的 setTimeout 包裝器 - 提前定義以避免引用錯誤
const safeSetTimeout = (callback, delay) => {
  if (pageState.value.isUnloading) {
    console.log('頁面已離開，取消計時器')
    return null
  }
  
  const timeoutId = setTimeout(() => {
    pageState.value.pendingTimeouts.delete(timeoutId)
    if (!pageState.value.isUnloading && callback) {
      callback()
    }
  }, delay)
  
  pageState.value.pendingTimeouts.add(timeoutId)
  return timeoutId
}

// 前向聲明 - 將在後面定義實際函數
let startLegacyPolling
let stopLegacyPolling

// 安全的輪詢啟動函數（舊版setInterval）
const safeStartLegacyPolling = (intervalMs) => {
  if (pageState.value.isUnloading) {
    console.log('頁面已離開，不啟動輪詢')
    return
  }
  // startLegacyPolling 將在稍後定義
  if (typeof startLegacyPolling === 'function') {
    startLegacyPolling(intervalMs)
  }
}

// 搜尋查詢
const searchQuery = ref('')

// 篩選選項
const filters = ref([
  { key: 'all', label: '所有訊息' },
  { key: 'unread', label: '未讀' },
  { key: 'favorites', label: '重要' },
  { key: 'archived', label: '封存' }
])

const activeFilter = ref('all')

// 選中的用戶
const activeUserId = ref(null)
const selectedUser = ref(null)

// 載入狀態
const loading = ref(false)
const conversationsLoading = ref(false)
const chatConnectionStatus = ref('ready') // 'ready', 'connected', 'disconnected'

// 簡化的狀態管理
const refreshStatus = ref({
  lastUpdate: null,
  isManual: false
})

// 簡化的時間格式化函數
const formatTime = (timestamp) => {
  if (!timestamp) return ''
  try {
    const now = new Date()
    const time = new Date(timestamp)
    const diffInMinutes = Math.floor((now - time) / (1000 * 60))
    
    if (diffInMinutes < 1) return '剛剛'
    if (diffInMinutes < 60) return `${diffInMinutes}分鐘前`
    if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}小時前`
    return `${Math.floor(diffInMinutes / 1440)}天前`
  } catch (e) {
    console.warn('Time formatting error:', e)
    return ''
  }
}

// 簡化的連線狀態管理
const updateChatConnectionStatus = () => {
  chatConnectionStatus.value = 'ready' // 始終顯示為準備就緒
}

// 簡化的手動刷新方法
const performManualRefresh = async () => {
  if (isRefreshing.value) {
    console.log('刷新已在進行中，跳過')
    return
  }
  
  isRefreshing.value = true
  
  try {
    console.log('手動刷新聊天室數據...')
    
    // 使用簡化輪詢的手動刷新
    const success = await manualRefresh() // 從 useSimplePolling 來的方法
    
    if (success) {
      console.log('聊天室數據刷新完成')
      resetErrors() // 重置錯誤計數
    } else {
      console.warn('聊天室數據刷新失敗')
    }
    
  } catch (error) {
    console.error('刷新聊天室數據失敗:', error)
    performanceMonitor.recordError('refresh_error', error)
  } finally {
    isRefreshing.value = false
  }
}

// 定時輪詢功能（增強防競爭版）- 重新命名避免與Long Polling衝突
startLegacyPolling = (intervalMs = 1000) => {
  if (pageState.value.isUnloading) {
    console.log('頁面已離開，不啟動輪詢')
    return
  }
  
  if (pollingConfig.value.enabled) {
    console.log('輪詢已在運行中')
    return
  }
  
  pollingConfig.value.enabled = true
  pollingConfig.value.interval = intervalMs
  pollingConfig.value.retryCount = 0
  
  console.log(`啟動定時輪詢，間隔: ${intervalMs}ms`)
  
  const poll = async () => {
    if (!pollingConfig.value.enabled || pageState.value.isUnloading) return
    
    // 檢查頁面可見性
    if (process.client && document.hidden) {
      console.log('頁面隱藏，跳過本次輪詢')
      scheduleNextPoll()
      return
    }
    
    // 檢查是否有其他API操作正在進行
    if (globalLock.value || loadingLocks.value.userSelection || isRefreshing.value) {
      console.log('有其他操作正在進行，跳過本次輪詢')
      scheduleNextPoll()
      return
    }
    
    // 檢查是否達到最大重試次數
    if (pollingConfig.value.retryCount >= pollingConfig.value.maxRetries) {
      console.warn('輪詢重試次數過多，暫停輪詢')
      stopLegacyPolling()
      return
    }
    
    try {
      await performManualRefresh()
    } catch (error) {
      console.error('輪詢更新失敗:', error)
    }
    
    scheduleNextPoll()
  }
  
  const scheduleNextPoll = () => {
    if (pollingConfig.value.enabled && !pageState.value.isUnloading) {
      pollingConfig.value.timer = safeSetTimeout(poll, pollingConfig.value.interval)
    }
  }
  
  // 延遲執行第一次輪詢，避免與初始化競爭
  safeSetTimeout(() => {
    if (pollingConfig.value.enabled) {
      poll()
    }
  }, 1000)
}

// 停止舊版定時輪詢
stopLegacyPolling = () => {
  pollingConfig.value.enabled = false
  if (pollingConfig.value.timer) {
    clearTimeout(pollingConfig.value.timer)
    pollingConfig.value.timer = null
  }
  console.log('已停止舊版定時輪詢')
}

// 獲取連接狀態文字 - 使用簡化的輪詢狀態
const getPollingStatusText = () => {
  // 使用從 useSimplePolling 來的方法
  return getConnectionStatusText()
}

// 切換 Long Polling 狀態
const toggleLongPolling = async () => {
  if (isPolling.value) {
    stopPolling()
  } else {
    await startSimplePolling()
  }
}

// 啟動簡化的輪詢 (先聲明，在所有回調函數定義後再實作)
let startSimplePolling = null

// 處理輪詢更新（保持排序穩定）
const handlePollingUpdate = async (data) => {
  console.log('收到輪詢更新:', data)
  
  try {
    if (data && Array.isArray(data)) {
      // 轉換 API 數據格式到前端格式，與 loadConversations 保持一致
      const apiUsers = data.map(conv => {
        // 確保客戶名稱正確顯示
        const customerName = conv.customer?.name || conv.customer_name || conv.last_customer_name || '客戶'
        
        return {
          id: parseInt(conv.line_user_id),
          name: customerName,
          role: 'line_customer',
          avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(customerName)}&background=00C300&color=fff`,
          lastMessage: conv.last_message || conv.last_customer_message || conv.last_system_message || '',
          timestamp: new Date(conv.last_message_time),
          unreadCount: conv.unread_count || 0,
          online: false,
          isBot: true,
          lineUserId: conv.line_user_id,
          customerInfo: {
            phone: conv.customer?.phone || conv.customer_phone || '',
            region: conv.customer?.region || conv.customer_region || '',
            source: conv.customer?.source || conv.customer_source || '',
            status: conv.customer?.status || conv.customer_status || ''
          }
        }
      })
      
      // 去重並保持穩定的排序
      const userMap = new Map()
      apiUsers.forEach(user => {
        if (!user.lineUserId) return
        userMap.set(user.lineUserId, user)
      })
      
      // 轉回陣列並按時間排序（最新的在前面）
      const uniqueUsers = Array.from(userMap.values())
      const sortedUsers = uniqueUsers.sort((a, b) => {
        const timeA = new Date(a.timestamp).getTime()
        const timeB = new Date(b.timestamp).getTime()
        return timeB - timeA // 最新的在前面
      })
      
      // 檢查是否有實際變化（只比較關鍵資訊）
      const currentKeys = apiConversations.value.map(u => u.lineUserId).join(',')
      const newKeys = sortedUsers.map(u => u.lineUserId).join(',')
      
      // 只有在用戶列表實際改變時才更新，避免破壞排序
      if (currentKeys !== newKeys) {
        apiConversations.value = sortedUsers
        console.log(`輪詢更新對話列表: ${uniqueUsers.length} 筆記錄 (列表已更新)`)
      } else {
        // 更新現有用戶的詳細資訊，但保持順序不變
        apiConversations.value.forEach((currentUser, index) => {
          const updatedUser = sortedUsers.find(u => u.lineUserId === currentUser.lineUserId)
          if (updatedUser) {
            // 只更新可能變化的資訊，保持引用穩定
            currentUser.unreadCount = updatedUser.unreadCount
            currentUser.lastMessage = updatedUser.lastMessage
            // 不更新 timestamp 以保持排序穩定
          }
        })
        console.log(`輪詢更新對話列表: ${uniqueUsers.length} 筆記錄 (詳細資訊已更新，排序保持)`)
      }
    }
  } catch (error) {
    console.error('處理輪詢更新時發生錯誤:', error)
  }
}

// 處理輪詢錯誤
const handlePollingError = (error) => {
  console.error('輪詢錯誤:', error)
  
  // 顯示詳細錯誤資訊
  let errorMessage = '連接失敗'
  if (error?.error) {
    errorMessage = error.error
  } else if (error?.message) {
    errorMessage = error.message
  }
  
  // 在開發模式下顯示更多詳細資訊
  if (process.dev && error?.debug_info) {
    console.error('詳細錯誤資訊:', error.debug_info)
    errorMessage += `\n[開發模式] ${error.debug_info.file}:${error.debug_info.line}`
  }
  
  if (retryCount.value >= 3) {
    showError(errorMessage)
  }
}

// 處理認證錯誤
const handleAuthError = (error) => {
  console.error('認證錯誤:', error)
  
  let errorMessage = '認證失敗，請重新登入'
  if (error?.error && error.error !== 'Authentication required') {
    errorMessage = error.error
  }
  
  showError(errorMessage)
}

// 現在定義 startSimplePolling 函數（在所有回調函數定義之後）
startSimplePolling = async () => {
  console.log('=== startSimplePolling 呼叫開始 ===')
  console.log('回調函數檢查:', {
    handlePollingUpdate: {
      exists: !!handlePollingUpdate,
      type: typeof handlePollingUpdate,
      isFunction: typeof handlePollingUpdate === 'function'
    },
    handlePollingError: {
      exists: !!handlePollingError,
      type: typeof handlePollingError,
      isFunction: typeof handlePollingError === 'function'
    },
    handleAuthError: {
      exists: !!handleAuthError,
      type: typeof handleAuthError,
      isFunction: typeof handleAuthError === 'function'
    }
  })
  
  // 驗證所有回調函數都已定義
  if (typeof handlePollingUpdate !== 'function') {
    console.error('handlePollingUpdate 未定義或不是函數!', typeof handlePollingUpdate)
    throw new Error('handlePollingUpdate is not a function')
  }
  
  if (typeof handlePollingError !== 'function') {
    console.error('handlePollingError 未定義或不是函數!', typeof handlePollingError)
    throw new Error('handlePollingError is not a function')
  }
  
  if (typeof handleAuthError !== 'function') {
    console.error('handleAuthError 未定義或不是函數!', typeof handleAuthError)
    throw new Error('handleAuthError is not a function')
  }
  
  console.log('所有回調函數驗證通過，開始啟動輪詢')
  
  startPolling({
    interval: 2000, // 2秒輪詢間隔
    onUpdate: handlePollingUpdate,
    onError: handlePollingError,
    onAuthError: handleAuthError
  })
  
  console.log('=== startSimplePolling 呼叫完成 ===')
}

// API 數據狀態
const apiConversations = ref([])
const apiMessages = ref({})

// 全域載入鎖，防止競態條件和API競爭
const globalLock = ref(false)
const loadingLocks = ref({
  conversations: false,
  messages: false,
  userSelection: false,
  apiCallInProgress: false
})

// API 調用狀態追蹤
const apiCallTracker = ref({
  activeApiCalls: new Set(),
  lastApiCall: null,
  callCounter: 0
})

// 載入對話列表
const loadConversations = async () => {
  // 防止重複載入和API競爭
  if (loadingLocks.value.conversations || loadingLocks.value.userSelection || loadingLocks.value.apiCallInProgress) {
    console.log('對話列表正在載入中或有其他API操作進行中，跳過重複請求')
    return
  }
  
  const callId = `conversations_${++apiCallTracker.value.callCounter}`
  apiCallTracker.value.activeApiCalls.add(callId)
  apiCallTracker.value.lastApiCall = callId

  try {
    loadingLocks.value.conversations = true
    conversationsLoading.value = true
    console.log('開始載入對話列表...')
    
    // 性能監控 - 開始計時
    const startTime = performance.now()
    
    const response = await getConversations()
    
    // 性能監控 - 記錄API調用
    const endTime = performance.now()
    const duration = endTime - startTime
    performanceMonitor.recordApiCall('/api/chats', duration, !!response?.data)
    
    if (response?.data) {
      // 轉換 API 數據格式到前端格式，並添加數據校驗
      const apiUsers = response.data.map(conv => {
        // 校驗對話數據
        const validationResult = DataValidator.validateConversation({
          line_user_id: conv.line_user_id,
          unread_count: conv.unread_count || 0
        })
        
        if (!validationResult.valid) {
          console.warn('對話數據校驗失敗:', validationResult.errors, conv)
        }
        
        // 確保客戶名稱正確顯示
        const customerName = conv.customer?.name || conv.customer_name || conv.last_customer_name || '客戶'
        
        return {
          id: parseInt(conv.line_user_id),
          name: customerName,
          role: 'line_customer',
          avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(customerName)}&background=00C300&color=fff`,
          lastMessage: conv.last_message || conv.last_customer_message || conv.last_system_message || '',
          timestamp: new Date(conv.last_message_time),
          unreadCount: conv.unread_count || 0,
          online: false,
          isBot: true,
          lineUserId: conv.line_user_id,
          customerInfo: {
            phone: conv.customer?.phone || conv.customer_phone || '',
            region: conv.customer?.region || conv.customer_region || '',
            source: conv.customer?.source || conv.customer_source || '',
            status: conv.customer?.status || conv.customer_status || ''
          }
        }
      })
      
      // 強化去重處理：確保每個 line_user_id 只有一筆記錄
      const userMap = new Map()
      apiUsers.forEach(user => {
        if (!user.lineUserId) return
        
        const existing = userMap.get(user.lineUserId)
        if (!existing || user.timestamp > existing.timestamp) {
          userMap.set(user.lineUserId, user)
        }
      })
      
      // 轉回陣列並按時間排序（最新的在前面）
      const uniqueUsers = Array.from(userMap.values())
      const sortedUsers = uniqueUsers.sort((a, b) => {
        const timeA = new Date(a.timestamp).getTime()
        const timeB = new Date(b.timestamp).getTime()
        return timeB - timeA // 最新的在前面
      })
      
      // 直接設定對話列表，確保排序一致
      apiConversations.value = sortedUsers
      console.log(`載入對話列表: ${uniqueUsers.length} 筆唯一記錄`)
    }
  } catch (error) {
    console.error('Failed to load conversations:', error)
    
    // 顯示詳細錯誤資訊給用戶
    let errorMessage = '載入對話列表失敗'
    if (error?.error) {
      errorMessage = error.error
    } else if (error?.message) {
      errorMessage = error.message
    }
    
    // 在開發模式下記錄詳細資訊
    if (process.dev && error?.debug_info) {
      console.error('詳細錯誤資訊:', error.debug_info)
    }
    
    showError(errorMessage)
  } finally {
    conversationsLoading.value = false
    loadingLocks.value.conversations = false
    apiCallTracker.value.activeApiCalls.delete(callId)
    console.log('對話列表載入完成，清理API追蹤')
  }
}

// 載入特定對話的訊息（簡化版）
const loadConversationMessages = async (userId) => {
  try {
    console.log('開始載入用戶訊息:', userId)
    
    const response = await getConversation(userId)
    console.log('API response for conversation:', response)
    
    if (response?.data && Array.isArray(response.data)) {
      // 轉換 API 數據格式到前端格式
      const transformedMessages = response.data.map(msg => {
        return {
          id: msg.id,
          senderId: msg.is_from_customer ? parseInt(msg.line_user_id) : 'system',
          content: msg.message_content,
          timestamp: new Date(msg.message_timestamp),
          type: msg.message_type || 'text',
          isBot: !msg.is_from_customer,
          isCustomer: msg.is_from_customer,
          isAutoReply: !msg.is_from_customer,
          metadata: msg.metadata || {},
          version: msg.version || 1
        }
      })
      
      // 按時間排序（舊的在前面，新的在後面）
      transformedMessages.sort((a, b) => {
        const timeA = new Date(a.timestamp).getTime()
        const timeB = new Date(b.timestamp).getTime()
        return timeA - timeB
      })
      
      console.log('Transformed messages:', transformedMessages.length, '筆訊息')
      
      // 直接設定到對應的用戶訊息中
      apiMessages.value[userId] = transformedMessages
      
      return transformedMessages
    } else {
      console.log('No messages found or invalid response format')
      apiMessages.value[userId] = []
      return []
    }
  } catch (error) {
    console.error('Failed to load conversation messages:', error)
    apiMessages.value[userId] = []
    return []
  }
}

// 時間排序函數
const sortByTime = (users) => {
  if (!Array.isArray(users)) {
    console.warn('sortByTime: 輸入不是陣列:', users)
    return []
  }
  
  return users.sort((a, b) => {
    try {
      // 確保時間戳有效
      const timeA = a?.timestamp ? new Date(a.timestamp).getTime() : 0
      const timeB = b?.timestamp ? new Date(b.timestamp).getTime() : 0
      
      // 按時間排序（最新的在前面）
      if (timeA !== timeB) {
        return timeB - timeA
      }
      
      // 時間相同時按ID排序確保穩定性
      const idA = a?.id || 0
      const idB = b?.id || 0
      return idB - idA
    } catch (error) {
      console.error('sortByTime 排序錯誤:', error, { a, b })
      return 0
    }
  })
}

// 只使用 API 數據
const combinedUsers = computed(() => {
  // 只使用 API 對話數據，移除所有模擬數據
  // 數據已在 loadConversations 中排序和去重，直接返回
  if (!Array.isArray(apiConversations.value)) {
    return []
  }
  
  // 最終確保去重：以 lineUserId 為唯一標識
  const uniqueMap = new Map()
  apiConversations.value.forEach(user => {
    if (user?.lineUserId && !uniqueMap.has(user.lineUserId)) {
      uniqueMap.set(user.lineUserId, user)
    }
  })
  
  return Array.from(uniqueMap.values())
})

// 搜尋結果
const searchResults = ref([])
const isSearching = ref(false)

// 執行搜尋
const performSearch = async (query) => {
  if (!query.trim()) {
    searchResults.value = []
    return
  }
  
  try {
    isSearching.value = true
    console.log('Searching for:', query.trim()) // Debug log
    
    const response = await searchConversations(query.trim())
    console.log('Search response:', response) // Debug log
    
    if (response?.data && Array.isArray(response.data)) {
      // 轉換搜尋結果格式
      const searchUsers = response.data.map(conv => ({
        id: parseInt(conv.line_user_id),
        name: conv.customer?.name || '客戶',
        role: 'line_customer',
        avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(conv.customer?.name || '客戶')}&background=00C300&color=fff`,
        lastMessage: conv.last_message || '',
        timestamp: new Date(conv.last_message_time),
        unreadCount: conv.unread_count || 0,
        online: false,
        isBot: true,
        lineUserId: conv.line_user_id,
        customerInfo: {
          phone: conv.customer?.phone || '',
          region: conv.customer?.region || '',
          source: conv.customer?.source || '',
          status: conv.customer?.status || ''
        }
      }))
      
      // 強化去重處理：使用 Map 確保唯一性
      const userMap = new Map()
      searchUsers.forEach(user => {
        if (!user.lineUserId) return
        
        const existing = userMap.get(user.lineUserId)
        if (!existing || user.timestamp > existing.timestamp) {
          userMap.set(user.lineUserId, user)
        }
      })
      
      // 轉回陣列並按時間排序（最新的在前面）
      const uniqueSearchUsers = Array.from(userMap.values())
      const sortedSearchUsers = uniqueSearchUsers.sort((a, b) => {
        const timeA = new Date(a.timestamp).getTime()
        const timeB = new Date(b.timestamp).getTime()
        return timeB - timeA // 最新的在前面
      })
      
      // 數據比較：使用更簡單的雜湊比較
      const currentSearchHash = searchResults.value.map(u => `${u.lineUserId}:${u.timestamp?.getTime()}`).sort().join('|')
      const newSearchHash = sortedSearchUsers.map(u => `${u.lineUserId}:${u.timestamp?.getTime()}`).sort().join('|')
      
      // 只有在搜索結果真正改變時才更新
      if (currentSearchHash !== newSearchHash) {
        searchResults.value = sortedSearchUsers
        console.log('Search results processed:', uniqueSearchUsers.length, '(已更新)') // Debug log
      } else {
        console.log('Search results processed:', uniqueSearchUsers.length, '(無變化)') // Debug log
      }
    } else {
      console.log('No search results or invalid response format')
      searchResults.value = []
    }
  } catch (error) {
    console.error('Search failed:', error)
    
    // 如果API搜尋失敗，清空搜尋結果讓本地搜尋接管
    searchResults.value = []
    
    // 可選：顯示錯誤提示
    console.warn('搜尋API失敗，將使用本地搜尋功能')
  } finally {
    isSearching.value = false
  }
}

// 監聽搜尋查詢變化
let searchTimeout = null
watch(searchQuery, (newQuery) => {
  if (searchTimeout) clearTimeout(searchTimeout)
  
  if (!newQuery.trim()) {
    searchResults.value = []
    return
  }
  
  searchTimeout = safeSetTimeout(() => {
    performSearch(newQuery)
  }, 500)
})

// 根據權限過濾用戶列表
const filteredUsers = computed(() => {
  try {
    // 如果有搜尋結果，優先顯示搜尋結果（已在 performSearch 中排序和去重）
    if (searchQuery.value && searchQuery.value.trim() && searchResults.value && searchResults.value.length > 0) {
      // 搜索結果已經處理過，直接返回副本
      return Array.isArray(searchResults.value) ? [...searchResults.value] : []
    }
    
    let users = combinedUsers.value
    
    // 確保 users 是陣列
    if (!Array.isArray(users)) {
      console.warn('combinedUsers 不是陣列:', users)
      return []
    }

    // 權限過濾 - 業務人員只能看到自己相關的對話和BOT
    if (authStore && authStore.isSales && typeof authStore.hasPermission === 'function' && !authStore.hasPermission('all_access')) {
      users = users.filter(user => {
        if (!user || typeof user !== 'object') return false
        return user.id === authStore.user?.id || 
               user.isBot || 
               user.role === 'dealer_executive' ||
               user.role === 'admin_manager'
      })
    }

    // 本地搜尋過濾（如果沒有遠端搜尋結果）
    if (searchQuery.value && searchQuery.value.trim() && 
        searchResults.value && searchResults.value.length === 0 && 
        !isSearching.value) {
      const query = searchQuery.value.toLowerCase()
      users = users.filter(user => {
        if (!user || typeof user !== 'object') return false
        return (user.name && user.name.toLowerCase().includes(query)) ||
               (user.customerInfo?.phone && user.customerInfo.phone.includes(searchQuery.value)) ||
               (user.customerInfo?.region && user.customerInfo.region.toLowerCase().includes(query))
      })
    }

    // 狀態過濾
    if (activeFilter.value) {
      switch (activeFilter.value) {
        case 'unread':
          users = users.filter(user => user && user.unreadCount > 0)
          break
        case 'favorites':
          users = users.filter(user => user && user.isFavorite)
          break
        case 'archived':
          users = users.filter(user => user && user.isArchived)
          break
      }
    }

    // 數據已在 loadConversations 中排序，這裡只需要去重處理
    // 根據 lineUserId 進行最終去重確保
    const finalUsers = users.reduce((acc, current) => {
      const existing = acc.find(item => item?.lineUserId === current?.lineUserId)
      if (!existing && current?.lineUserId) {
        acc.push(current)
      }
      return acc
    }, [])
    
    return finalUsers
  } catch (error) {
    console.error('filteredUsers computed 發生錯誤:', error)
    return []
  }
})

// 移除所有模擬訊息數據，只使用 API 數據

// 自動更新策略
const autoRefreshTimer = ref(null)

// 頁面可見性監聽增強版
const setupVisibilityListener = () => {
  if (process.client) {
    document.addEventListener('visibilitychange', handleVisibilityChange)
    
    // 在組件卸載時移除監聽器
    onUnmounted(() => {
      document.removeEventListener('visibilitychange', handleVisibilityChange)
    })
  }
}

// 頁面可見性變化使用簡化輪詢
const handleVisibilityChange = async () => {
  if (process.client && !pageState.value.isUnloading) {
    if (!document.hidden && pageState.value.isActive) {
      console.log('頁面變可見，恢復簡化輪詢')
      
      // 手動刷新一次
      if (autoRefreshEnabled.value && !globalLock.value) {
        await performManualRefresh()
      }
      
      // 如果輪詢被停止，則重新啟動
      if (!isPolling.value && !globalLock.value && pageState.value.isActive) {
        console.log('頁面可見，重新啟動簡化輪詢')
        await startSimplePolling()
      }
    } else {
      console.log('頁面隱藏，暫停輪詢節省資源')
      stopPolling()
    }
  }
}

// 當前聊天訊息
const currentMessages = computed(() => {
  try {
    if (!selectedUser.value || typeof selectedUser.value !== 'object') {
      return []
    }
    
    // 只使用 API 數據，LINE BOT 用戶使用 lineUserId 查找
    if (selectedUser.value.isBot && selectedUser.value.lineUserId) {
      const apiMsgs = apiMessages.value[selectedUser.value.lineUserId]
      if (Array.isArray(apiMsgs) && apiMsgs.length > 0) {
        // 按時間排序訊息（舊的在前面，新的在後面）
        return apiMsgs.sort((a, b) => {
          try {
            if (!a || !b || !a.timestamp || !b.timestamp) return 0
            const timeA = new Date(a.timestamp).getTime()
            const timeB = new Date(b.timestamp).getTime()
            return timeA - timeB
          } catch (sortError) {
            console.error('訊息排序錯誤:', sortError)
            return 0
          }
        })
      }
    }
    
    // 沒有 API 數據時返回空陣列
    return []
  } catch (error) {
    console.error('currentMessages computed 發生錯誤:', error)
    return []
  }
})

// 選擇用戶功能已被 selectUserWithRealtime 取代

// 發送訊息
const sendMessage = async (content) => {
  if (!selectedUser.value || !content.trim()) return
  
  try {
    // 如果是 LINE BOT 用戶，使用 API 發送
    if (selectedUser.value.isBot && selectedUser.value.lineUserId) {
      const response = await replyMessage(selectedUser.value.lineUserId, content.trim())
      
      if (response?.conversation) {
        // 添加發送的訊息到對話中
        const newMessage = {
          id: response.conversation.id,
          senderId: authStore.user?.id,
          content: content.trim(),
          timestamp: new Date(response.conversation.message_timestamp),
          type: 'text',
          isBot: false,
          isCustomer: false
        }
        
        // 更新 API 訊息數據
        if (!apiMessages.value[selectedUser.value.lineUserId]) {
          apiMessages.value[selectedUser.value.lineUserId] = []
        }
        apiMessages.value[selectedUser.value.lineUserId].push(newMessage)
      }
    } else {
      // 非 LINE BOT 用戶不支援發送訊息
      console.warn('只支援向 LINE BOT 用戶發送訊息')
      await showError('目前只支援向 LINE 客戶發送訊息')
      return
    }
    
    // 更新 API 對話列表中的對應項目
    const currentTime = new Date()
    const apiUserIndex = apiConversations.value.findIndex(u => u.id === selectedUser.value.id)
    if (apiUserIndex !== -1) {
      apiConversations.value[apiUserIndex].lastMessage = content.trim()
      apiConversations.value[apiUserIndex].timestamp = currentTime
    }
    
    // 發送訊息後延遲刷新
    setTimeout(() => {
      performManualRefresh()
      
      // 如果輪詢被停止，發送訊息後確保輪詢運行
      if (!isPolling.value) {
        console.log('發送訊息後啟動輪詢')
        startSimplePolling()
      }
    }, 1000)
    
  } catch (error) {
    console.error('Failed to send message:', error)
    
    // 顯示詳細錯誤資訊
    let errorMessage = '發送訊息失敗，請重試'
    if (error?.error) {
      errorMessage = error.error
    } else if (error?.message) {
      errorMessage = error.message
    }
    
    // 在開發模式下顯示更多詳細資訊
    if (process.dev && error?.debug_info) {
      console.error('詳細錯誤資訊:', error.debug_info)
    }
    
    await showError(errorMessage)
  }
}

// 選擇用戶功能（簡化版）
const selectUserWithRealtime = async (user) => {
  try {
    if (!user || typeof user !== 'object') {
      console.error('selectUserWithRealtime: 無效的用戶對象', user)
      return
    }
    
    // 簡單的重複選擇檢查
    if (selectedUser.value?.lineUserId === user.lineUserId) {
      console.log('用戶已選中，跳過重複選擇')
      return
    }
    
    console.log('選擇用戶:', user.name, 'LineUserID:', user.lineUserId)
    
    // 設定選中的用戶
    selectedUser.value = user
    activeUserId.value = user.id
    
    // 清空當前顯示的訊息，避免混淆
    if (user.isBot && user.lineUserId) {
      // 如果已有緩存的訊息，直接使用
      if (apiMessages.value[user.lineUserId]?.length > 0) {
        console.log('使用緩存的訊息:', apiMessages.value[user.lineUserId].length, '筆')
      } else {
        // 載入訊息
        console.log('載入用戶訊息:', user.lineUserId)
        await loadConversationMessages(user.lineUserId)
        
        // 檢查訊息是否載入成功
        nextTick(() => {
          const loadedMessages = apiMessages.value[user.lineUserId]
          console.log('訊息載入完成:', loadedMessages?.length || 0, '筆')
          
          if (!loadedMessages || loadedMessages.length === 0) {
            console.warn('未載入到任何訊息，可能是權限問題或該用戶沒有對話記錄')
          }
        })
      }
    }
    
    console.log('用戶選擇完成')
    
  } catch (error) {
    console.error('選擇用戶時發生錯誤:', error)
    // 發生錯誤時不要影響其他功能
  }
}





// 初始化數據載入
// Nuxt 路由導航清理
const router = useRouter()
const route = useRoute()

// 監聽路由變化以清理資源
watch(() => route.path, (newPath, oldPath) => {
  if (oldPath && oldPath.includes('/chat') && !newPath.includes('/chat')) {
    console.log(`離開聊天室頁面：${oldPath} -> ${newPath}，執行資源清理`)
    cleanupAllResources()
  }
})

// 路由導航守衛（離開時清理）
onBeforeRouteLeave((to, from) => {
  console.log(`路由導航離開：${from.path} -> ${to.path}`)
  if (from.path.includes('/chat')) {
    console.log('離開聊天室，清理所有資源')
    cleanupAllResources()
  }
  return true
})

// 初始化數據載入
onMounted(async () => {
  console.log('聊天室初始化開始 - 使用簡化輪詢')
  
  // 設定頁面為活躍狀態
  pageState.value.isActive = true
  pageState.value.isUnloading = false
  
  // 載入對話列表
  await loadConversations()
  
  // 設置頁面可見性監聽
  setupVisibilityListener()
  
  // 啟動簡化輪詢（自動開始）
  await startSimplePolling()
  
  // 性能監控 - 定期記錄內存使用情況
  setInterval(() => {
    performanceMonitor.recordMemoryUsage()
  }, 10000) // 每10秒記錄一次
  
  console.log('聊天室初始化完成 - 簡化輪詢已啟動')
})

// 安全函數已移至頂部定義

// 全域資源清理函數（增強版）
const cleanupAllResources = () => {
  console.log('清理所有聊天室資源...')
  
  // 設定頁面為離開狀態
  pageState.value.isUnloading = true
  pageState.value.isActive = false
  
  // 停止定時輪詢
  stopPolling()
  stopLegacyPolling()
  
  // 清理所有待處理的計時器
  pageState.value.pendingTimeouts.forEach(timeoutId => {
    clearTimeout(timeoutId)
  })
  pageState.value.pendingTimeouts.clear()
  
  // 清理API追蹤
  apiCallTracker.value.activeApiCalls.clear()
  apiCallTracker.value.lastApiCall = null
  
  // 清理所有鎖定狀態
  loadingLocks.value.conversations = false
  loadingLocks.value.messages = false
  loadingLocks.value.userSelection = false
  loadingLocks.value.apiCallInProgress = false
  globalLock.value = false
  
  // 清理其他計時器
  if (autoRefreshTimer.value) {
    clearInterval(autoRefreshTimer.value)
    autoRefreshTimer.value = null
  }
  
  // 清理搜尋計時器
  if (searchTimeout) {
    clearTimeout(searchTimeout)
    searchTimeout = null
  }
  
  autoRefreshEnabled.value = false
  isRefreshing.value = false
  
  console.log('所有資源已清理完成，頁面設定為已離開')
}


// 頁面卸載清理增強版
onUnmounted(() => {
  console.log('聊天室頁面卸載，執行資源清理')
  
  // 停止 Long Polling 並清理資源
  stopPolling()
  
  // 原有的清理邏輯
  cleanupAllResources()
})

// 頁面導航前清理（Nuxt 3 方式）
onBeforeUnmount(() => {
  console.log('頁面即將卸載，執行預清理')
  
  // 停止 Long Polling
  stopPolling()
  
  // 原有的清理邏輯
  cleanupAllResources()
})

// 監聽頁面離開事件（瀏覽器關閉或導航）
if (process.client) {
  const handleBeforeUnload = (event) => {
    console.log('瀏覽器即將關閉或離開頁面，執行緊急清理')
    cleanupAllResources()
    
    // 如果有正在進行的操作，警告用戶
    if (globalLock.value || Object.values(loadingLocks.value).some(lock => lock)) {
      const message = '有操作正在進行中，確定要離開嗎？'
      event.returnValue = message
      return message
    }
  }
  
  const handlePageHide = () => {
    console.log('頁面被隱藏或導航離開，執行資源清理')
    cleanupAllResources()
  }
  
  window.addEventListener('beforeunload', handleBeforeUnload)
  window.addEventListener('pagehide', handlePageHide)
  
  // 在組件卸載時移除監聽器
  onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload)
    window.removeEventListener('pagehide', handlePageHide)
  })
}

// 頁面標題
useHead({
  title: '聊天室 - 融資貸款公司 CRM 系統'
})
</script>

<style scoped>
/* 左側用戶列表滾動條樣式 */
.custom-scrollbar-left {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 #f8fafc;
}

.custom-scrollbar-left::-webkit-scrollbar {
  width: 8px;
}

.custom-scrollbar-left::-webkit-scrollbar-track {
  background: #f8fafc;
  border-radius: 4px;
  margin: 4px 0;
}

.custom-scrollbar-left::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
  border: 1px solid #f8fafc;
  min-height: 20px;
}

.custom-scrollbar-left::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.custom-scrollbar-left::-webkit-scrollbar-thumb:active {
  background: #64748b;
}

.custom-scrollbar-left::-webkit-scrollbar-corner {
  background: #f8fafc;
}

/* 為 Firefox 提供更好的滾動條樣式 */
@supports (scrollbar-width: thin) {
  .custom-scrollbar-left {
    scrollbar-width: auto;
    scrollbar-color: #cbd5e1 #f8fafc;
  }
}
</style>