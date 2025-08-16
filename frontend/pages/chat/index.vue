<template>
  <div class="flex bg-gray-50" style="height: calc(100vh - 120px); max-height: calc(100vh - 120px);">
    <!-- 左側用戶列表 -->
    <div class="w-80 bg-white border-r border-gray-300 flex flex-col">
      <!-- 標題和篩選 -->
      <div class="p-4 border-b border-gray-200">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center space-x-3">
            <h2 class="text-lg font-semibold text-gray-900">聊天室</h2>
            <!-- 連線狀態指示器 -->
            <ClientOnly>
              <div class="flex items-center space-x-1">
                <div class="w-2 h-2 rounded-full bg-green-400"></div>
                <span class="text-xs text-gray-500">已連線</span>
              </div>
            </ClientOnly>
            
            <!-- 手動刷新按鈕 -->
            <ClientOnly>
              <div class="flex space-x-1" style="display: none;">
                <button 
                  @click="manualRefresh"
                  :disabled="isRefreshing"
                  class="text-xs px-2 py-1 bg-blue-100 text-blue-600 rounded hover:bg-blue-200 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <span v-if="isRefreshing">刷新中...</span>
                  <span v-else>手動刷新</span>
                </button>
                
                <!-- 輪詢控制按鈕 -->
                <button 
                  @click="togglePolling"
                  :class="[
                    'text-xs px-2 py-1 rounded transition-colors',
                    pollingConfig.enabled 
                      ? 'bg-green-100 text-green-600 hover:bg-green-200' 
                      : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                  ]"
                >
                  <span v-if="pollingConfig.enabled">停止輪詢</span>
                  <span v-else>啟動輪詢</span>
                </button>
                
                <!-- 輪詢間隔選擇 -->
                <select 
                  @change="changePollingInterval(parseInt($event.target.value))"
                  :value="pollingConfig.interval"
                  class="text-xs px-1 py-1 border border-gray-300 rounded bg-white"
                >
                  <option value="500">0.5秒</option>
                  <option value="1000">1秒</option>
                  <option value="2000">2秒</option>
                  <option value="5000">5秒</option>
                </select>
                
                <span class="text-xs text-gray-500">
                  {{ formatTime(lastRefreshTime) }}
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

definePageMeta({
  middleware: 'auth'
})

const { error: showError } = useNotification()

const authStore = useAuthStore()
const { getConversations, getConversation, replyMessage, getChatStats, searchConversations } = useChat()

// 簡化的聊天室更新策略 - 移除複雜的實時技術
const isRefreshing = ref(false)
const lastRefreshTime = ref(new Date())
const autoRefreshEnabled = ref(true)

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
  const now = new Date()
  const time = new Date(timestamp)
  const diffInMinutes = Math.floor((now - time) / (1000 * 60))
  
  if (diffInMinutes < 1) return '剛剛'
  if (diffInMinutes < 60) return `${diffInMinutes}分鐘前`
  if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}小時前`
  return `${Math.floor(diffInMinutes / 1440)}天前`
}

// 簡化的連線狀態管理
const updateChatConnectionStatus = () => {
  chatConnectionStatus.value = 'ready' // 始終顯示為準備就緒
}

// 手動刷新功能（增強防競爭版）
const manualRefresh = async () => {
  if (isRefreshing.value || globalLock.value || loadingLocks.value.apiCallInProgress) {
    console.log('刷新已在進行中或有其他API操作，跳過')
    return
  }
  
  isRefreshing.value = true
  loadingLocks.value.apiCallInProgress = true
  
  try {
    console.log('手動刷新聊天室數據...')
    
    // API 序列化：確保只有一個API調用可以進行
    if (selectedUser.value && selectedUser.value.lineUserId) {
      // 有選中用戶時，僅更新該用戶的訊息，不更新主列表以避免競爭
      console.log('有選中用戶，僅更新該用戶訊息:', selectedUser.value.lineUserId)
      await loadConversationMessages(selectedUser.value.lineUserId)
    } else {
      // 沒有選中用戶時，更新主對話列表
      console.log('沒有選中用戶，更新主對話列表')
      await loadConversations()
    }
    
    lastRefreshTime.value = new Date()
    console.log('聊天室數據刷新完成')
    
    // 成功後重置重試計數
    pollingConfig.value.retryCount = 0
    
  } catch (error) {
    console.error('刷新聊天室數據失敗:', error)
    pollingConfig.value.retryCount++
  } finally {
    isRefreshing.value = false
    loadingLocks.value.apiCallInProgress = false
    console.log('API調用鎖定已釋放')
  }
}

// 定時輪詢功能（增強防競爭版）
const startPolling = (intervalMs = 1000) => {
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
      stopPolling()
      return
    }
    
    try {
      await manualRefresh()
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

// 停止輪詢
const stopPolling = () => {
  console.log('停止定時輪詢')
  pollingConfig.value.enabled = false
  
  if (pollingConfig.value.timer) {
    clearTimeout(pollingConfig.value.timer)
    pollingConfig.value.timer = null
  }
  
  // 重設重試計數
  pollingConfig.value.retryCount = 0
  
  console.log('輪詢已停止，重試計數已重設')
}

// 調整輪詢間隔
const changePollingInterval = (intervalMs) => {
  if (pollingConfig.value.enabled) {
    stopPolling()
    safeStartPolling(intervalMs)
  } else {
    pollingConfig.value.interval = intervalMs
  }
  console.log(`輪詢間隔已設定為: ${intervalMs}ms`)
}

// 切換輪詢狀態
const togglePolling = () => {
  if (pollingConfig.value.enabled) {
    stopPolling()
  } else {
    safeStartPolling(pollingConfig.value.interval)
  }
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
    const response = await getConversations()
    
    if (response?.data) {
      // 轉換 API 數據格式到前端格式
      const apiUsers = response.data.map(conv => ({
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
      
      // 去重處理：根據 lineUserId 去重，保留最新的記錄
      const uniqueUsers = apiUsers.reduce((acc, current) => {
        const existing = acc.find(item => item.lineUserId === current.lineUserId)
        if (!existing) {
          acc.push(current)
        } else {
          // 如果存在，比較時間戳，保留較新的
          if (current.timestamp > existing.timestamp) {
            const index = acc.indexOf(existing)
            acc[index] = current
          }
        }
        return acc
      }, [])
      
      // 排序處理
      const sortedUsers = sortByTime(uniqueUsers)
      
      // 數據比較：只有在數據實際改變時才更新
      const currentData = JSON.stringify(apiConversations.value.map(u => ({
        lineUserId: u.lineUserId,
        lastMessage: u.lastMessage,
        timestamp: u.timestamp?.getTime(),
        unreadCount: u.unreadCount
      })))
      
      const newData = JSON.stringify(sortedUsers.map(u => ({
        lineUserId: u.lineUserId,
        lastMessage: u.lastMessage,
        timestamp: u.timestamp?.getTime(),
        unreadCount: u.unreadCount
      })))
      
      // 只有在數據真正改變時才更新，避免不必要的重新渲染
      if (currentData !== newData) {
        apiConversations.value = sortedUsers
        console.log(`載入對話列表: ${uniqueUsers.length} 筆記錄 (已更新)`)
      } else {
        console.log(`載入對話列表: ${uniqueUsers.length} 筆記錄 (無變化)`)
      }
    }
  } catch (error) {
    console.error('Failed to load conversations:', error)
  } finally {
    conversationsLoading.value = false
    loadingLocks.value.conversations = false
    apiCallTracker.value.activeApiCalls.delete(callId)
    console.log('對話列表載入完成，清理API追蹤')
  }
}

// 載入特定對話的訊息
const loadConversationMessages = async (userId) => {
  // 防止重複載入和API競爭
  if (loadingLocks.value.messages || loadingLocks.value.conversations || loadingLocks.value.apiCallInProgress) {
    console.log('訊息正在載入中或有其他API操作進行中，跳過重複請求')
    return
  }
  
  const callId = `messages_${userId}_${++apiCallTracker.value.callCounter}`
  apiCallTracker.value.activeApiCalls.add(callId)
  apiCallTracker.value.lastApiCall = callId

  try {
    loadingLocks.value.messages = true
    loading.value = true
    console.log('開始載入用戶訊息:', userId)
    
    const response = await getConversation(userId)
    console.log('API response for conversation:', response)
    
    if (response?.data && Array.isArray(response.data)) {
      // 轉換 API 數據格式到前端格式
      const transformedMessages = response.data.map(msg => ({
        id: msg.id,
        senderId: msg.is_from_customer ? parseInt(msg.line_user_id) : 'bot',
        content: msg.message_content,
        timestamp: new Date(msg.message_timestamp),
        type: msg.message_type || 'text',
        isBot: true,
        isCustomer: msg.is_from_customer,
        isAutoReply: !msg.is_from_customer,
        metadata: msg.metadata || {}
      }))
      
      // 按時間排序（舊的在前面，新的在後面）
      transformedMessages.sort((a, b) => {
        const timeA = new Date(a.timestamp).getTime()
        const timeB = new Date(b.timestamp).getTime()
        return timeA - timeB
      })
      
      console.log('Transformed messages:', transformedMessages)
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
  } finally {
    loading.value = false
    loadingLocks.value.messages = false
    apiCallTracker.value.activeApiCalls.delete(callId)
    console.log('用戶訊息載入完成:', userId, '清理API追蹤')
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
  // 數據已在 loadConversations 中排序和去重，直接返回避免重複處理
  if (!Array.isArray(apiConversations.value)) {
    return []
  }
  
  // 創建副本以避免直接修改原始數據，但不重新排序
  return [...apiConversations.value]
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
      
      // 去重處理：根據 lineUserId 去重，保留最新的記錄
      const uniqueSearchUsers = searchUsers.reduce((acc, current) => {
        const existing = acc.find(item => item.lineUserId === current.lineUserId)
        if (!existing) {
          acc.push(current)
        } else {
          // 如果存在，比較時間戳，保留較新的
          if (current.timestamp > existing.timestamp) {
            const index = acc.indexOf(existing)
            acc[index] = current
          }
        }
        return acc
      }, [])
      
      // 排序處理
      const sortedSearchUsers = sortByTime(uniqueSearchUsers)
      
      // 數據比較：只有在搜索結果實際改變時才更新
      const currentSearchData = JSON.stringify(searchResults.value.map(u => ({
        lineUserId: u.lineUserId,
        lastMessage: u.lastMessage,
        timestamp: u.timestamp?.getTime()
      })))
      
      const newSearchData = JSON.stringify(sortedSearchUsers.map(u => ({
        lineUserId: u.lineUserId,
        lastMessage: u.lastMessage,
        timestamp: u.timestamp?.getTime()
      })))
      
      // 只有在搜索結果真正改變時才更新
      if (currentSearchData !== newSearchData) {
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
      manualRefresh()
      
      // 如果輪詢被停止，發送訊息後短時啟動輪詢
      if (!pollingConfig.value.enabled) {
        console.log('發送訊息後短時啟動輪詢')
        safeStartPolling(pollingConfig.value.interval)
        
        // 30秒後自動停止（節省資源）
        safeSetTimeout(() => {
          if (pollingConfig.value.enabled) {
            console.log('自動停止短時輪詢')
            stopPolling()
          }
        }, 30000)
      }
    }, 1000)
    
  } catch (error) {
    console.error('Failed to send message:', error)
    await showError('發送訊息失敗，請重試')
  }
}

// 選擇用戶功能（增強版防競態）
const selectUserWithRealtime = async (user) => {
  try {
    if (!user || typeof user !== 'object') {
      console.error('selectUserWithRealtime: 無效的用戶對象', user)
      return
    }
    
    // 檢查是否有其他操作正在進行
    if (loadingLocks.value.userSelection || globalLock.value) {
      console.log('用戶選擇操作正在進行中，忽略重複請求')
      return
    }
    
    console.log('=== 選擇用戶開始 ===')
    console.log('Selecting user:', user.name, 'ID:', user.id, 'LineUserID:', user.lineUserId)
    
    // 設定全域鎖和用戶選擇鎖
    loadingLocks.value.userSelection = true
    loadingLocks.value.apiCallInProgress = true
    globalLock.value = true
    
    // 暫停輪詢以避免競態條件
    const wasPollingEnabled = pollingConfig.value.enabled
    if (wasPollingEnabled) {
      console.log('暫停輪詢以避免競態條件')
      stopPolling()
    }
    
    // 等待所有正在進行的API調用完成
    let waitCount = 0
    while (apiCallTracker.value.activeApiCalls.size > 0 && waitCount < 50) {
      console.log(`等待API調用完成... (${apiCallTracker.value.activeApiCalls.size}個調用待完成)`)
      await new Promise(resolve => setTimeout(resolve, 100))
      waitCount++
    }
    
    if (apiCallTracker.value.activeApiCalls.size > 0) {
      console.warn('強制清理未完成的API調用')
      apiCallTracker.value.activeApiCalls.clear()
    }
    
    // 執行原有的用戶選擇邏輯
    selectedUser.value = user
    activeUserId.value = user.id
    
    console.log('User selected, activeUserId set to:', user.id)
    console.log('Is bot user?', user.isBot, 'Line User ID:', user.lineUserId)
    
    // 載入對話訊息 (如果是 LINE BOT 用戶) - 增強版防競爭
    if (user.isBot && user.lineUserId) {
      console.log('Loading conversation messages for LINE bot user:', user.lineUserId)
      console.log('停止主列表更新以避免與訊息載入競爭')
      
      // 確保在載入訊息時不會同時調用主列表API
      loadingLocks.value.apiCallInProgress = true
      
      try {
        await loadConversationMessages(user.lineUserId)
        console.log('✓ 對話訊息載入完成')
      } catch (error) {
        console.error('✗ 載入對話訊息失敗:', error)
      } finally {
        // 延遲釋放API鎖定，確保操作完成
        safeSetTimeout(() => {
          loadingLocks.value.apiCallInProgress = false
          console.log('訊息載入API鎖定已釋放')
        }, 500)
      }
    } else {
      console.log('User is not a LINE bot user')
    }
    
    // 標記為已讀（僅記錄，不修改響應式數據）
    if (user.unreadCount > 0) {
      console.log('用戶有未讀訊息，將標記為已讀')
      // 可以選擇性調用 markAsRead API 但不立即更新界面
      // await markAsRead(user.lineUserId)
    }
    
    // 恢復輪詢（如果之前啟用）
    if (wasPollingEnabled) {
      console.log('恢復輪詢')
      safeSetTimeout(() => {
        safeStartPolling(pollingConfig.value.interval)
      }, 1500) // 延遲1.5秒恢復，確保操作完成
    }
    
    console.log('=== 選擇用戶完成 ===')
    
    // 調試：檢查當前訊息
    nextTick(() => {
      console.log('Current messages after user selection:', currentMessages.value?.length || 0, 'messages')
    })
  } catch (error) {
    console.error('selectUserWithRealtime 發生錯誤:', error)
  } finally {
    // 確保鎖定狀態被清理
    safeSetTimeout(() => {
      loadingLocks.value.userSelection = false
      loadingLocks.value.apiCallInProgress = false
      globalLock.value = false
      console.log('用戶選擇鎖定已清理')
    }, 1000)
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
  console.log('聊天室初始化開始')
  
  // 設定頁面為活躍狀態
  pageState.value.isActive = true
  pageState.value.isUnloading = false
  
  // 載入對話列表
  await loadConversations()
  
  // 設置頁面可見性監聽
  setupVisibilityListener()
  
  // 更新連線狀態
  updateChatConnectionStatus()
  
  // 默認啟動輪詢（可選擇）
  safeStartPolling(1000) // 1秒間隔，自動啟動輪詢
  
  console.log('聊天室初始化完成')
  console.log('可使用「啟動輪詢」按鈕啟動定時更新')
})

// 安全的 setTimeout 包裝器
const safeSetTimeout = (callback, delay) => {
  if (pageState.value.isUnloading) {
    console.log('頁面已離開，取消計時器')
    return null
  }
  
  const timeoutId = setTimeout(() => {
    pageState.value.pendingTimeouts.delete(timeoutId)
    if (!pageState.value.isUnloading) {
      callback()
    }
  }, delay)
  
  pageState.value.pendingTimeouts.add(timeoutId)
  return timeoutId
}

// 安全的輪詢啟動函數
const safeStartPolling = (intervalMs = 1000) => {
  if (pageState.value.isUnloading) {
    console.log('頁面已離開，不啟動輪詢')
    return
  }
  startPolling(intervalMs)
}

// 全域資源清理函數（增強版）
const cleanupAllResources = () => {
  console.log('清理所有聊天室資源...')
  
  // 設定頁面為離開狀態
  pageState.value.isUnloading = true
  pageState.value.isActive = false
  
  // 停止定時輪詢
  stopPolling()
  
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

// 頁面可見性變化時的額外清理
const handleVisibilityChange = () => {
  if (process.client && !pageState.value.isUnloading) {
    if (!document.hidden && pageState.value.isActive) {
      console.log('頁面變可見，檢查更新')
      
      // 手動刷新一次
      if (autoRefreshEnabled.value && !globalLock.value) {
        manualRefresh()
      }
      
      // 如果輪詢被停止且用戶在聊天室，則重新啟動
      if (!pollingConfig.value.enabled && selectedUser.value && !globalLock.value && pageState.value.isActive) {
        console.log('頁面可見且有選中用戶，重新啟動輪詢')
        safeStartPolling(pollingConfig.value.interval)
      }
    } else {
      console.log('頁面隱藏，停止輪詢節省資源')
      stopPolling()
    }
  }
}

// 頁面卸載清理增強版
onUnmounted(() => {
  console.log('聊天室頁面卸載，執行資源清理')
  cleanupAllResources()
})

// 頁面導航前清理（Nuxt 3 方式）
onBeforeUnmount(() => {
  console.log('頁面即將卸載，執行預清理')
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