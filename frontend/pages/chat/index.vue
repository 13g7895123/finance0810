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
            <div class="flex items-center space-x-1">
              <div 
                class="w-2 h-2 rounded-full"
                :class="{
                  'bg-green-400 animate-pulse': connectionStatus === 'connected',
                  'bg-yellow-400 animate-pulse': connectionStatus === 'connecting',
                  'bg-red-400': connectionStatus === 'error',
                  'bg-gray-400': connectionStatus === 'disconnected'
                }"
              ></div>
              <span class="text-xs text-gray-500">
                {{ getConnectionStatusText() }}
              </span>
              <span class="text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded">
                Firebase
              </span>
              <!-- 重新連接按鈕 -->
              <button
                v-if="connectionStatus === 'error' || connectionStatus === 'disconnected'"
                @click="reconnectChat"
                class="text-xs bg-red-100 text-red-600 hover:bg-red-200 px-2 py-1 rounded transition-colors"
                title="重新連接"
              >
                重連
              </button>
            </div>
          </div>
        </div>
        
        <!-- 搜尋框 -->
        <div class="relative">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="搜尋用戶..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
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
              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
          >
            {{ filter.label }}
          </button>
        </div>
      </div>

      <!-- 用戶列表 -->
      <div class="flex-1 overflow-y-auto custom-scrollbar-left">
        <ChatUserList
          :users="filteredUsers"
          :activeUserId="activeUserId"
          @userSelect="selectUser"
        />
      </div>
    </div>

    <!-- 右側聊天區域 -->
    <div class="flex-1 flex flex-col">
      <ChatMessageArea
        v-if="selectedUser"
        :user="selectedUser"
        :messages="currentMessages"
        @sendMessage="handleSendMessage"
      />
      
      <!-- 未選擇用戶時的預設畫面 -->
      <div v-else class="flex-1 flex items-center justify-center bg-gray-50">
        <div class="text-center">
          <ChatBubbleLeftRightIcon class="w-16 h-16 text-gray-400 mx-auto mb-4" />
          <h3 class="text-xl font-medium text-gray-900 mb-2">選擇聊天對象</h3>
          <p class="text-gray-500">從左側列表選擇要聊天的用戶開始對話</p>
          <!-- 顯示連接錯誤 -->
          <div v-if="error" class="mt-4 p-4 bg-red-100 border border-red-300 rounded-lg">
            <p class="text-red-700 text-sm">{{ error }}</p>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { 
  MagnifyingGlassIcon,
  ChatBubbleLeftRightIcon 
} from '@heroicons/vue/24/outline'

definePageMeta({
  middleware: 'auth'
})

const { error: showError } = useNotification()
const { canViewAllChats, getLocalUser } = useAuth()

// 使用新的即時聊天系統
const realtimeChat = useRealtimeChat()

// 解構即時聊天狀態
const { 
  conversations, 
  messages, 
  connectionStatus, 
  error,
  getConnectionStatusText 
} = realtimeChat

// 頁面狀態
const searchQuery = ref('')
const activeFilter = ref('all')
const activeUserId = ref(null)
const selectedUser = ref(null)

// 篩選選項
const filters = ref([
  { key: 'all', label: '所有訊息' },
  { key: 'unread', label: '未讀' },
  { key: 'favorites', label: '重要' },
  { key: 'archived', label: '封存' }
])

// 過濾用戶列表
const filteredUsers = computed(() => {
  let users = [...conversations.value]
  
  // 權限過濾 - 業務人員只能看到自己相關的對話，admin/executive 可以看全部
  if (!canViewAllChats()) {
    const currentUser = getLocalUser()
    users = users.filter(user => {
      // 只顯示分配給當前用戶的對話
      return user.customerInfo?.assignedTo === currentUser?.id
    })
  }

  // 搜尋過濾
  if (searchQuery.value && searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    users = users.filter(user => {
      return (user.name && user.name.toLowerCase().includes(query)) ||
             (user.customerInfo?.phone && user.customerInfo.phone.includes(searchQuery.value)) ||
             (user.customerInfo?.region && user.customerInfo.region.toLowerCase().includes(query))
    })
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

  return users
})

// 當前選中用戶的訊息
const currentMessages = computed(() => {
  if (!selectedUser.value?.lineUserId) return []
  return messages.value[selectedUser.value.lineUserId] || []
})

/**
 * 選擇用戶
 */
const selectUser = async (user) => {
  if (!user?.lineUserId) return
  
  console.log('選擇用戶:', user.name, 'LineUserId:', user.lineUserId)
  
  selectedUser.value = user
  activeUserId.value = user.id
  
  // 檢查連接狀態，如果異常則重新初始化
  if (connectionStatus.value === 'disconnected' || connectionStatus.value === 'error') {
    console.log('檢測到連接異常，嘗試重新初始化')
    try {
      await realtimeChat.initialize()
    } catch (error) {
      console.error('重新初始化失敗:', error)
      await showError('聊天室連接異常，請重新整理頁面')
      return
    }
  }
  
  // 載入該用戶的訊息（Firebase會自動監聽，API會從後端載入）
  await realtimeChat.loadMessages(user.lineUserId)
}

/**
 * 發送訊息
 */
const handleSendMessage = async (content) => {
  if (!selectedUser.value?.lineUserId || !content.trim()) return
  
  try {
    await realtimeChat.sendMessage(selectedUser.value.lineUserId, content.trim())
    console.log('訊息發送成功')
  } catch (error) {
    console.error('發送訊息失敗:', error)
    let errorMessage = '發送訊息失敗，請重試'
    if (error?.error) {
      errorMessage = error.error
    } else if (error?.message) {
      errorMessage = error.message
    }
    await showError(errorMessage)
  }
}

/**
 * 重新連接聊天室
 */
const reconnectChat = async () => {
  console.log('手動重新連接聊天室')
  
  try {
    await realtimeChat.initialize()
    console.log('手動重新連接成功')
  } catch (error) {
    console.error('手動重新連接失敗:', error)
    await showError('重新連接失敗，請稍後再試或重新整理頁面')
  }
}


// 頁面初始化
onMounted(async () => {
  console.log('即時聊天室初始化開始...')
  
  try {
    await realtimeChat.initialize()
    console.log('即時聊天室初始化完成')
    
    // 添加頁面可見性監聽器
    document.addEventListener('visibilitychange', handleVisibilityChange)
    console.log('頁面可見性監聽器已設置')
  } catch (error) {
    console.error('即時聊天室初始化失敗:', error)
    await showError('聊天室初始化失敗，請重新整理頁面')
  }
})

// 頁面清理
onBeforeUnmount(() => {
  console.log('即時聊天室頁面卸載，清理資源...')
  
  // 移除頁面可見性監聽器
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  console.log('頁面可見性監聽器已移除')
  
  realtimeChat.cleanup()
})

onUnmounted(() => {
  // 確保清理工作完成
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  realtimeChat.cleanup()
})

// 監聽路由變化進行清理
const router = useRouter()
const route = useRoute()

watch(() => route.path, (newPath, oldPath) => {
  if (oldPath && oldPath.includes('/chat') && !newPath.includes('/chat')) {
    console.log('離開即時聊天室頁面，清理資源')
    realtimeChat.cleanup()
  }
})

// 監聽頁面可見性變化，確保回到頁面時重新連接
const handleVisibilityChange = async () => {
  if (!document.hidden && route.path.includes('/chat')) {
    console.log('頁面重新可見，檢查聊天室連接狀態')
    
    // 如果連接斷開，重新初始化
    if (connectionStatus.value === 'disconnected' || connectionStatus.value === 'error') {
      console.log('重新初始化聊天室連接')
      try {
        await realtimeChat.initialize()
        console.log('聊天室重新連接成功')
      } catch (error) {
        console.error('聊天室重新連接失敗:', error)
      }
    }
  }
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
</style>