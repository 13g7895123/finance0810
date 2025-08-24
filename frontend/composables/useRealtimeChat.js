import { ref, computed, watch } from 'vue'

export const useRealtimeChat = () => {
  // Firebase即時聊天
  const firebaseChat = useFirebaseChat()
  
  // 原有的API聊天（fallback）
  const { getConversations, getConversation, replyMessage } = useChat()
  
  // 狀態管理
  const useFirebase = ref(false)
  const conversations = ref([])
  const messages = ref({})
  const connectionStatus = ref('disconnected')
  const error = ref(null)
  
  // API fallback的輪詢控制
  const apiPollingInterval = ref(null)
  const isPollingActive = ref(false)

  /**
   * 初始化即時聊天
   */
  const initialize = async () => {
    console.log('初始化即時聊天系統...')
    
    // 首先嘗試初始化Firebase
    const firebaseAvailable = firebaseChat.initializeFirebase()
    
    if (firebaseAvailable && firebaseChat.isAvailable()) {
      console.log('使用Firebase即時更新')
      useFirebase.value = true
      connectionStatus.value = 'connected'
      
      // 開始監聽Firebase變化
      await startFirebaseListeners()
    } else {
      console.log('Firebase不可用，使用API輪詢fallback')
      useFirebase.value = false
      connectionStatus.value = 'connecting'
      
      // 開始API輪詢
      await startApiPolling()
    }
  }

  /**
   * 開始Firebase監聽
   */
  const startFirebaseListeners = async () => {
    try {
      const authStore = useAuthStore()
      const staffId = authStore.isSales ? authStore.user?.id : null
      
      // 監聽對話列表
      firebaseChat.watchConversations(staffId)
      
      // 監聽Firebase狀態變化
      watch(firebaseChat.conversations, (newConversations) => {
        conversations.value = [...newConversations]
      }, { deep: true })
      
      watch(firebaseChat.messages, (newMessages) => {
        messages.value = { ...newMessages }
      }, { deep: true })
      
      watch(firebaseChat.connectionStatus, (status) => {
        connectionStatus.value = status
        
        // 如果Firebase連接失敗，切換到API輪詢
        if (status === 'error' && useFirebase.value) {
          console.warn('Firebase連接失敗，切換到API輪詢')
          useFirebase.value = false
          startApiPolling()
        }
      })
      
    } catch (error) {
      console.error('Firebase監聽器啟動失敗:', error)
      useFirebase.value = false
      startApiPolling()
    }
  }

  /**
   * 開始API輪詢（fallback）
   */
  const startApiPolling = async () => {
    if (isPollingActive.value) {
      console.log('API輪詢已在運行')
      return
    }
    
    console.log('啟動API輪詢模式')
    isPollingActive.value = true
    connectionStatus.value = 'connected'
    
    // 立即載入一次
    await loadConversationsFromAPI()
    
    // 設定定期輪詢（每3秒）
    apiPollingInterval.value = setInterval(async () => {
      if (isPollingActive.value) {
        await loadConversationsFromAPI()
      }
    }, 3000)
  }

  /**
   * 停止API輪詢
   */
  const stopApiPolling = () => {
    if (apiPollingInterval.value) {
      clearInterval(apiPollingInterval.value)
      apiPollingInterval.value = null
    }
    isPollingActive.value = false
    console.log('API輪詢已停止')
  }

  /**
   * 從API載入對話列表
   */
  const loadConversationsFromAPI = async () => {
    try {
      const response = await getConversations()
      
      if (response?.data) {
        const apiConversations = response.data.map(conv => ({
          id: parseInt(conv.line_user_id),
          lineUserId: conv.line_user_id,
          name: conv.customer?.name || conv.customer_name || '客戶',
          role: 'line_customer',
          avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(conv.customer?.name || '客戶')}&background=00C300&color=fff`,
          lastMessage: conv.last_message || '',
          timestamp: new Date(conv.last_message_time),
          unreadCount: conv.unread_count || 0,
          online: false,
          isBot: true,
          customerInfo: {
            phone: conv.customer?.phone || '',
            region: conv.customer?.region || '',
            source: conv.customer?.source || '',
            status: conv.customer?.status || ''
          }
        }))
        
        // 去重並排序
        const conversationsMap = new Map()
        apiConversations.forEach(conv => {
          conversationsMap.set(conv.lineUserId, conv)
        })
        
        const sortedConversations = Array.from(conversationsMap.values())
          .sort((a, b) => b.timestamp - a.timestamp)
        
        conversations.value = sortedConversations
      }
    } catch (error) {
      console.error('API載入對話列表失敗:', error)
      connectionStatus.value = 'error'
    }
  }

  /**
   * 載入特定用戶的訊息
   */
  const loadMessages = async (lineUserId) => {
    if (useFirebase.value) {
      // 使用Firebase監聽
      firebaseChat.watchMessages(lineUserId)
    } else {
      // 使用API載入
      await loadMessagesFromAPI(lineUserId)
    }
  }

  /**
   * 從API載入訊息
   */
  const loadMessagesFromAPI = async (lineUserId) => {
    try {
      const response = await getConversation(lineUserId)
      
      if (response?.data && Array.isArray(response.data)) {
        const apiMessages = response.data.map(msg => ({
          id: msg.id,
          senderId: msg.is_from_customer ? parseInt(lineUserId) : 'system',
          content: msg.message_content,
          timestamp: new Date(msg.message_timestamp),
          type: msg.message_type || 'text',
          isBot: !msg.is_from_customer,
          isCustomer: msg.is_from_customer,
          isAutoReply: !msg.is_from_customer,
          metadata: msg.metadata || {}
        }))
        
        // 按時間排序
        apiMessages.sort((a, b) => a.timestamp - b.timestamp)
        messages.value[lineUserId] = apiMessages
      }
    } catch (error) {
      console.error(`API載入訊息失敗 ${lineUserId}:`, error)
    }
  }

  /**
   * 發送訊息
   */
  const sendMessage = async (lineUserId, content) => {
    try {
      // 無論使用Firebase或API，發送訊息都通過API
      const response = await replyMessage(lineUserId, content)
      
      if (response?.conversation) {
        // 如果使用API輪詢，立即更新本地訊息
        if (!useFirebase.value) {
          await loadMessagesFromAPI(lineUserId)
        }
        // Firebase模式下，訊息會通過即時監聽自動更新
      }
      
      return response
    } catch (error) {
      console.error('發送訊息失敗:', error)
      throw error
    }
  }

  /**
   * 停止監聽特定用戶訊息
   */
  const unwatchMessages = (lineUserId) => {
    if (useFirebase.value) {
      firebaseChat.unwatchMessages(lineUserId)
    }
    // API模式不需要特殊處理
  }

  /**
   * 清理所有資源
   */
  const cleanup = () => {
    console.log('清理即時聊天資源...')
    
    if (useFirebase.value) {
      firebaseChat.cleanup()
    }
    
    stopApiPolling()
    
    conversations.value = []
    messages.value = {}
    connectionStatus.value = 'disconnected'
  }

  /**
   * 獲取連接狀態文字
   */
  const getConnectionStatusText = () => {
    if (useFirebase.value) {
      switch (connectionStatus.value) {
        case 'connected': return '即時連接'
        case 'connecting': return '連接中...'
        case 'error': return '連接異常'
        default: return '未連接'
      }
    } else {
      switch (connectionStatus.value) {
        case 'connected': return 'API輪詢'
        case 'connecting': return '連接中...'
        case 'error': return '連接異常'
        default: return '未連接'
      }
    }
  }

  return {
    // 狀態
    conversations: readonly(conversations),
    messages: readonly(messages),
    connectionStatus: readonly(connectionStatus),
    useFirebase: readonly(useFirebase),
    error: readonly(error),
    
    // 方法
    initialize,
    loadMessages,
    sendMessage,
    unwatchMessages,
    cleanup,
    getConnectionStatusText
  }
}