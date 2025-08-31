import { ref, reactive, onUnmounted } from 'vue'
import { 
  ref as dbRef, 
  child, 
  query, 
  orderByChild, 
  limitToLast, 
  onValue, 
  get,
  equalTo,
  orderByKey,
  off
} from 'firebase/database'

export const useFirebaseChat = () => {
  const { $firebaseDB } = useNuxtApp()
  const authStore = useAuthStore()
  
  // 狀態管理
  const isFirebaseConnected = ref(false)
  const conversations = ref([])
  const messages = reactive({})
  const listeners = ref(new Map()) // 追蹤所有監聽器以便清理
  
  // 錯誤處理
  const error = ref(null)
  const connectionStatus = ref('disconnected') // 'connected', 'connecting', 'disconnected', 'error'

  /**
   * 初始化Firebase連接
   */
  const initializeFirebase = () => {
    if (!$firebaseDB) {
      console.warn('Firebase Realtime Database not available, falling back to API')
      connectionStatus.value = 'error'
      isFirebaseConnected.value = false
      return false
    }
    
    // 如果已經連接，先清理
    if (isFirebaseConnected.value) {
      console.log('重新初始化Firebase，先清理舊連接')
      cleanup()
    }
    
    connectionStatus.value = 'connecting'
    
    // 重置狀態
    conversations.value = []
    Object.keys(messages).forEach(key => delete messages[key])
    error.value = null
    
    isFirebaseConnected.value = true
    connectionStatus.value = 'connected'
    console.log('Firebase Realtime Database connection initialized')
    return true
  }

  /**
   * 監聽對話列表變化
   */
  const watchConversations = (staffId = null) => {
    if (!isFirebaseConnected.value) return

    try {
      const conversationsRef = dbRef($firebaseDB, 'conversations')

      const listener = onValue(conversationsRef, (snapshot) => {
        const conversationsList = []
        
        if (snapshot.exists()) {
          const data = snapshot.val()
          
          Object.keys(data).forEach((lineUserId) => {
            const conversationData = data[lineUserId]
            
            // 如果指定了 staffId，只顯示分配給該員工的對話
            // staffId 為 null 表示 admin/executive 用戶，可以看所有對話
            if (staffId && conversationData.assignedStaffId !== staffId) {
              return
            }
            
            conversationsList.push({
              id: conversationData.mysqlCustomerId || lineUserId,
              lineUserId: lineUserId,
              name: conversationData.customerName || '客戶',
              role: 'line_customer',
              avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(conversationData.customerName || '客戶')}&background=00C300&color=fff`,
              lastMessage: conversationData.lastMessage?.content || '',
              timestamp: conversationData.lastMessage?.timestamp ? new Date(conversationData.lastMessage.timestamp) : new Date(),
              unreadCount: conversationData.unreadCount?.staff || 0,
              online: false,
              isBot: true,
              customerInfo: {
                phone: conversationData.customerPhone || '',
                region: conversationData.customerRegion || '',
                source: conversationData.customerSource || '',
                status: conversationData.status || ''
              }
            })
          })
          
          // 按更新時間排序
          conversationsList.sort((a, b) => b.timestamp - a.timestamp)
        }
        
        conversations.value = conversationsList
        console.log('Firebase Realtime Database conversations updated:', conversationsList.length)
        
      }, (error) => {
        console.error('Firebase Realtime Database conversations listener error:', error)
        handleFirebaseError(error)
      })

      // 儲存監聽器引用以便清理
      listeners.value.set('conversations', { ref: conversationsRef, listener })
      
    } catch (error) {
      console.error('Failed to setup Realtime Database conversations listener:', error)
      handleFirebaseError(error)
    }
  }

  /**
   * 監聽特定用戶的訊息變化
   */
  const watchMessages = (lineUserId) => {
    if (!isFirebaseConnected.value || !lineUserId) return

    try {
      const messagesRef = dbRef($firebaseDB, `conversations/${lineUserId}/messages`)
      const messagesQuery = query(
        messagesRef, 
        orderByChild('timestamp')
      )

      const listener = onValue(messagesQuery, (snapshot) => {
        const messagesList = []
        
        if (snapshot.exists()) {
          const data = snapshot.val()
          
          Object.keys(data).forEach((messageId) => {
            const msg = data[messageId]
            messagesList.push({
              id: messageId,
              senderId: msg.senderId === 'customer' ? parseInt(lineUserId) : 'system',
              content: msg.content,
              timestamp: msg.timestamp ? new Date(msg.timestamp) : new Date(),
              type: msg.type || 'text',
              isBot: msg.senderId !== 'customer',
              isCustomer: msg.senderId === 'customer',
              isAutoReply: msg.senderId !== 'customer',
              metadata: msg.metadata || {}
            })
          })
          
          // 按時間戳記排序
          messagesList.sort((a, b) => a.timestamp - b.timestamp)
        }
        
        messages[lineUserId] = messagesList
        console.log(`Firebase Realtime Database messages updated for ${lineUserId}:`, messagesList.length)
        
      }, (error) => {
        console.error(`Firebase Realtime Database messages listener error for ${lineUserId}:`, error)
        handleFirebaseError(error)
      })

      // 儲存監聽器引用以便清理
      listeners.value.set(`messages_${lineUserId}`, { ref: messagesRef, listener })
      
    } catch (error) {
      console.error(`Failed to setup Realtime Database messages listener for ${lineUserId}:`, error)
      handleFirebaseError(error)
    }
  }

  /**
   * 停止監聽特定用戶的訊息
   */
  const unwatchMessages = (lineUserId) => {
    const listenerKey = `messages_${lineUserId}`
    const listenerData = listeners.value.get(listenerKey)
    
    if (listenerData) {
      off(listenerData.ref, 'value', listenerData.listener)
      listeners.value.delete(listenerKey)
      console.log(`Stopped watching messages for ${lineUserId}`)
    }
  }

  /**
   * 錯誤處理
   */
  const handleFirebaseError = (firebaseError) => {
    error.value = firebaseError
    connectionStatus.value = 'error'
    
    // Firebase錯誤記錄，不使用API輪詢fallback
    console.error('Firebase Realtime Database error:', firebaseError)
  }

  /**
   * 清理所有監聽器
   */
  const cleanup = () => {
    listeners.value.forEach((listenerData, key) => {
      try {
        off(listenerData.ref, 'value', listenerData.listener)
        console.log(`Cleaned up Firebase listener: ${key}`)
      } catch (error) {
        console.error(`Error cleaning up Firebase listener ${key}:`, error)
      }
    })
    
    listeners.value.clear()
    isFirebaseConnected.value = false
    connectionStatus.value = 'disconnected'
  }

  /**
   * 檢查Firebase是否可用
   */
  const isAvailable = () => {
    return !!$firebaseDB && isFirebaseConnected.value
  }

  // 組件卸載時清理
  onUnmounted(() => {
    cleanup()
  })

  return {
    // 狀態
    isFirebaseConnected: readonly(isFirebaseConnected),
    conversations: readonly(conversations),
    messages: readonly(messages),
    error: readonly(error),
    connectionStatus: readonly(connectionStatus),
    
    // 方法
    initializeFirebase,
    watchConversations,
    watchMessages,
    unwatchMessages,
    cleanup,
    isAvailable
  }
}