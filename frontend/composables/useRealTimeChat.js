/**
 * Real-time Chat Composable
 * 實時聊天功能封裝 (使用長輪詢)
 */

export const useRealTimeChat = () => {
  const { 
    startPolling, 
    stopPolling, 
    isConnected, 
    onUpdate, 
    onAnyUpdate,
    offUpdate,
    cleanup: cleanupPolling
  } = useLongPolling()
  
  const activeRooms = ref(new Set())
  const messageCallbacks = ref(new Map())
  const conversationUpdates = ref(new Map())
  const pollingInstances = ref(new Map())
  
  /**
   * 初始化實時聊天
   */
  const initializeRealTimeChat = () => {
    // 開始對話列表輪詢
    startPolling()
    
    // 監聽新訊息
    onUpdate('new_messages', (data) => {
      handleNewMessage(data)
    })
    
    // 監聽對話列表更新
    onUpdate('conversation_list_update', (data) => {
      handleConversationUpdate(data)
    })
  }
  
  /**
   * 處理新訊息
   */
  const handleNewMessage = (data) => {
    const { line_user_id, messages } = data
    
    // 如果有這個房間的回調函數，執行它
    const callback = messageCallbacks.value.get(line_user_id)
    if (callback) {
      messages.forEach(message => {
        callback({
          type: 'new_message',
          message: {
            id: message.id,
            content: message.content,
            timestamp: message.timestamp,
            is_from_customer: message.is_from_customer,
            status: message.status,
            message_type: message.message_type
          }
        })
      })
    }
    
    // 更新對話列表
    if (conversationUpdates.value.has(line_user_id)) {
      const updateCallback = conversationUpdates.value.get(line_user_id)
      const latestMessage = messages[messages.length - 1]
      updateCallback({
        type: 'new_message',
        lastMessage: latestMessage.content,
        timestamp: latestMessage.timestamp,
        unreadCount: messages.filter(m => m.is_from_customer && m.status === 'unread').length
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