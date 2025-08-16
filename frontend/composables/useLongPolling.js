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
  const isAggressiveMode = ref(false)
  const currentLineUserId = ref(null)
  
  const { $api } = useNuxtApp()
  const route = useRoute()
  
  // 輪詢間隔配置
  const AGGRESSIVE_POLLING_INTERVAL = 300 // 300ms for chat page
  const NORMAL_POLLING_INTERVAL = 1000 // 1s for normal usage
  const ERROR_RETRY_INTERVAL = 5000 // 5s for error retry
  
  /**
   * 根據模式取得輪詢間隔
   */
  const getPollingInterval = () => {
    return isAggressiveMode.value ? AGGRESSIVE_POLLING_INTERVAL : NORMAL_POLLING_INTERVAL
  }
  
  /**
   * 開始長輪詢
   */
  const startPolling = (lineUserId = null, aggressive = false) => {
    if (isPolling.value) {
      return
    }
    
    isPolling.value = true
    isConnected.value = true
    isAggressiveMode.value = aggressive
    currentLineUserId.value = lineUserId
    lastUpdate.value = new Date().toISOString()
    
    console.log(`開始長輪詢 - 模式: ${aggressive ? '積極' : '正常'}, 間隔: ${getPollingInterval()}ms`)
    
    // 開始輪詢循環
    pollForUpdates(lineUserId)
  }
  
  /**
   * 開始積極輪詢（聊天室專用）
   */
  const startAggressivePolling = (lineUserId = null) => {
    startPolling(lineUserId, true)
  }
  
  /**
   * 停止長輪詢
   */
  const stopPolling = () => {
    console.log('停止長輪詢')
    isPolling.value = false
    isConnected.value = false
    isAggressiveMode.value = false
    currentLineUserId.value = null
    
    if (pollingInterval.value) {
      clearTimeout(pollingInterval.value)
      pollingInterval.value = null
    }
  }
  
  /**
   * 暫停輪詢（保持狀態）
   */
  const pausePolling = () => {
    console.log('暫停長輪詢')
    if (pollingInterval.value) {
      clearTimeout(pollingInterval.value)
      pollingInterval.value = null
    }
  }
  
  /**
   * 恢復輪詢
   */
  const resumePolling = () => {
    if (isPolling.value && !pollingInterval.value) {
      console.log('恢復長輪詢')
      pollForUpdates(currentLineUserId.value)
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
        pollingInterval.value = setTimeout(() => {
          pollForUpdates(lineUserId)
        }, getPollingInterval())
      }
      
    } catch (error) {
      console.error('Long polling error:', error)
      
      // 如果還在輪詢，等待更長時間後重試
      if (isPolling.value) {
        pollingInterval.value = setTimeout(() => {
          pollForUpdates(lineUserId)
        }, ERROR_RETRY_INTERVAL)
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
  
  // 頁面可見性檢測
  if (process.client) {
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        pausePolling()
      } else {
        resumePolling()
      }
    })
  }

  return {
    isPolling: readonly(isPolling),
    isConnected: readonly(isConnected),
    lastUpdate: readonly(lastUpdate),
    isAggressiveMode: readonly(isAggressiveMode),
    startPolling,
    startAggressivePolling,
    stopPolling,
    pausePolling,
    resumePolling,
    onUpdate,
    onAnyUpdate,
    offUpdate,
    cleanup
  }
}