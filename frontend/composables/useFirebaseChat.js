import { ref, reactive, onUnmounted } from 'vue'
import { 
  collection, 
  doc, 
  query, 
  where, 
  orderBy, 
  limit, 
  onSnapshot,
  getDocs 
} from 'firebase/firestore'

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
      console.warn('Firebase Firestore not available, falling back to API')
      connectionStatus.value = 'error'
      return false
    }
    
    connectionStatus.value = 'connecting'
    isFirebaseConnected.value = true
    connectionStatus.value = 'connected'
    console.log('Firebase Firestore connection initialized')
    return true
  }

  /**
   * 監聽對話列表變化
   */
  const watchConversations = (staffId = null) => {
    if (!isFirebaseConnected.value) return

    try {
      const conversationsRef = collection($firebaseDB, 'conversations')
      let conversationsQuery = conversationsRef
      
      // 如果指定了 staffId，只監聽分配給該員工的對話
      // staffId 為 null 表示 admin/executive 用戶，可以看所有對話
      if (staffId) {
        conversationsQuery = query(
          conversationsRef,
          where('assignedStaffId', '==', staffId),
          orderBy('updated', 'desc')
        )
      } else {
        conversationsQuery = query(
          conversationsRef,
          orderBy('updated', 'desc')
        )
      }

      const unsubscribe = onSnapshot(conversationsQuery, (querySnapshot) => {
        const conversationsList = []
        
        querySnapshot.forEach((doc) => {
          const data = doc.data()
          conversationsList.push({
            id: data.mysqlCustomerId || doc.id,
            lineUserId: doc.id,
            name: data.customerName || '客戶',
            role: 'line_customer',
            avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(data.customerName || '客戶')}&background=00C300&color=fff`,
            lastMessage: data.lastMessage?.content || '',
            timestamp: data.lastMessage?.timestamp ? new Date(data.lastMessage.timestamp) : new Date(),
            unreadCount: data.unreadCount?.staff || 0,
            online: false,
            isBot: true,
            customerInfo: {
              phone: data.customerPhone || '',
              region: data.customerRegion || '',
              source: data.customerSource || '',
              status: data.status || ''
            }
          })
        })
        
        conversations.value = conversationsList
        console.log('Firebase Firestore conversations updated:', conversationsList.length)
        
      }, (error) => {
        console.error('Firebase Firestore conversations listener error:', error)
        handleFirebaseError(error)
      })

      // 儲存監聽器引用以便清理
      listeners.value.set('conversations', unsubscribe)
      
    } catch (error) {
      console.error('Failed to setup Firestore conversations listener:', error)
      handleFirebaseError(error)
    }
  }

  /**
   * 監聽特定用戶的訊息變化
   */
  const watchMessages = (lineUserId) => {
    if (!isFirebaseConnected.value || !lineUserId) return

    try {
      const messagesRef = collection($firebaseDB, 'conversations', lineUserId, 'messages')
      const messagesQuery = query(
        messagesRef, 
        orderBy('timestamp', 'asc'),
        limit(100)
      )

      const unsubscribe = onSnapshot(messagesQuery, (querySnapshot) => {
        const messagesList = []
        
        querySnapshot.forEach((doc) => {
          const msg = doc.data()
          messagesList.push({
            id: doc.id,
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
        
        messages[lineUserId] = messagesList
        console.log(`Firebase Firestore messages updated for ${lineUserId}:`, messagesList.length)
        
      }, (error) => {
        console.error(`Firebase Firestore messages listener error for ${lineUserId}:`, error)
        handleFirebaseError(error)
      })

      // 儲存監聽器引用以便清理
      listeners.value.set(`messages_${lineUserId}`, unsubscribe)
      
    } catch (error) {
      console.error(`Failed to setup Firestore messages listener for ${lineUserId}:`, error)
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