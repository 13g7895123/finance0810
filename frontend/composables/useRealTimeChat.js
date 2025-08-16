/**
 * Real-time Chat Composable
 * 實時聊天功能封裝 (使用 WebSocket)
 */

export const useRealTimeChat = () => {
  const { 
    connect, 
    disconnect, 
    isConnected,
    isConnecting,
    joinChatRoom,
    joinAdminChannel,
    leaveChatRoom,
    leaveAdminChannel,
    sendChatMessage,
    cleanup: cleanupWebSocket
  } = useWebSocket()
  
  const activeRooms = ref(new Set())
  const messageCallbacks = ref(new Map())
  const conversationUpdates = ref(new Map())
  const pollingInstances = ref(new Map())
  
  /**
   * 初始化實時聊天
   */
  const initializeRealTimeChat = async () => {
    // 連接 WebSocket
    await connect()
    
    // 訂閱管理員頻道以接收所有聊天室更新
    joinAdminChannel((event) => {
      handleWebSocketMessage(event)
    })
  }
  
  /**
   * 處理 WebSocket 訊息
   */
  const handleWebSocketMessage = (event) => {
    if (event.type === 'new_message' && event.data) {
      const { line_user_id } = event.data
      
      // 如果有這個房間的回調函數，執行它
      const callback = messageCallbacks.value.get(line_user_id)
      if (callback) {
        callback({
          type: 'new_message',
          message: {
            id: event.data.id,
            content: event.data.content,
            timestamp: event.data.timestamp,
            is_from_customer: event.data.is_from_customer,
            status: event.data.status,
            message_type: event.data.message_type
          }
        })
      }
      
      // 更新對話列表
      if (conversationUpdates.value.has(line_user_id)) {
        const updateCallback = conversationUpdates.value.get(line_user_id)
        updateCallback({
          type: 'new_message',
          lastMessage: event.data.content,
          timestamp: event.data.timestamp,
          unreadCount: event.data.is_from_customer && event.data.status === 'unread' ? 1 : 0
        })
      }
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
    const { updated_conversations } = data
    
    // 通知所有對話需要重新載入
    conversationUpdates.value.forEach((callback, roomId) => {
      if (updated_conversations.includes(roomId)) {
        callback({
          type: 'conversation_update',
          needs_reload: true
        })
      }
    })
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
  const joinRoom = (lineUserId, messageCallback) => {
    if (!isConnected.value) {
      console.warn('WebSocket not connected, cannot join room')
      return false
    }
    
    try {
      const channel = joinChatRoom(lineUserId, (event) => {
        if (messageCallback) {
          messageCallback(event)
        }
        handleWebSocketMessage(event)
      })
      
      if (channel) {
        activeRooms.value.add(lineUserId)
        messageCallbacks.value.set(lineUserId, messageCallback)
        return true
      }
      
      return false
    } catch (error) {
      console.error('Failed to join chat room:', error)
      return false
    }
  }
  
  /**
   * 離開聊天房間
   */
  const leaveRoom = (lineUserId) => {
    if (!isConnected.value) {
      return false
    }
    
    try {
      leaveChatRoom(lineUserId)
      activeRooms.value.delete(lineUserId)
      messageCallbacks.value.delete(lineUserId)
      conversationUpdates.value.delete(lineUserId)
      return true
    } catch (error) {
      console.error('Failed to leave chat room:', error)
      return false
    }
  }
  
  /**
   * 發送訊息
   */
  const sendMessage = async (lineUserId, message) => {
    if (!isConnected.value) {
      console.warn('WebSocket not connected, cannot send message')
      return false
    }
    
    try {
      return await sendChatMessage(lineUserId, message)
    } catch (error) {
      console.error('Failed to send message:', error)
      return false
    }
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
    activeRooms.value.forEach(lineUserId => {
      leaveChatRoom(lineUserId)
    })
    
    // 離開管理員頻道
    leaveAdminChannel()
    
    // 清理回調
    activeRooms.value.clear()
    messageCallbacks.value.clear()
    conversationUpdates.value.clear()
    
    // 斷開WebSocket連接
    cleanupWebSocket()
  }
  
  /**
   * 當組件銷毀時清理
   */
  onUnmounted(() => {
    cleanup()
  })
  
  return {
    isConnected: readonly(isConnected),
    isConnecting: readonly(isConnecting),
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