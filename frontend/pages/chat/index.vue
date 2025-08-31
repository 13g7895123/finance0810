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
      <div 
        ref="userListContainer"
        class="flex-1 overflow-y-auto custom-scrollbar-left"
      >
        <ChatUserList
          ref="chatUserListComponent"
          :users="filteredUsers"
          :activeUserId="activeUserId"
          @userSelect="selectUser"
        />
        
        <!-- 調試信息面板 (僅在開發模式顯示) -->
        <div 
          v-if="showDebugPanel" 
          class="fixed top-4 right-4 bg-white border border-gray-300 rounded-lg shadow-lg p-4 max-w-sm z-50"
        >
          <div class="flex items-center justify-between mb-3">
            <h4 class="font-semibold text-gray-900">聊天室載入檢查</h4>
            <button 
              @click="toggleDebugPanel"
              class="text-gray-500 hover:text-gray-700 font-bold text-lg"
            >
              ✕
            </button>
          </div>
          
          <div class="space-y-2 text-xs text-gray-800">
            <!-- DOM檢查 -->
            <div class="flex items-center justify-between">
              <span class="font-medium text-gray-700">用戶列表容器:</span>
              <span :class="debugInfo.hasUserListContainer ? 'text-green-700 font-bold' : 'text-red-700 font-bold'">
                {{ debugInfo.hasUserListContainer ? '✓' : '✗' }}
              </span>
            </div>
            
            <div class="flex items-center justify-between">
              <span class="font-medium text-gray-700">用戶項目容器:</span>
              <span :class="debugInfo.hasUserItemsContainer ? 'text-green-700 font-bold' : 'text-red-700 font-bold'">
                {{ debugInfo.hasUserItemsContainer ? '✓' : '✗' }}
              </span>
            </div>
            
            <!-- 數據檢查 -->
            <div class="flex items-center justify-between">
              <span class="font-medium text-gray-700">原始對話數據:</span>
              <span class="font-mono font-bold text-gray-900">{{ conversations.length }}</span>
            </div>
            
            <div class="flex items-center justify-between">
              <span class="font-medium text-gray-700">過濾後用戶:</span>
              <span class="font-mono font-bold text-gray-900">{{ filteredUsers.length }}</span>
            </div>
            
            <div class="flex items-center justify-between">
              <span class="font-medium text-gray-700">DOM中用戶項目:</span>
              <span class="font-mono font-bold text-gray-900">{{ debugInfo.renderedUserCount }}</span>
            </div>
            
            <!-- 連接檢查 -->
            <div class="flex items-center justify-between">
              <span class="font-medium text-gray-700">Firebase狀態:</span>
              <span :class="getStatusColor(connectionStatus)" class="font-bold">
                {{ connectionStatus }}
              </span>
            </div>
            
            <!-- 操作按鈕 -->
            <div class="pt-2 border-t border-gray-200 space-y-1">
              <button 
                @click="performFullCheck"
                class="w-full text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 font-medium"
              >
                執行完整檢查
              </button>
              
              <button 
                @click="forceReloadData"
                class="w-full text-xs bg-orange-500 text-white px-2 py-1 rounded hover:bg-orange-600 font-medium"
              >
                強制重新載入數據
              </button>
            </div>
            
            <!-- 檢查結果 -->
            <div v-if="debugInfo.lastCheckResult" class="pt-2 border-t border-gray-200">
              <div class="text-xs font-bold mb-1 text-gray-700">檢查結果:</div>
              <div 
                class="text-xs p-2 rounded whitespace-pre-wrap font-medium"
                :class="debugInfo.lastCheckResult.success ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'"
              >
                {{ debugInfo.lastCheckResult.message }}
              </div>
            </div>
          </div>
        </div>
        
        <!-- 調試按鈕 -->
        <button
          v-if="!showDebugPanel && showDebugControls"
          @click="toggleDebugPanel"
          class="fixed bottom-4 right-4 bg-blue-500 text-white p-2 rounded-full shadow-lg hover:bg-blue-600 z-40"
          title="顯示調試面板"
        >
          🔍
        </button>
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

// 調試相關狀態
const showDebugPanel = ref(false)
const showDebugControls = ref(false) // 默認關閉，通過條件控制
const userListContainer = ref(null)
const chatUserListComponent = ref(null)

// 調試信息
const debugInfo = ref({
  hasUserListContainer: false,
  hasUserItemsContainer: false,
  renderedUserCount: 0,
  lastCheckResult: null
})

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

// ===== 調試功能 =====

/**
 * 切換調試面板顯示
 */
const toggleDebugPanel = () => {
  showDebugPanel.value = !showDebugPanel.value
  if (showDebugPanel.value) {
    updateDebugInfo()
  }
}

/**
 * 更新調試信息
 */
const updateDebugInfo = () => {
  nextTick(() => {
    // 檢查用戶列表容器
    debugInfo.value.hasUserListContainer = !!userListContainer.value
    
    // 檢查用戶項目容器 (ChatUserList組件內的 .space-y-1.p-2)
    const userItemsContainer = userListContainer.value?.querySelector('.space-y-1.p-2')
    debugInfo.value.hasUserItemsContainer = !!userItemsContainer
    
    // 計算DOM中實際渲染的用戶項目數量
    debugInfo.value.renderedUserCount = userItemsContainer?.children?.length || 0
    
    console.log('調試信息更新:', debugInfo.value)
  })
}

/**
 * 執行完整檢查
 */
const performFullCheck = () => {
  console.log('執行聊天室數據載入完整檢查')
  
  updateDebugInfo()
  
  const checks = []
  
  // 1. DOM結構檢查
  if (!debugInfo.value.hasUserListContainer) {
    checks.push('用戶列表容器未找到')
  }
  
  if (!debugInfo.value.hasUserItemsContainer) {
    checks.push('用戶項目容器(.space-y-1.p-2)未找到')
  }
  
  // 2. 數據一致性檢查
  if (filteredUsers.value.length > 0 && debugInfo.value.renderedUserCount === 0) {
    checks.push('有過濾後用戶數據但DOM中沒有渲染項目')
  }
  
  if (filteredUsers.value.length !== debugInfo.value.renderedUserCount) {
    checks.push(`數據不一致: 過濾用戶${filteredUsers.value.length}個，DOM渲染${debugInfo.value.renderedUserCount}個`)
  }
  
  // 3. Firebase連接檢查
  if (connectionStatus.value === 'error') {
    checks.push('Firebase連接異常')
  }
  
  if (connectionStatus.value === 'disconnected') {
    checks.push('Firebase未連接')
  }
  
  // 4. 數據源檢查
  if (conversations.value.length === 0) {
    checks.push('無原始對話數據')
  }
  
  // 生成檢查結果
  if (checks.length === 0) {
    debugInfo.value.lastCheckResult = {
      success: true,
      message: '所有檢查項目通過 ✓'
    }
  } else {
    debugInfo.value.lastCheckResult = {
      success: false,
      message: `發現 ${checks.length} 個問題:\n${checks.join('\n')}`
    }
  }
  
  // 輸出詳細檢查結果到控制台
  console.log('聊天室檢查結果:', {
    conversations: conversations.value.length,
    filteredUsers: filteredUsers.value.length,
    renderedCount: debugInfo.value.renderedUserCount,
    connectionStatus: connectionStatus.value,
    hasContainers: {
      userListContainer: debugInfo.value.hasUserListContainer,
      userItemsContainer: debugInfo.value.hasUserItemsContainer
    },
    issues: checks
  })
}

/**
 * 獲取狀態顏色樣式
 */
const getStatusColor = (status) => {
  switch (status) {
    case 'connected':
      return 'text-green-700'
    case 'connecting':
      return 'text-yellow-700'
    case 'error':
      return 'text-red-700'
    case 'disconnected':
    default:
      return 'text-gray-700'
  }
}

/**
 * 強制重新載入數據
 */
const forceReloadData = async () => {
  console.log('強制重新載入聊天室數據')
  
  try {
    debugInfo.value.lastCheckResult = {
      success: false,
      message: '正在重新載入數據...'
    }
    
    // 完全清理並重新初始化
    await realtimeChat.cleanup()
    await nextTick()
    
    // 重新初始化
    await realtimeChat.initialize()
    
    // 更新調試信息
    await nextTick(() => {
      updateDebugInfo()
      performFullCheck()
    })
    
    console.log('數據重新載入完成')
    
  } catch (error) {
    console.error('強制重新載入失敗:', error)
    debugInfo.value.lastCheckResult = {
      success: false,
      message: `重新載入失敗: ${error.message}`
    }
  }
}


// 監聽數據變化，自動更新調試信息
watch([conversations, filteredUsers, connectionStatus], () => {
  if (showDebugPanel.value) {
    updateDebugInfo()
  }
}, { deep: true })

// 頁面初始化
onMounted(async () => {
  console.log('即時聊天室初始化開始...')
  
  try {
    await realtimeChat.initialize()
    console.log('即時聊天室初始化完成')
    
    // 初始化調試信息
    nextTick(() => {
      updateDebugInfo()
    })
    
    // 添加頁面可見性監聽器
    document.addEventListener('visibilitychange', handleVisibilityChange)
    console.log('頁面可見性監聽器已設置')
    
    // 添加鍵盤快捷鍵監聽器
    document.addEventListener('keydown', handleKeyDown)
    console.log('鍵盤快捷鍵監聽器已設置 (Ctrl+Shift+D)')
    
    // 設置調試控制項可見性
    showDebugControls.value = window.location.hostname === 'localhost' || 
                             window.location.hostname === '127.0.0.1' || 
                             localStorage.getItem('chatDebugMode') === 'true'
    
    // 檢查URL參數是否要求顯示調試面板
    const urlParams = new URLSearchParams(window.location.search)
    if (urlParams.has('debug') || localStorage.getItem('chatDebugMode') === 'true') {
      showDebugPanel.value = true
      showDebugControls.value = true
      console.log('調試模式已啟用')
    }
    
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
  
  // 移除鍵盤快捷鍵監聽器
  document.removeEventListener('keydown', handleKeyDown)
  console.log('鍵盤快捷鍵監聽器已移除')
  
  realtimeChat.cleanup()
})

onUnmounted(() => {
  // 確保清理工作完成
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  document.removeEventListener('keydown', handleKeyDown)
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
    
    // 更新調試信息
    if (showDebugPanel.value) {
      updateDebugInfo()
    }
    
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

// 鍵盤快捷鍵處理
const handleKeyDown = (event) => {
  // Ctrl+Shift+D 或 Cmd+Shift+D 切換調試面板
  if ((event.ctrlKey || event.metaKey) && event.shiftKey && event.key === 'D') {
    event.preventDefault()
    toggleDebugPanel()
  }
}

// 全域調試函數 - 可在瀏覽器控制台使用
if (typeof window !== 'undefined') {
  window.toggleChatDebug = () => {
    showDebugPanel.value = !showDebugPanel.value
    const mode = showDebugPanel.value ? 'enabled' : 'disabled'
    localStorage.setItem('chatDebugMode', showDebugPanel.value.toString())
    console.log(`聊天室調試模式已${mode === 'enabled' ? '啟用' : '禁用'}`)
    if (showDebugPanel.value) {
      updateDebugInfo()
    }
  }
  
  window.performChatCheck = performFullCheck
  window.forceReloadChatData = forceReloadData
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