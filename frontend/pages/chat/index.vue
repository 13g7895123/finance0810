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

// 手動刷新功能
const manualRefresh = async () => {
  if (isRefreshing.value) return
  
  isRefreshing.value = true
  try {
    console.log('手動刷新聊天室數據...')
    
    // 重新載入對話列表
    await loadConversations()
    
    // 如果有選中的用戶，重新載入其訊息
    if (selectedUser.value && selectedUser.value.lineUserId) {
      await loadConversationMessages(selectedUser.value.lineUserId)
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
  }
}

// 定時輪詢功能
const startPolling = (intervalMs = 1000) => {
  if (pollingConfig.value.enabled) {
    console.log('輪詢已在運行中')
    return
  }
  
  pollingConfig.value.enabled = true
  pollingConfig.value.interval = intervalMs
  pollingConfig.value.retryCount = 0
  
  console.log(`啟動定時輪詢，間隔: ${intervalMs}ms`)
  
  const poll = async () => {
    if (!pollingConfig.value.enabled) return
    
    // 檢查頁面可見性
    if (process.client && document.hidden) {
      console.log('頁面隱藏，跳過本次輪詢')
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
      if (!isRefreshing.value) {
        await manualRefresh()
      }
    } catch (error) {
      console.error('輪詢更新失敗:', error)
    }
    
    scheduleNextPoll()
  }
  
  const scheduleNextPoll = () => {
    if (pollingConfig.value.enabled) {
      pollingConfig.value.timer = setTimeout(poll, pollingConfig.value.interval)
    }
  }
  
  // 立即執行第一次輪詢
  poll()
}

// 停止輪詢
const stopPolling = () => {
  console.log('停止定時輪詢')
  pollingConfig.value.enabled = false
  
  if (pollingConfig.value.timer) {
    clearTimeout(pollingConfig.value.timer)
    pollingConfig.value.timer = null
  }
}

// 調整輪詢間隔
const changePollingInterval = (intervalMs) => {
  if (pollingConfig.value.enabled) {
    stopPolling()
    startPolling(intervalMs)
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
    startPolling(pollingConfig.value.interval)
  }
}

// API 數據狀態
const apiConversations = ref([])
const apiMessages = ref({})

// 載入對話列表
const loadConversations = async () => {
  try {
    conversationsLoading.value = true
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
      
      // 確保載入時就按時間排序
      apiConversations.value = sortByTime(apiUsers)
    }
  } catch (error) {
    console.error('Failed to load conversations:', error)
  } finally {
    conversationsLoading.value = false
  }
}

// 載入特定對話的訊息
const loadConversationMessages = async (userId) => {
  try {
    loading.value = true
    console.log('Loading conversation messages for userId:', userId)
    
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
  }
}

// 時間排序函數
const sortByTime = (users) => {
  return users.sort((a, b) => {
    const timeA = new Date(a.timestamp).getTime()
    const timeB = new Date(b.timestamp).getTime()
    
    // 按時間排序（最新的在前面）
    if (timeA !== timeB) {
      return timeB - timeA
    }
    
    // 時間相同時按ID排序確保穩定性
    return b.id - a.id
  })
}

// 只使用 API 數據
const combinedUsers = computed(() => {
  // 只使用 API 對話數據，移除所有模擬數據
  return sortByTime(apiConversations.value)
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
      
      // 按時間排序搜尋結果，確保一致性
      searchResults.value = sortByTime(searchUsers)
      console.log('Search results processed:', searchUsers.length) // Debug log
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
  
  searchTimeout = setTimeout(() => {
    performSearch(newQuery)
  }, 500)
})

// 根據權限過濾用戶列表
const filteredUsers = computed(() => {
  try {
    // 如果有搜尋結果，優先顯示搜尋結果
    if (searchQuery.value && searchQuery.value.trim() && searchResults.value && searchResults.value.length > 0) {
      return Array.isArray(searchResults.value) ? searchResults.value : []
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

    // 穩定的時間排序：確保一致性
    if (typeof sortByTime === 'function') {
      return sortByTime(users)
    } else {
      console.error('sortByTime 不是函數:', typeof sortByTime, sortByTime)
      return users
    }
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
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        console.log('頁面變可見，檢查更新')
        
        // 手動刷新一次
        if (autoRefreshEnabled.value) {
          manualRefresh()
        }
        
        // 如果輪詢被停止且用戶在聊天室，則重新啟動
        if (!pollingConfig.value.enabled && selectedUser.value) {
          console.log('頁面可見且有選中用戶，重新啟動輪詢')
          startPolling(pollingConfig.value.interval)
        }
      } else {
        console.log('頁面隱藏，考慮停止輪詢')
        // 頁面隱藏時可選擇停止輪詢節省資源
        // stopPolling() // 可選擇啟用
      }
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
        startPolling(pollingConfig.value.interval)
        
        // 30秒後自動停止（節省資源）
        setTimeout(() => {
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

// 選擇用戶功能（簡化版）
const selectUserWithRealtime = async (user) => {
  try {
    if (!user || typeof user !== 'object') {
      console.error('selectUserWithRealtime: 無效的用戶對象', user)
      return
    }
    
    console.log('Selecting user:', user)
    
    // 執行原有的用戶選擇邏輯
    selectedUser.value = user
    activeUserId.value = user.id
    
    console.log('User selected, activeUserId set to:', user.id)
    console.log('Is bot user?', user.isBot, 'Line User ID:', user.lineUserId)
    
    // 載入對話訊息 (如果是 LINE BOT 用戶)
    if (user.isBot && user.lineUserId) {
      console.log('Loading conversation messages for LINE bot user:', user.lineUserId)
      if (typeof loadConversationMessages === 'function') {
        await loadConversationMessages(user.lineUserId)
      } else {
        console.error('loadConversationMessages 不是函數:', typeof loadConversationMessages)
      }
    } else {
      console.log('User is not a LINE bot user')
    }
    
    // 標記為已讀，但不立即觸發更新避免排序跳動
    if (user.unreadCount > 0) {
      nextTick(() => {
        if (user && typeof user === 'object') {
          user.unreadCount = 0
        }
      })
    }
    
    // 選擇用戶後可選擇自動啟動輪詢
    if (!pollingConfig.value.enabled) {
      console.log('選擇用戶後自動啟動輪詢')
      // startPolling(pollingConfig.value.interval) // 可選擇啟用
    }
    
    // 調試：檢查當前訊息
    nextTick(() => {
      console.log('Current messages after user selection:', currentMessages.value)
    })
  } catch (error) {
    console.error('selectUserWithRealtime 發生錯誤:', error)
  }
}





// 初始化數據載入
onMounted(async () => {
  console.log('聊天室初始化開始')
  
  // 載入對話列表
  await loadConversations()
  
  // 設置頁面可見性監聽
  setupVisibilityListener()
  
  // 更新連線狀態
  updateChatConnectionStatus()
  
  // 默認啟動輪詢（可選擇）
  // startPolling(1000) // 1秒間隔，用戶可手動啟動
  
  console.log('聊天室初始化完成')
  console.log('可使用「啟動輪詢」按鈕啟動定時更新')
})

// 頁面卸載清理增強版
onUnmounted(() => {
  console.log('聊天室頁面卸載，清理資源')
  
  // 停止定時輪詢
  stopPolling()
  
  // 清理其他計時器
  if (autoRefreshTimer.value) {
    clearInterval(autoRefreshTimer.value)
    autoRefreshTimer.value = null
  }
  
  autoRefreshEnabled.value = false
  
  console.log('所有資源已清理完成')
})

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