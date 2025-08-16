/**
 * High Frequency API Polling Composable for Real-time Chat Updates
 * 高頻API輪詢實時聊天更新組合函數
 */

export const useHighFrequencyPolling = () => {
  const isPolling = ref(false)
  const isConnected = ref(false)
  const lastUpdate = ref(null)
  const pollingInterval = ref(null)
  const activeListeners = ref(new Map())
  const isAggressiveMode = ref(false)
  const currentLineUserId = ref(null)
  
  const { $api } = useNuxtApp()
  
  // 高頻輪詢間隔配置 (0.5秒實現即時性)
  const POLLING_INTERVAL = 500 // 500ms for real-time experience
  const ERROR_RETRY_INTERVAL = 2000 // 2s for error retry
  
  /**
   * 開始高頻輪詢
   */
  const startPolling = (lineUserId = null, aggressive = false) => {
    if (isPolling.value) {
      console.log('輪詢已在運行中，跳過啟動')
      return
    }
    
    isPolling.value = true
    isConnected.value = true
    isAggressiveMode.value = aggressive
    currentLineUserId.value = lineUserId
    lastUpdate.value = new Date().toISOString()
    
    console.log(`開始高頻API輪詢 - 間隔: ${POLLING_INTERVAL}ms`)
    
    // 開始輪詢循環
    pollForUpdates()
  }
  
  /**
   * 開始積極輪詢（聊天室專用）
   */
  const startAggressivePolling = (lineUserId = null) => {
    console.log('啟動積極模式高頻輪詢')
    startPolling(lineUserId, true)
  }
  
  /**
   * 停止輪詢
   */
  const stopPolling = () => {
    console.log('停止高頻API輪詢')
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
    console.log('暫停高頻輪詢')
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
      console.log('恢復高頻輪詢')
      pollForUpdates()
    }
  }
  
  /**
   * 執行API輪詢請求
   */
  const pollForUpdates = async () => {
    if (!isPolling.value) {
      return
    }
    
    try {
      // 建構API參數
      const params = {
        last_update: lastUpdate.value
      }
      
      if (currentLineUserId.value) {
        params.line_user_id = currentLineUserId.value
      }
      
      // 發送API請求
      const response = await $api('/api/chats/poll-updates', {
        params,
        timeout: 3000 // 3秒超時
      })
      
      // 處理響應數據
      if (response?.data && Array.isArray(response.data) && response.data.length > 0) {
        console.log(`收到 ${response.data.length} 個更新`)
        
        // 處理每個更新
        response.data.forEach(update => {
          if (update && typeof update === 'object') {
            handleUpdate(update)
          } else {
            console.warn('無效的更新對象:', update)
          }
        })
      }
      
      // 更新最後更新時間
      if (response?.timestamp) {
        lastUpdate.value = response.timestamp
      }
      
      // 繼續下一次輪詢
      if (isPolling.value) {
        pollingInterval.value = setTimeout(pollForUpdates, POLLING_INTERVAL)
      }
      
    } catch (error) {
      console.error('高頻API輪詢錯誤:', error)
      
      // 錯誤重試機制
      if (isPolling.value) {
        pollingInterval.value = setTimeout(pollForUpdates, ERROR_RETRY_INTERVAL)
      }
    }
  }
  
  /**
   * 處理更新數據
   */
  const handleUpdate = (update) => {
    const { type } = update
    
    // 調用特定類型的監聽器
    if (activeListeners.value.has(type)) {
      const callbacks = activeListeners.value.get(type)
      if (Array.isArray(callbacks)) {
        callbacks.forEach(callback => {
          if (typeof callback === 'function') {
            try {
              callback(update)
            } catch (error) {
              console.error(`處理 ${type} 更新時發生錯誤:`, error)
            }
          }
        })
      }
    }
    
    // 調用通用監聽器
    if (activeListeners.value.has('*')) {
      const generalCallbacks = activeListeners.value.get('*')
      if (Array.isArray(generalCallbacks)) {
        generalCallbacks.forEach(callback => {
          if (typeof callback === 'function') {
            try {
              callback(update)
            } catch (error) {
              console.error('處理通用更新時發生錯誤:', error)
            }
          }
        })
      }
    }
  }
  
  /**
   * 監聽特定類型的更新
   */
  const onUpdate = (type, callback) => {
    if (typeof callback !== 'function') {
      console.error('onUpdate 回調必須是函數:', typeof callback, callback)
      return
    }
    
    if (!activeListeners.value.has(type)) {
      activeListeners.value.set(type, [])
    }
    activeListeners.value.get(type).push(callback)
    
    console.log(`註冊 ${type} 類型的更新監聽器`)
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
    console.log('清理高頻輪詢資源')
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
        console.log('頁面隱藏，暫停輪詢')
        pausePolling()
      } else {
        console.log('頁面可見，恢復輪詢')
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