/**
 * WebSocket Composable for Real-time Updates
 * 用於實時更新的WebSocket封裝
 */

export const useWebSocket = () => {
  const socket = ref(null)
  const isConnected = ref(false)
  const reconnectAttempts = ref(0)
  const maxReconnectAttempts = 5
  const reconnectDelay = 2000
  
  const authStore = useAuthStore()
  
  /**
   * 連接WebSocket
   */
  const connect = () => {
    try {
      // 使用environment變數或默認WebSocket URL
      const wsUrl = process.env.NUXT_WS_URL || 'ws://localhost:9203'
      const token = authStore.token
      
      if (!token) {
        console.warn('No auth token available for WebSocket connection')
        return
      }
      
      // 建立WebSocket連接，包含認證token
      socket.value = new WebSocket(`${wsUrl}?token=${encodeURIComponent(token)}`)
      
      socket.value.onopen = () => {
        console.log('WebSocket connected')
        isConnected.value = true
        reconnectAttempts.value = 0
        
        // 設置訊息處理器
        setupMessageHandler()
        
        // 發送初始認證訊息
        sendMessage({
          type: 'auth',
          token: token
        })
      }
      
      socket.value.onclose = (event) => {
        console.log('WebSocket disconnected:', event.code, event.reason)
        isConnected.value = false
        
        // 自動重連（除非是正常關閉）
        if (event.code !== 1000 && reconnectAttempts.value < maxReconnectAttempts) {
          setTimeout(() => {
            reconnectAttempts.value++
            console.log(`Attempting to reconnect (${reconnectAttempts.value}/${maxReconnectAttempts})`)
            connect()
          }, reconnectDelay * reconnectAttempts.value)
        }
      }
      
      socket.value.onerror = (error) => {
        console.error('WebSocket error:', error)
      }
      
    } catch (error) {
      console.error('Failed to create WebSocket connection:', error)
    }
  }
  
  /**
   * 斷開WebSocket連接
   */
  const disconnect = () => {
    if (socket.value) {
      reconnectAttempts.value = maxReconnectAttempts // 防止自動重連
      socket.value.close(1000, 'Manual disconnect')
      socket.value = null
      isConnected.value = false
      // 清理所有訊息監聽器
      messageListeners.value.clear()
    }
  }
  
  /**
   * 發送訊息
   */
  const sendMessage = (message) => {
    if (socket.value && isConnected.value) {
      try {
        socket.value.send(JSON.stringify(message))
        return true
      } catch (error) {
        console.error('Failed to send WebSocket message:', error)
        return false
      }
    }
    return false
  }
  
  // 儲存所有訊息監聽器
  const messageListeners = ref(new Map())
  
  /**
   * 設置訊息處理器
   */
  const setupMessageHandler = () => {
    if (!socket.value) return
    
    socket.value.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data)
        
        // 執行所有匹配類型的回調
        if (messageListeners.value.has(data.type)) {
          const callbacks = messageListeners.value.get(data.type)
          callbacks.forEach(callback => {
            try {
              callback(data)
            } catch (error) {
              console.error(`Error in message callback for type ${data.type}:`, error)
            }
          })
        }
        
        // 執行所有通用回調
        if (messageListeners.value.has('*')) {
          const generalCallbacks = messageListeners.value.get('*')
          generalCallbacks.forEach(callback => {
            try {
              callback(data)
            } catch (error) {
              console.error('Error in general message callback:', error)
            }
          })
        }
        
      } catch (error) {
        console.error('Failed to parse WebSocket message:', error)
      }
    }
  }
  
  /**
   * 監聽特定類型的訊息
   */
  const onMessage = (type, callback) => {
    if (!messageListeners.value.has(type)) {
      messageListeners.value.set(type, [])
    }
    messageListeners.value.get(type).push(callback)
  }
  
  /**
   * 監聽所有訊息
   */
  const onAnyMessage = (callback) => {
    onMessage('*', callback)
  }
  
  /**
   * 加入聊天室
   */
  const joinChatRoom = (roomId) => {
    return sendMessage({
      type: 'join_room',
      room: roomId
    })
  }
  
  /**
   * 離開聊天室
   */
  const leaveChatRoom = (roomId) => {
    return sendMessage({
      type: 'leave_room',
      room: roomId
    })
  }
  
  /**
   * 發送聊天訊息
   */
  const sendChatMessage = (roomId, message) => {
    return sendMessage({
      type: 'chat_message',
      room: roomId,
      message: message
    })
  }
  
  /**
   * 當組件銷毀時清理
   */
  onUnmounted(() => {
    disconnect()
  })
  
  return {
    socket: readonly(socket),
    isConnected: readonly(isConnected),
    reconnectAttempts: readonly(reconnectAttempts),
    connect,
    disconnect,
    sendMessage,
    onMessage,
    onAnyMessage,
    joinChatRoom,
    leaveChatRoom,
    sendChatMessage
  }
}