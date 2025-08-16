/**
 * Long Polling Composable for Real-time Updates
 * 長輪詢實時更新組合函數
 */

export const useLongPolling = () => {
  const isPolling = ref(false)
  const isConnected = ref(false)
  const lastUpdate = ref(null)
  const pollingInterval = ref(null)
  const activeListeners = ref(new Map())
  
  const { $api } = useNuxtApp()
  
  /**
   * 開始長輪詢
   */
  const startPolling = (lineUserId = null) => {
    if (isPolling.value) {
      return
    }
    
    isPolling.value = true
    isConnected.value = true
    lastUpdate.value = new Date().toISOString()
    
    // 開始輪詢循環
    pollForUpdates(lineUserId)
  }
  
  /**
   * 停止長輪詢
   */
  const stopPolling = () => {
    isPolling.value = false
    isConnected.value = false
    
    if (pollingInterval.value) {
      clearTimeout(pollingInterval.value)
      pollingInterval.value = null
    }
  }
  
  /**
   * 執行輪詢請求
   */
  const pollForUpdates = async (lineUserId = null) => {
    if (!isPolling.value) {
      return
    }
    
    try {
      const params = {
        timeout: 30,
        last_update: lastUpdate.value
      }
      
      if (lineUserId) {
        params.line_user_id = lineUserId
      }
      
      const response = await $api('/api/chats/poll-updates', {
        params,
        timeout: 35000 // 稍微大於服務器超時時間
      })
      
      if (response.data && Array.isArray(response.data) && response.data.length > 0) {
        // 處理收到的更新
        response.data.forEach(update => {
          handleUpdate(update)
        })
      }
      
      // 更新最後更新時間
      if (response.timestamp) {
        lastUpdate.value = response.timestamp
      }
      
      // 如果還在輪詢，繼續下一次輪詢
      if (isPolling.value) {
        // 短暫延遲後繼續輪詢
        pollingInterval.value = setTimeout(() => {
          pollForUpdates(lineUserId)
        }, 1000)
      }
      
    } catch (error) {
      console.error('Long polling error:', error)
      
      // 如果還在輪詢，等待更長時間後重試
      if (isPolling.value) {
        pollingInterval.value = setTimeout(() => {
          pollForUpdates(lineUserId)
        }, 5000)
      }
    }
  }
  
  /**
   * 處理更新
   */
  const handleUpdate = (update) => {
    const { type } = update
    
    // 調用對應的監聽器
    if (activeListeners.value.has(type)) {
      const callbacks = activeListeners.value.get(type)
      callbacks.forEach(callback => {
        try {
          callback(update)
        } catch (error) {
          console.error(`Error in update callback for type ${type}:`, error)
        }
      })
    }
    
    // 調用通用監聽器
    if (activeListeners.value.has('*')) {
      const generalCallbacks = activeListeners.value.get('*')
      generalCallbacks.forEach(callback => {
        try {
          callback(update)
        } catch (error) {
          console.error('Error in general update callback:', error)
        }
      })
    }
  }
  
  /**
   * 監聽特定類型的更新
   */
  const onUpdate = (type, callback) => {
    if (!activeListeners.value.has(type)) {
      activeListeners.value.set(type, [])
    }
    activeListeners.value.get(type).push(callback)
  }
  
  /**
   * 監聽所有更新
   */
  const onAnyUpdate = (callback) => {
    onUpdate('*', callback)
  }
  
  /**
   * 移除監聽器
   */
  const offUpdate = (type, callback = null) => {
    if (callback) {
      // 移除特定回調
      if (activeListeners.value.has(type)) {
        const callbacks = activeListeners.value.get(type)
        const index = callbacks.indexOf(callback)
        if (index > -1) {
          callbacks.splice(index, 1)
        }
      }
    } else {
      // 移除該類型的所有回調
      activeListeners.value.delete(type)
    }
  }
  
  /**
   * 清理所有監聽器
   */
  const cleanup = () => {
    stopPolling()
    activeListeners.value.clear()
  }
  
  /**
   * 當組件銷毀時清理
   */
  onUnmounted(() => {
    cleanup()
  })
  
  return {
    isPolling: readonly(isPolling),
    isConnected: readonly(isConnected),
    lastUpdate: readonly(lastUpdate),
    startPolling,
    stopPolling,
    onUpdate,
    onAnyUpdate,
    offUpdate,
    cleanup
  }
}