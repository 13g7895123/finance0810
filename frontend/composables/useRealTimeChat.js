/**
 * Real-time Chat Composable
 * 實時聊天功能封裝
 */

export const useRealTimeChat = () => {
  const { 
    connect, 
    disconnect, 
    isConnected, 
    onMessage, 
    joinChatRoom, 
    leaveChatRoom,
    sendChatMessage 
  } = useWebSocket()
  
  const activeRooms = ref(new Set())
  const messageCallbacks = ref(new Map())
  const conversationUpdates = ref(new Map())
  
  /**
   * 初始化實時聊天
   */
  const initializeRealTimeChat = () => {
    connect()
    
    // 監聽新訊息
    onMessage('new_message', (data) => {
      handleNewMessage(data)
    })
    
    // 監聽訊息狀態更新
    onMessage('message_status', (data) => {
      handleMessageStatusUpdate(data)
    })
    
    // 監聽對話列表更新
    onMessage('conversation_update', (data) => {
      handleConversationUpdate(data)
    })
    
    // 監聽用戶狀態變更
    onMessage('user_status', (data) => {
      handleUserStatusUpdate(data)
    })
  }
  
  /**
   * 處理新訊息
   */
  const handleNewMessage = (data) => {
    const { room, message } = data
    
    // 如果有這個房間的回調函數，執行它
    const callback = messageCallbacks.value.get(room)
    if (callback) {
      callback({
        type: 'new_message',
        message: message
      })
    }
    
    // 更新對話列表
    if (conversationUpdates.value.has(room)) {
      const updateCallback = conversationUpdates.value.get(room)
      updateCallback({
        type: 'new_message',
        lastMessage: message.content,
        timestamp: message.timestamp,
        unreadCount: message.unreadCount || 0
      })
    }
  }
  
  /**
   * 處理訊息狀態更新
   */
  const handleMessageStatusUpdate = (data) => {
    const { room, messageId, status } = data
    
    const callback = messageCallbacks.value.get(room)
    if (callback) {
      callback({
        type: 'message_status',
        messageId: messageId,
        status: status
      })
    }
  }
  
  /**
   * 處理對話更新
   */
  const handleConversationUpdate = (data) => {
    const { room, update } = data
    
    if (conversationUpdates.value.has(room)) {
      const updateCallback = conversationUpdates.value.get(room)
      updateCallback(update)
    }
  }
  
  /**
   * 處理用戶狀態更新
   */
  const handleUserStatusUpdate = (data) => {
    // 廣播用戶狀態變更給所有房間
    messageCallbacks.value.forEach((callback) => {
      callback({
        type: 'user_status',
        userId: data.userId,
        status: data.status,
        online: data.online
      })
    })
  }
  
  /**
   * 加入聊天房間
   */
  const joinRoom = (roomId, messageCallback) => {
    if (!isConnected.value) {
      console.warn('WebSocket not connected, cannot join room')
      return false
    }
    
    const success = joinChatRoom(roomId)
    
    if (success) {
      activeRooms.value.add(roomId)
      messageCallbacks.value.set(roomId, messageCallback)
    }
    
    return success
  }
  
  /**
   * 離開聊天房間
   */
  const leaveRoom = (roomId) => {
    if (!isConnected.value) {
      return false
    }
    
    const success = leaveChatRoom(roomId)
    
    if (success) {
      activeRooms.value.delete(roomId)
      messageCallbacks.value.delete(roomId)
      conversationUpdates.value.delete(roomId)
    }
    
    return success
  }
  
  /**
   * 發送訊息
   */
  const sendMessage = (roomId, message) => {
    if (!isConnected.value) {
      console.warn('WebSocket not connected, cannot send message')
      return false
    }
    
    return sendChatMessage(roomId, message)
  }
  
  /**
   * 註冊對話列表更新回調
   */
  const onConversationUpdate = (roomId, callback) => {
    conversationUpdates.value.set(roomId, callback)
  }
  
  /**
   * 取消註冊對話列表更新回調
   */
  const offConversationUpdate = (roomId) => {
    conversationUpdates.value.delete(roomId)
  }
  
  /**
   * 標記訊息為已讀
   */
  const markAsRead = (roomId, messageIds) => {
    if (!isConnected.value) {
      return false
    }
    
    return sendMessage(roomId, {
      type: 'mark_read',
      messageIds: Array.isArray(messageIds) ? messageIds : [messageIds]
    })
  }
  
  /**
   * 獲取房間在線用戶
   */
  const getRoomUsers = (roomId) => {
    if (!isConnected.value) {
      return false
    }
    
    return sendMessage(roomId, {
      type: 'get_room_users'
    })
  }
  
  /**
   * 清理所有連接和回調
   */
  const cleanup = () => {
    // 離開所有房間
    activeRooms.value.forEach(roomId => {
      leaveChatRoom(roomId)
    })
    
    // 清理回調
    activeRooms.value.clear()
    messageCallbacks.value.clear()
    conversationUpdates.value.clear()
    
    // 斷開WebSocket連接
    disconnect()
  }
  
  /**
   * 當組件銷毀時清理
   */
  onUnmounted(() => {
    cleanup()
  })
  
  return {
    isConnected: readonly(isConnected),
    activeRooms: readonly(activeRooms),
    initializeRealTimeChat,
    joinRoom,
    leaveRoom,
    sendMessage,
    onConversationUpdate,
    offConversationUpdate,
    markAsRead,
    getRoomUsers,
    cleanup
  }
}