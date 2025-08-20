export const useChat = () => {
  const authStore = useAuthStore()
  const { get, post } = useApi()
  
  // API 基礎呼叫 - 只使用真實API
  const apiCall = async (endpoint, options = {}) => {
    if (!authStore.user?.token) {
      throw new Error('Authentication required for chat functions')
    }
    
    let result
    if (options.method === 'POST') {
      const { data, error } = await post(endpoint, options.body || {})
      if (error) throw error
      result = data
    } else {
      const { data, error } = await get(endpoint, options.query || {})
      if (error) throw error
      result = data
    }
    
    return result
  }


  // 獲取對話列表
  const getConversations = async (page = 1) => {
    return await apiCall('/chats', {
      query: { page }
    })
  }

  // 獲取特定用戶的對話
  const getConversation = async (userId, page = 1) => {
    return await apiCall(`/chats/${userId}`, {
      query: { page }
    })
  }

  // 回覆訊息
  const replyMessage = async (userId, message) => {
    return await apiCall(`/chats/${userId}/reply`, {
      method: 'POST',
      body: { message }
    })
  }

  // 獲取未讀訊息數量
  const getUnreadCount = async () => {
    return await apiCall('/chats/unread/count')
  }

  // 標記訊息為已讀
  const markAsRead = async (userId) => {
    return await apiCall(`/chats/${userId}/read`, {
      method: 'POST'
    })
  }

  // 刪除對話
  const deleteConversation = async (userId) => {
    return await apiCall(`/chats/${userId}`, {
      method: 'DELETE'
    })
  }

  // 獲取對話統計
  const getChatStats = async () => {
    return await apiCall('/chats/stats')
  }

  // 搜尋對話
  const searchConversations = async (query) => {
    console.log('useChat: searching for', query) // Debug log
    const result = await apiCall('/chats/search', {
      query: { q: query }
    })
    console.log('useChat: search result', result) // Debug log
    return result
  }

  return {
    getConversations,
    getConversation,
    replyMessage,
    getUnreadCount,
    markAsRead,
    deleteConversation,
    getChatStats,
    searchConversations
  }
}