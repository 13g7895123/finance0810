<template>
  <div class="flex bg-gray-50" style="height: calc(100vh - 120px); max-height: calc(100vh - 120px);">
    <!-- 左側用戶列表 -->
    <div class="w-80 bg-white border-r border-gray-300 flex flex-col">
      <!-- 標題和篩選 -->
      <div class="p-4 border-b border-gray-200">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center space-x-3">
            <h2 class="text-lg font-semibold text-gray-900">聊天室</h2>
            <!-- WebSocket 連接狀態指示器 -->
            <div class="flex items-center space-x-1">
              <div 
                :class="[
                  'w-2 h-2 rounded-full',
                  {
                    'bg-green-400': chatConnectionStatus === 'connected',
                    'bg-yellow-400': chatConnectionStatus === 'connecting',
                    'bg-red-400': chatConnectionStatus === 'failed' || chatConnectionStatus === 'disconnected'
                  }
                ]"
              ></div>
              <span class="text-xs text-gray-500">
                {{ 
                  chatConnectionStatus === 'connected' ? '實時' : 
                  chatConnectionStatus === 'connecting' ? '連線中' :
                  chatConnectionStatus === 'failed' ? '連線失敗' : '離線'
                }}
              </span>
              <!-- Loading指示器 -->
              <div 
                v-if="initializingChat"
                class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-500"
              ></div>
            </div>
            <!-- 重新連線按鈕 (連線失敗時顯示) -->
            <button 
              v-if="chatConnectionStatus === 'failed'"
              @click="initializeChatConnection"
              :disabled="initializingChat"
              class="text-xs px-2 py-1 bg-red-100 text-red-600 rounded hover:bg-red-200 disabled:opacity-50"
            >
              重新連線
            </button>
            
            <!-- Debug: WebSocket 測試按鈕 (僅開發環境顯示) -->
            <button 
              v-if="$config.public.dev"
              @click="testWebSocketConnection"
              class="text-xs px-2 py-1 bg-blue-100 text-blue-600 rounded hover:bg-blue-200"
            >
              測試連接
            </button>
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
        <!-- 聊天室初始化載入狀態 -->
        <div v-if="initializingChat && chatConnectionStatus === 'connecting'" class="p-4 text-center">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mx-auto mb-2"></div>
          <p class="text-sm text-gray-500">正在初始化聊天室...</p>
        </div>
        
        <!-- 搜尋中載入狀態 -->
        <div v-else-if="isSearching" class="p-4 text-center">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mx-auto mb-2"></div>
          <p class="text-sm text-gray-500">搜尋中...</p>
        </div>
        
        <!-- 連線失敗提示 -->
        <div v-else-if="chatConnectionStatus === 'failed'" class="p-4 text-center">
          <div class="text-red-500 mb-2">
            <svg class="w-6 h-6 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.232 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
          </div>
          <p class="text-sm text-gray-600 mb-2">聊天室連線失敗</p>
          <p class="text-xs text-gray-500 mb-3">請檢查網路連線或稍後再試</p>
          <button 
            @click="initializeChatConnection"
            :disabled="initializingChat"
            class="text-xs px-3 py-1 bg-red-100 text-red-600 rounded hover:bg-red-200 disabled:opacity-50"
          >
            重新連線
          </button>
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
const { 
  initializeRealTimeChat, 
  joinRoom, 
  leaveRoom, 
  sendMessage: sendRealtimeMessage, 
  onConversationUpdate,
  isConnected: isWebSocketConnected 
} = useRealTimeChat()

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
const initializingChat = ref(false)
const chatConnectionStatus = ref('disconnected') // 'disconnected', 'connecting', 'connected', 'failed'

// API 數據狀態
const apiConversations = ref([])
const apiMessages = ref({})

// 移除模擬用戶數據，只使用 API 數據

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
  // 如果有搜尋結果，優先顯示搜尋結果
  if (searchQuery.value.trim() && searchResults.value.length > 0) {
    return searchResults.value
  }
  
  let users = combinedUsers.value

  // 權限過濾 - 業務人員只能看到自己相關的對話和BOT
  if (authStore.isSales && !authStore.hasPermission('all_access')) {
    users = users.filter(user => 
      user.id === authStore.user?.id || 
      user.isBot || 
      user.role === 'dealer_executive' ||
      user.role === 'admin_manager'
    )
  }

  // 本地搜尋過濾（如果沒有遠端搜尋結果）
  if (searchQuery.value.trim() && searchResults.value.length === 0 && !isSearching.value) {
    const query = searchQuery.value.toLowerCase()
    users = users.filter(user =>
      user.name.toLowerCase().includes(query) ||
      (user.customerInfo?.phone && user.customerInfo.phone.includes(searchQuery.value)) ||
      (user.customerInfo?.region && user.customerInfo.region.toLowerCase().includes(query))
    )
  }

  // 狀態過濾
  switch (activeFilter.value) {
    case 'unread':
      users = users.filter(user => user.unreadCount > 0)
      break
    case 'favorites':
      users = users.filter(user => user.isFavorite)
      break
    case 'archived':
      users = users.filter(user => user.isArchived)
      break
  }

  // 穩定的時間排序：確保一致性
  return sortByTime(users)
})

// 移除所有模擬訊息數據，只使用 API 數據

// 當前聊天訊息 - 只使用 API 數據
const currentMessages = computed(() => {
  if (!selectedUser.value) return []
  
  // 只使用 API 數據，LINE BOT 用戶使用 lineUserId 查找
  if (selectedUser.value.isBot && selectedUser.value.lineUserId) {
    const apiMsgs = apiMessages.value[selectedUser.value.lineUserId]
    if (apiMsgs && apiMsgs.length > 0) {
      // 按時間排序訊息（舊的在前面，新的在後面）
      return apiMsgs.sort((a, b) => {
        const timeA = new Date(a.timestamp).getTime()
        const timeB = new Date(b.timestamp).getTime()
        return timeA - timeB
      })
    }
  }
  
  // 沒有 API 數據時返回空陣列
  return []
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
    
  } catch (error) {
    console.error('Failed to send message:', error)
    await showError('發送訊息失敗，請重試')
  }
}

// 實時聊天功能
const currentRoomId = ref(null)

// 增強版選擇用戶功能（包含實時聊天）
const selectUserWithRealtime = async (user) => {
  console.log('Selecting user:', user)
  
  // 離開之前的房間
  if (currentRoomId.value) {
    leaveRoom(currentRoomId.value)
  }
  
  // 執行原有的用戶選擇邏輯
  selectedUser.value = user
  activeUserId.value = user.id
  
  console.log('User selected, activeUserId set to:', user.id)
  console.log('Is bot user?', user.isBot, 'Line User ID:', user.lineUserId)
  
  // 載入對話訊息 (如果是 LINE BOT 用戶)
  if (user.isBot && user.lineUserId) {
    console.log('Loading conversation messages for LINE bot user:', user.lineUserId)
    await loadConversationMessages(user.lineUserId)
    
    // 加入實時聊天房間 (直接使用 lineUserId)
    currentRoomId.value = user.lineUserId
    
    // 加入房間並設置訊息回調
    joinRoom(user.lineUserId, (data) => {
      handleRealtimeMessage(data, user)
    })
    
    // 設置對話列表更新回調
    onConversationUpdate(user.lineUserId, (update) => {
      handleConversationUpdate(update, user)
    })
  } else {
    console.log('User is not a LINE bot user, using mock messages')
  }
  
  // 標記為已讀，但不立即觸發更新避免排序跳動
  if (user.unreadCount > 0) {
    nextTick(() => {
      user.unreadCount = 0
    })
  }
  
  // 調試：檢查當前訊息
  nextTick(() => {
    console.log('Current messages after user selection:', currentMessages.value)
  })
}

// 處理實時訊息
const handleRealtimeMessage = (data, user) => {
  console.log('收到實時訊息:', data, '用戶:', user)
  
  switch (data.type) {
    case 'new_message':
      console.log('處理新訊息:', data.message)
      // 添加新訊息到當前對話
      if (apiMessages.value[user.lineUserId]) {
        const newMessage = {
          id: data.message.id,
          senderId: data.message.is_from_customer ? parseInt(data.message.line_user_id) : 'bot',
          content: data.message.content,
          timestamp: new Date(data.message.timestamp),
          type: data.message.message_type || 'text',
          isBot: true,
          isCustomer: data.message.is_from_customer,
          isAutoReply: !data.message.is_from_customer,
          metadata: data.message.metadata || {}
        }
        
        // 檢查是否已存在相同 ID 的訊息，避免重複添加
        const existingMessageIndex = apiMessages.value[user.lineUserId].findIndex(msg => msg.id === newMessage.id)
        if (existingMessageIndex === -1) {
          apiMessages.value[user.lineUserId].push(newMessage)
          console.log('新訊息已添加到對話:', newMessage)
          
          // 更新對話列表中的最新訊息和時間戳
          const userIndex = apiConversations.value.findIndex(u => u.lineUserId === user.lineUserId)
          if (userIndex !== -1) {
            apiConversations.value[userIndex].lastMessage = newMessage.content
            apiConversations.value[userIndex].timestamp = newMessage.timestamp
            if (newMessage.isCustomer) {
              apiConversations.value[userIndex].unreadCount += 1
            }
          }
        } else {
          console.log('訊息已存在，跳過添加:', newMessage.id)
        }
      } else {
        console.log('找不到用戶的對話記錄，初始化:', user.lineUserId)
        // 初始化該用戶的對話記錄
        apiMessages.value[user.lineUserId] = [{
          id: data.message.id,
          senderId: data.message.is_from_customer ? parseInt(data.message.line_user_id) : 'bot',
          content: data.message.content,
          timestamp: new Date(data.message.timestamp),
          type: data.message.message_type || 'text',
          isBot: true,
          isCustomer: data.message.is_from_customer,
          isAutoReply: !data.message.is_from_customer,
          metadata: data.message.metadata || {}
        }]
      }
      break
      
    case 'message_status':
      // 更新訊息狀態
      if (apiMessages.value[user.lineUserId]) {
        const messageIndex = apiMessages.value[user.lineUserId].findIndex(
          msg => msg.id === data.messageId
        )
        if (messageIndex !== -1) {
          apiMessages.value[user.lineUserId][messageIndex].status = data.status
        }
      }
      break
      
    case 'user_status':
      // 更新用戶在線狀態 - 只更新 API 數據
      const apiUserIndex = apiConversations.value.findIndex(u => u.id === data.userId)
      if (apiUserIndex !== -1) {
        apiConversations.value[apiUserIndex].online = data.online
      }
      break
  }
}

// 處理對話列表更新
const handleConversationUpdate = (update, user) => {
  // 只更新 API 對話列表，移除模擬數據更新
  const apiUserIndex = apiConversations.value.findIndex(u => u.id === user.id)
  if (apiUserIndex !== -1) {
    if (update.lastMessage) {
      apiConversations.value[apiUserIndex].lastMessage = update.lastMessage
    }
    if (update.timestamp) {
      apiConversations.value[apiUserIndex].timestamp = new Date(update.timestamp)
    }
    if (update.unreadCount !== undefined) {
      apiConversations.value[apiUserIndex].unreadCount = update.unreadCount
    }
  }
}

// 測試 WebSocket 連接功能 (僅開發環境)
const testWebSocketConnection = () => {
  console.log('=== WebSocket 連接測試 ===')
  console.log('連接狀態:', isWebSocketConnected.value)
  console.log('活躍房間:', activeRooms.value)
  console.log('當前選中用戶:', selectedUser.value)
  console.log('當前房間ID:', currentRoomId.value)
  
  if (selectedUser.value?.lineUserId) {
    console.log('嘗試發送測試訊息到:', selectedUser.value.lineUserId)
    // 這裡可以添加發送測試訊息的邏輯
  } else {
    console.log('沒有選中的用戶')
  }
  
  // 顯示一個簡單的通知
  console.log('WebSocket 連接測試完成，請查看控制台輸出')
}

// Real-time chat functionality is now integrated

// 初始化實時聊天的輔助函數
const initializeChatConnection = async () => {
  initializingChat.value = true
  chatConnectionStatus.value = 'connecting'
  
  try {
    // 確保認證已完成
    await authStore.waitForInitialization()
    
    if (!authStore.isLoggedIn || !authStore.token) {
      chatConnectionStatus.value = 'failed'
      console.warn('用戶未登入或無有效token，聊天室連線失敗')
      return false
    }
    
    const success = await initializeRealTimeChat()
    if (success) {
      chatConnectionStatus.value = 'connected'
      console.log('Real-time chat initialized successfully')
      return true
    } else {
      chatConnectionStatus.value = 'failed'
      console.warn('Real-time chat initialization failed')
      return false
    }
  } catch (error) {
    chatConnectionStatus.value = 'failed'
    console.error('Error initializing real-time chat:', error)
    return false
  } finally {
    initializingChat.value = false
  }
}

// 監聽WebSocket連線狀態變化
watch(isWebSocketConnected, (newStatus) => {
  if (newStatus && chatConnectionStatus.value === 'connecting') {
    chatConnectionStatus.value = 'connected'
  } else if (!newStatus && chatConnectionStatus.value === 'connected') {
    chatConnectionStatus.value = 'disconnected'
  }
})

// 初始化數據載入
onMounted(async () => {
  // 載入對話列表
  loadConversations()
  
  // 初始化實時聊天連線
  await initializeChatConnection()
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