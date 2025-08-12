/**
 * LINE Integration Composable
 * 處理 LINE 整合相關 API 調用
 */

export const useLineIntegration = () => {
  const { get, post } = useApi()

  /**
   * 取得 LINE 整合設定
   */
  const getSettings = async () => {
    const response = await get('/line-integration/settings')
    return response
  }

  /**
   * 更新 LINE 整合設定
   */
  const updateSettings = async (settings) => {
    const response = await post('/line-integration/settings', settings)
    return response
  }

  /**
   * 測試 LINE 連線
   */
  const testConnection = async () => {
    const response = await post('/line-integration/test-connection')
    return response
  }

  /**
   * 取得 LINE Bot 資訊
   */
  const getBotInfo = async () => {
    const response = await get('/line-integration/bot-info')
    return response
  }

  /**
   * 取得 LINE 整合統計資料
   */
  const getStats = async () => {
    const response = await get('/line-integration/stats')
    return response
  }

  /**
   * 取得最近的 LINE 對話
   */
  const getRecentConversations = async (limit = 10) => {
    const response = await get('/line-integration/recent-conversations', { limit })
    return response
  }

  /**
   * 發送 LINE 訊息
   */
  const sendMessage = async (lineUserId, message) => {
    const response = await post('/line-integration/send-message', {
      line_user_id: lineUserId,
      message: message
    })
    return response
  }

  return {
    getSettings,
    updateSettings,
    testConnection,
    getBotInfo,
    getStats,
    getRecentConversations,
    sendMessage
  }
}