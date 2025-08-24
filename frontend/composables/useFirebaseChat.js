import { ref, reactive, onUnmounted } from 'vue'
import { onValue, ref as dbRef, off, orderByChild, query, limitToLast } from 'firebase/database'

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
      console.warn('Firebase Database not available, falling back to API')
      connectionStatus.value = 'error'
      return false
    }
    
    connectionStatus.value = 'connecting'
    isFirebaseConnected.value = true
    connectionStatus.value = 'connected'
    return true
  }

  /**
   * 監聽對話列表變化
   */
  const watchConversations = (staffId = null) => {
    if (!isFirebaseConnected.value) return

    try {
      const conversationsRef = dbRef($firebaseDB, 'conversations')
      let conversationsQuery = conversationsRef
      
      // 如果指定了 staffId，只監聽分配給該員工的對話
      // staffId 為 null 表示 admin/executive 用戶，可以看所有對話
      if (staffId) {
        conversationsQuery = query(
          conversationsRef,
          orderByChild('assignedStaffId'),
          // equalTo(staffId) // 需要Firebase SDK正確配置
        )
      }

      const unsubscribe = onValue(conversationsQuery, (snapshot) => {
        const data = snapshot.val()
        if (data) {
          // 轉換Firebase數據格式為前端格式
          const conversationsList = Object.keys(data).map(lineUserId => ({
            id: parseInt(lineUserId),
            lineUserId,
            name: data[lineUserId].customerName || '客戶',
            role: 'line_customer', 
            avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(data[lineUserId].customerName || '客戶')}&background=00C300&color=fff`,
            lastMessage: data[lineUserId].lastMessage?.content || '',
            timestamp: data[lineUserId].lastMessage?.timestamp ? new Date(data[lineUserId].lastMessage.timestamp) : new Date(),
            unreadCount: data[lineUserId].unreadCount?.staff || 0,
            online: false,
            isBot: true,
            customerInfo: {
              phone: data[lineUserId].customerPhone || '',
              region: data[lineUserId].customerRegion || '',
              source: data[lineUserId].customerSource || '',
              status: data[lineUserId].status || ''
            }
          }))

          // 按時間排序（最新的在前面）
          conversationsList.sort((a, b) => b.timestamp - a.timestamp)
          
          conversations.value = conversationsList
          console.log('Firebase conversations updated:', conversationsList.length)
        } else {
          conversations.value = []
        }
      }, (error) => {
        console.error('Firebase conversations listener error:', error)
        handleFirebaseError(error)
      })

      // 儲存監聽器引用以便清理
      listeners.value.set('conversations', unsubscribe)
      
    } catch (error) {
      console.error('Failed to setup conversations listener:', error)
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
      const messagesQuery = query(messagesRef, orderByChild('timestamp'), limitToLast(100))

      const unsubscribe = onValue(messagesQuery, (snapshot) => {
        const data = snapshot.val()
        if (data) {
          // 轉換Firebase數據格式為前端格式
          const messagesList = Object.keys(data).map(messageId => {
            const msg = data[messageId]
            return {
              id: messageId,
              senderId: msg.senderId === 'customer' ? parseInt(lineUserId) : 'system',
              content: msg.content,
              timestamp: new Date(msg.timestamp),
              type: msg.type || 'text',
              isBot: msg.senderId !== 'customer',
              isCustomer: msg.senderId === 'customer',
              isAutoReply: msg.senderId !== 'customer',
              metadata: msg.metadata || {}
            }
          })

          // 按時間排序（舊的在前面，新的在後面）
          messagesList.sort((a, b) => a.timestamp - b.timestamp)
          
          messages[lineUserId] = messagesList
          console.log(`Firebase messages updated for ${lineUserId}:`, messagesList.length)
        } else {
          messages[lineUserId] = []
        }
      }, (error) => {
        console.error(`Firebase messages listener error for ${lineUserId}:`, error)
        handleFirebaseError(error)
      })

      // 儲存監聽器引用以便清理
      listeners.value.set(`messages_${lineUserId}`, unsubscribe)
      
    } catch (error) {
      console.error(`Failed to setup messages listener for ${lineUserId}:`, error)
      handleFirebaseError(error)
    }
  }

  /**
   * 停止監聽特定用戶的訊息
   */
  const unwatchMessages = (lineUserId) => {
    const listenerKey = `messages_${lineUserId}`
    const unsubscribe = listeners.value.get(listenerKey)
    
    if (unsubscribe) {
      unsubscribe() // Firebase v9+ 監聽器直接調用即可取消
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
    
    // 如果Firebase出錯，可以fallback到原有的API輪詢
    console.warn('Firebase error, may need to fallback to API polling')
  }

  /**
   * 清理所有監聽器
   */
  const cleanup = () => {
    listeners.value.forEach((unsubscribe, key) => {
      try {
        unsubscribe()
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