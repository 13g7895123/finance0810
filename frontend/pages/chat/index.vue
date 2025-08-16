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
                  isWebSocketConnected ? 'bg-green-400' : 'bg-red-400'
                ]"
              ></div>
              <span class="text-xs text-gray-500">
                {{ isWebSocketConnected ? '實時' : '離線' }}
              </span>
            </div>
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

// API 數據狀態
const apiConversations = ref([])
const apiMessages = ref({})

// 模擬用戶數據 - 根據權限過濾，包含更多 LINE BOT 對話記錄
const allUsers = ref([
  {
    id: 1,
    name: '經銷商王總',
    role: 'dealer_executive',
    avatar: 'https://ui-avatars.com/api/?name=王總&background=6366f1&color=fff',
    lastMessage: '請盡快處理這個案子',
    timestamp: new Date('2024-08-08T14:30:00'),
    unreadCount: 2,
    online: true,
    permissions: ['all_access']
  },
  {
    id: 2,
    name: '行政主管張經理',
    role: 'admin_manager',
    avatar: 'https://ui-avatars.com/api/?name=張經理&background=22c55e&color=fff',
    lastMessage: '今天的報表已經完成了',
    timestamp: new Date('2024-08-08T13:45:00'),
    unreadCount: 0,
    online: true,
    permissions: ['dashboard', 'customer_management', 'reports', 'chat', 'settings']
  },
  {
    id: 3,
    name: '業務員李小姐',
    role: 'sales_staff',
    avatar: 'https://ui-avatars.com/api/?name=李小姐&background=f97316&color=fff',
    lastMessage: '客戶已經確認合約內容',
    timestamp: new Date('2024-08-08T12:20:00'),
    unreadCount: 1,
    online: false,
    permissions: ['personal_customers', 'chat']
  },
  {
    id: 4,
    name: '業務員陳先生',
    role: 'sales_staff',
    avatar: 'https://ui-avatars.com/api/?name=陳先生&background=8b5cf6&color=fff',
    lastMessage: '明天可以安排會議嗎？',
    timestamp: new Date('2024-08-08T11:15:00'),
    unreadCount: 0,
    online: true,
    permissions: ['personal_customers', 'chat']
  },
  // LINE BOT 客戶對話記錄
  {
    id: 100,
    name: '劉柏毅',
    role: 'line_customer',
    avatar: 'https://ui-avatars.com/api/?name=劉&background=00C300&color=fff',
    lastMessage: '請問汽車貸款的利率是多少？',
    timestamp: new Date('2024-08-08T15:30:00'),
    unreadCount: 1,
    online: true,
    isBot: true,
    lineUserId: 'U123456789',
    customerInfo: {
      phone: '0912345678',
      region: '台北',
      source: '熊好貸',
      status: '待處理'
    }
  },
  {
    id: 101,
    name: 'CSL',
    role: 'line_customer',
    avatar: 'https://ui-avatars.com/api/?name=CSL&background=FF5722&color=fff',
    lastMessage: 'https://stickershop.line-scdn...',
    timestamp: new Date('2024-08-08T17:58:00'),
    unreadCount: 0,
    online: false,
    isBot: true,
    lineUserId: 'U987654321',
    customerInfo: {
      phone: '0923456789',
      region: '新北',
      source: '網站A',
      status: '已完成'
    }
  },
  {
    id: 102,
    name: 'Daniel',
    role: 'line_customer',
    avatar: 'https://ui-avatars.com/api/?name=Daniel&background=4CAF50&color=fff',
    lastMessage: '感謝客服的文字',
    timestamp: new Date('2024-08-08T15:56:00'),
    unreadCount: 2,
    online: true,
    isBot: true,
    lineUserId: 'U555666777',
    customerInfo: {
      phone: '0934567890',
      region: '桃園',
      source: '熊好貸',
      status: '待處理'
    }
  },
  {
    id: 103,
    name: '暴色水母',
    role: 'line_customer',
    avatar: 'https://ui-avatars.com/api/?name=暴&background=9C27B0&color=fff',
    lastMessage: 'ooo',
    timestamp: new Date('2024-08-08T15:27:00'),
    unreadCount: 0,
    online: false,
    isBot: true,
    lineUserId: 'U111222333',
    customerInfo: {
      phone: '0945678901',
      region: '台中',
      source: '網站B',
      status: '進行中'
    }
  },
  {
    id: 104,
    name: 'Miranda · 米...',
    role: 'line_customer',
    avatar: 'https://ui-avatars.com/api/?name=M&background=FF9800&color=fff',
    lastMessage: '',
    timestamp: new Date('2024-08-08T05:59:00'),
    unreadCount: 0,
    online: false,
    isBot: true,
    lineUserId: 'U444555666',
    customerInfo: {
      phone: '0956789012',
      region: '高雄',
      source: '網站A',
      status: '已完成'
    }
  },
  {
    id: 105,
    name: '晞晞',
    role: 'line_customer',
    avatar: 'https://ui-avatars.com/api/?name=晞&background=E91E63&color=fff',
    lastMessage: 'https://storage.googleapis.co...',
    timestamp: new Date('2024-07-08T05:59:00'),
    unreadCount: 0,
    online: false,
    isBot: true,
    lineUserId: 'U777888999',
    customerInfo: {
      phone: '0967890123',
      region: '台南',
      source: '熊好貸',
      status: '已完成'
    }
  }
])

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

// 合併 API 數據和模擬數據
const combinedUsers = computed(() => {
  let users = []
  
  // 如果有 API 數據，優先使用 API 數據
  if (apiConversations.value.length > 0) {
    users = [...allUsers.value, ...apiConversations.value]
  } else {
    users = [...allUsers.value]
  }
  
  // 確保合併後的數據按時間排序
  return sortByTime(users)
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

// 模擬訊息數據 - 包含豐富的 LINE BOT 對話記錄
const messages = ref({
  1: [
    {
      id: 1,
      senderId: 1,
      content: '請盡快處理這個案子',
      timestamp: new Date('2024-08-08T14:30:00'),
      type: 'text'
    },
    {
      id: 2,
      senderId: authStore.user?.id,
      content: '好的，我馬上處理',
      timestamp: new Date('2024-08-08T14:32:00'),
      type: 'text'
    }
  ],
  2: [
    {
      id: 3,
      senderId: 2,
      content: '今天的報表已經完成了',
      timestamp: new Date('2024-08-08T13:45:00'),
      type: 'text'
    }
  ],
  // 劉柏毅的 LINE BOT 對話
  100: [
    {
      id: 100,
      senderId: 100,
      content: '你好',
      timestamp: new Date('2024-08-08T14:14:00'),
      type: 'text',
      isBot: true,
      isCustomer: true
    },
    {
      id: 101,
      senderId: 'bot',
      content: '您好！歡迎來到熊好貸，我是您的專屬服務助手。請問您需要什麼貸款服務呢？',
      timestamp: new Date('2024-08-08T14:14:30'),
      type: 'text',
      isBot: true,
      isAutoReply: true
    },
    {
      id: 102,
      senderId: 100,
      content: '請問汽車貸款的利率是多少？',
      timestamp: new Date('2024-08-08T15:30:00'),
      type: 'text',
      isBot: true,
      isCustomer: true
    },
    {
      id: 103,
      senderId: 'bot',
      content: '汽車貸款利率依據您的信用狀況和車輛條件而定，一般在2.88%-15.75%之間。我們提供免費評估服務，請提供您的聯絡方式，專員將為您詳細說明。',
      timestamp: new Date('2024-08-08T15:30:15'),
      type: 'text',
      isBot: true,
      isAutoReply: true
    }
  ],
  // CSL 的 LINE BOT 對話
  101: [
    {
      id: 201,
      senderId: 101,
      content: 'https://stickershop.line-scdn.net/stickershop/v1/sticker/52002734/android/sticker.png',
      timestamp: new Date('2024-08-08T17:58:00'),
      type: 'sticker',
      isBot: true,
      isCustomer: true
    },
    {
      id: 202,
      senderId: 'bot',
      content: '感謝您的訊息！如有任何貸款需求或問題，歡迎隨時聯繫我們。',
      timestamp: new Date('2024-08-08T17:58:30'),
      type: 'text',
      isBot: true,
      isAutoReply: true
    }
  ],
  // Daniel 的 LINE BOT 對話
  102: [
    {
      id: 301,
      senderId: 102,
      content: '感謝客服的文字',
      timestamp: new Date('2024-08-08T15:56:00'),
      type: 'text',
      isBot: true,
      isCustomer: true
    },
    {
      id: 302,
      senderId: 'bot',
      content: '不客氣！很高興能為您服務。如果您有任何其他問題，請隨時告訴我們。',
      timestamp: new Date('2024-08-08T15:56:15'),
      type: 'text',
      isBot: true,
      isAutoReply: true
    },
    {
      id: 303,
      senderId: 102,
      content: 'ccc',
      timestamp: new Date('2024-08-08T14:15:00'),
      type: 'text',
      isBot: true,
      isCustomer: true
    },
    {
      id: 304,
      senderId: 102,
      content: 'ccc',
      timestamp: new Date('2024-08-08T14:16:00'),
      type: 'text',
      isBot: true,
      isCustomer: true
    },
    {
      id: 305,
      senderId: 102,
      content: 'vvv',
      timestamp: new Date('2024-08-08T14:18:00'),
      type: 'text',
      isBot: true,
      isCustomer: true
    },
    {
      id: 306,
      senderId: 102,
      content: 'vvv',
      timestamp: new Date('2024-08-08T14:26:00'),
      type: 'text',
      isBot: true,
      isCustomer: true
    },
    // 添加更多測試訊息以觸發滾動條
    {
      id: 307,
      senderId: 102,
      content: '我想了解更多關於汽車貸款的詳細信息',
      timestamp: new Date('2024-08-08T14:30:00'),
      type: 'text',
      isBot: true,
      isCustomer: true
    },
    {
      id: 308,
      senderId: 'bot',
      content: '很高興為您介紹我們的汽車貸款產品！我們提供多種方案：\n\n1. 一般汽車貸款：利率2.88%起\n2. 中古車貸款：利率3.5%起\n3. 原車融資：最高可貸車價150%\n\n請問您想了解哪一種產品呢？',
      timestamp: new Date('2024-08-08T14:30:30'),
      type: 'text',
      isBot: true,
      isAutoReply: true
    },
    {
      id: 309,
      senderId: 102,
      content: '原車融資聽起來很不錯，可以告訴我更詳細的條件嗎？',
      timestamp: new Date('2024-08-08T14:32:00'),
      type: 'text',
      isBot: true,
      isCustomer: true
    },
    {
      id: 310,
      senderId: 'bot',
      content: '原車融資的詳細條件如下：\n\n✅ 貸款金額：最高車價150%\n✅ 利率：依信用狀況3.88%-12.88%\n✅ 期數：12-84期彈性選擇\n✅ 免保人：信用良好可免保人\n✅ 快速審核：24小時內回覆\n\n需要準備的文件：\n📋 身分證正反面\n📋 駕駛執照\n📋 行車執照\n📋 近3個月銀行存摺\n\n您的車輛年份和廠牌是？',
      timestamp: new Date('2024-08-08T14:33:00'),
      type: 'text',
      isBot: true,
      isAutoReply: true
    },
    {
      id: 311,
      senderId: 102,
      content: '我的車是2020年的Toyota Camry，這樣可以貸多少呢？',
      timestamp: new Date('2024-08-08T14:35:00'),
      type: 'text',
      isBot: true,
      isCustomer: true
    },
    {
      id: 312,
      senderId: 'bot',
      content: '2020年Toyota Camry是很好的車款！根據市場行情評估：\n\n🚗 預估車價：約65-75萬\n💰 最高可貸：約97-112萬\n📊 建議貸款：80-90萬較為安全\n⏰ 還款期數：建議60-72期\n\n實際金額需要進行車輛鑑價，我們的專員可以免費到府評估。\n\n您希望我們安排專員聯繫您嗎？',
      timestamp: new Date('2024-08-08T14:36:00'),
      type: 'text',
      isBot: true,
      isAutoReply: true
    },
    {
      id: 313,
      senderId: 102,
      content: '好的，請安排專員聯繫我，我的電話是0912345678',
      timestamp: new Date('2024-08-08T14:38:00'),
      type: 'text',
      isBot: true,
      isCustomer: true
    },
    {
      id: 314,
      senderId: 'bot',
      content: '感謝您的信任！已經記錄您的聯絡方式：0912345678\n\n我們的專員將在1個工作小時內與您聯繫，為您提供：\n✓ 免費車輛鑑價服務\n✓ 詳細貸款方案說明\n✓ 客製化還款計劃\n✓ 快速審核流程說明\n\n如果您有任何緊急問題，也歡迎隨時透過LINE與我們聯繫。謝謝！',
      timestamp: new Date('2024-08-08T14:39:00'),
      type: 'text',
      isBot: true,
      isAutoReply: true
    }
  ],
  // 暴色水母的 LINE BOT 對話
  103: [
    {
      id: 401,
      senderId: 103,
      content: 'ooo',
      timestamp: new Date('2024-08-08T15:27:00'),
      type: 'text',
      isBot: true,
      isCustomer: true
    },
    {
      id: 402,
      senderId: 'bot',
      content: '您好！請問有什麼我可以協助您的嗎？我們提供汽車、機車、手機貸款等服務。',
      timestamp: new Date('2024-08-08T15:27:30'),
      type: 'text',
      isBot: true,
      isAutoReply: true
    }
  ],
  // 晞晞的 LINE BOT 對話
  105: [
    {
      id: 501,
      senderId: 105,
      content: 'https://storage.googleapis.com/line-bot/26VFC1752485624',
      timestamp: new Date('2024-07-08T05:59:00'),
      type: 'link',
      isBot: true,
      isCustomer: true
    },
    {
      id: 502,
      senderId: 'bot',
      content: '感謝您提供的資訊。我已經收到您的訊息，專員會盡快與您聯繫。',
      timestamp: new Date('2024-07-08T06:00:00'),
      type: 'text',
      isBot: true,
      isAutoReply: true
    }
  ]
})

// 當前聊天訊息
const currentMessages = computed(() => {
  if (!selectedUser.value) return []
  
  // 對於 LINE BOT 用戶，使用 lineUserId 查找 API 數據
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
  
  // 對於一般用戶，使用原有邏輯
  const normalMsgs = messages.value[selectedUser.value.id] || []
  if (normalMsgs.length > 0) {
    // 按時間排序訊息（舊的在前面，新的在後面）
    return normalMsgs.sort((a, b) => {
      const timeA = new Date(a.timestamp).getTime()
      const timeB = new Date(b.timestamp).getTime()
      return timeA - timeB
    })
  }
  
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
      // 對於內部用戶，使用原有的模擬邏輯
      const newMessage = {
        id: Math.floor(Math.random() * 1000000) + Date.now(), // Use consistent ID generation
        senderId: authStore.user?.id,
        content: content.trim(),
        timestamp: new Date(),
        type: 'text'
      }
      
      if (!messages.value[selectedUser.value.id]) {
        messages.value[selectedUser.value.id] = []
      }
      
      messages.value[selectedUser.value.id].push(newMessage)
    }
    
    // 更新最後訊息
    const currentTime = new Date()
    const userIndex = allUsers.value.findIndex(u => u.id === selectedUser.value.id)
    if (userIndex !== -1) {
      allUsers.value[userIndex].lastMessage = content.trim()
      allUsers.value[userIndex].timestamp = currentTime
    }
    
    // 更新 API 對話列表中的對應項目
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
    
    // 加入實時聊天房間
    const roomId = `line_${user.lineUserId}`
    currentRoomId.value = roomId
    
    // 加入房間並設置訊息回調
    joinRoom(roomId, (data) => {
      handleRealtimeMessage(data, user)
    })
    
    // 設置對話列表更新回調
    onConversationUpdate(roomId, (update) => {
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
          content: data.message.message_content,
          timestamp: new Date(data.message.message_timestamp),
          type: data.message.message_type || 'text',
          isBot: true,
          isCustomer: data.message.is_from_customer,
          isAutoReply: !data.message.is_from_customer,
          metadata: data.message.metadata || {}
        }
        apiMessages.value[user.lineUserId].push(newMessage)
        console.log('新訊息已添加到對話:', newMessage)
      } else {
        console.log('找不到用戶的對話記錄:', user.lineUserId)
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
      // 更新用戶在線狀態
      const userIndex = allUsers.value.findIndex(u => u.id === data.userId)
      if (userIndex !== -1) {
        allUsers.value[userIndex].online = data.online
      }
      break
  }
}

// 處理對話列表更新
const handleConversationUpdate = (update, user) => {
  // 更新對話列表中的項目
  const userIndex = allUsers.value.findIndex(u => u.id === user.id)
  if (userIndex !== -1) {
    if (update.lastMessage) {
      allUsers.value[userIndex].lastMessage = update.lastMessage
    }
    if (update.timestamp) {
      allUsers.value[userIndex].timestamp = new Date(update.timestamp)
    }
    if (update.unreadCount !== undefined) {
      allUsers.value[userIndex].unreadCount = update.unreadCount
    }
  }
  
  // 更新API對話列表
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

// Real-time chat functionality is now integrated

// 初始化數據載入
onMounted(() => {
  loadConversations()
  
  // 初始化實時聊天
  initializeRealTimeChat()
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