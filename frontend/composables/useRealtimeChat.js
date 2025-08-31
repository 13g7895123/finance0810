import { ref, computed, watch } from 'vue'

export const useRealtimeChat = () => {
  // Firebase即時聊天
  const firebaseChat = useFirebaseChat()
  
  // 發送訊息仍需要通過API
  const { replyMessage } = useChat()
  
  // 狀態管理
  const conversations = ref([])
  const messages = ref({})
  const connectionStatus = ref('disconnected')
  const error = ref(null)
  
  // 監聽器管理
  let watcherCleanupFns = []

  /**
   * 初始化即時聊天
   */
  const initialize = async () => {
    console.log('初始化Firebase即時聊天系統...')
    
    try {
      // 如果已經連接，先清理舊的連接
      if (connectionStatus.value === 'connected') {
        console.log('檢測到已有連接，先清理舊連接')
        await cleanup()
      }
      
      // 重置狀態
      conversations.value = []
      messages.value = {}
      error.value = null
      connectionStatus.value = 'connecting'
      
      // 初始化Firebase
      const firebaseAvailable = firebaseChat.initializeFirebase()
      
      console.log('Firebase初始化結果:', firebaseAvailable)
      console.log('Firebase可用性檢查:', firebaseChat.isAvailable())
      
      if (!firebaseAvailable || !firebaseChat.isAvailable()) {
        console.error('Firebase不可用，聊天室無法正常運作')
        console.error('Firebase檢查詳細資訊:', {
          firebaseAvailable,
          isAvailable: firebaseChat.isAvailable(),
          hasNuxtApp: !!useNuxtApp(),
          hasFirebaseDB: !!useNuxtApp().$firebaseDB
        })
        connectionStatus.value = 'error'
        error.value = 'Firebase連接失敗，請檢查配置'
        return
      }
      
      console.log('Firebase即時聊天已啟用')
      connectionStatus.value = 'connected'
      
      // 開始監聽Firebase變化
      await startFirebaseListeners()
      console.log('Firebase監聽器啟動完成')
      
    } catch (error) {
      console.error('初始化Firebase即時聊天系統失敗:', error)
      connectionStatus.value = 'error'
      error.value = `初始化失敗: ${error.message}`
    }
  }

  /**
   * 清理舊的監聽器
   */
  const cleanupWatchers = () => {
    watcherCleanupFns.forEach(cleanup => {
      try {
        cleanup()
      } catch (error) {
        console.warn('清理監聽器時出錯:', error)
      }
    })
    watcherCleanupFns = []
  }

  /**
   * 開始Firebase監聽
   */
  const startFirebaseListeners = async () => {
    try {
      // 先清理舊的監聽器
      cleanupWatchers()
      
      const { canViewAllChats, getLocalUser } = useAuth()
      const user = getLocalUser()
      
      // Admin/executive 用戶可以看所有對話，其他用戶只能看分配給自己的
      const staffId = canViewAllChats() ? null : user?.id
      
      // 監聽對話列表
      firebaseChat.watchConversations(staffId)
      
      // 監聽Firebase狀態變化 - 設置新的監聽器並保存清理函數
      const conversationsWatcherStop = watch(firebaseChat.conversations, (newConversations) => {
        console.log('Firebase conversations 更新:', newConversations.length)
        conversations.value = [...newConversations]
      }, { deep: true, immediate: true })
      watcherCleanupFns.push(conversationsWatcherStop)
      
      const messagesWatcherStop = watch(firebaseChat.messages, (newMessages) => {
        console.log('Firebase messages 更新:', Object.keys(newMessages).length, '個對話')
        messages.value = { ...newMessages }
      }, { deep: true, immediate: true })
      watcherCleanupFns.push(messagesWatcherStop)
      
      const statusWatcherStop = watch(firebaseChat.connectionStatus, (status) => {
        console.log('Firebase 連接狀態變化:', status)
        connectionStatus.value = status
        
        // Firebase連接失敗時顯示錯誤
        if (status === 'error') {
          console.error('Firebase連接失敗，聊天室功能異常')
          error.value = 'Firebase連接異常，請重新整理頁面或聯繫系統管理員'
        } else if (status === 'connected') {
          error.value = null
        }
      }, { immediate: true })
      watcherCleanupFns.push(statusWatcherStop)
      
    } catch (error) {
      console.error('Firebase監聽器啟動失敗:', error)
      connectionStatus.value = 'error'
      error.value = 'Firebase監聽器初始化失敗'
    }
  }


  /**
   * 載入特定用戶的訊息
   */
  const loadMessages = async (lineUserId) => {
    // 使用Firebase監聽
    firebaseChat.watchMessages(lineUserId)
  }

  /**
   * 發送訊息
   */
  const sendMessage = async (lineUserId, content) => {
    try {
      // 發送訊息通過API，Firebase會自動同步更新
      const response = await replyMessage(lineUserId, content)
      
      // Firebase模式下，訊息會通過即時監聽自動更新
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
    firebaseChat.unwatchMessages(lineUserId)
  }

  /**
   * 清理所有資源
   */
  const cleanup = () => {
    console.log('清理Firebase即時聊天資源...')
    
    // 清理監聽器
    cleanupWatchers()
    
    // 清理Firebase資源
    firebaseChat.cleanup()
    
    // 重置狀態
    conversations.value = []
    messages.value = {}
    connectionStatus.value = 'disconnected'
    error.value = null
    
    console.log('Firebase即時聊天資源清理完成')
  }

  /**
   * 獲取連接狀態文字
   */
  const getConnectionStatusText = () => {
    switch (connectionStatus.value) {
      case 'connected': return 'Firebase已連接'
      case 'connecting': return '連接Firebase中...'
      case 'error': return 'Firebase連接異常'
      default: return 'Firebase未連接'
    }
  }

  return {
    // 狀態
    conversations: readonly(conversations),
    messages: readonly(messages),
    connectionStatus: readonly(connectionStatus),
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