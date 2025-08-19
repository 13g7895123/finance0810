/**
 * Long Polling Composable for Real-time Updates
 * 真正的長輪詢實時更新組合函數（替換原有的 setInterval 方式）
 */

export const useLongPolling = () => {
  // 狀態管理
  const isPolling = ref(false)
  const currentVersion = ref(0)
  const connectionStatus = ref('disconnected') // disconnected, connecting, connected, error
  const lastError = ref(null)
  const retryCount = ref(0)
  const activeListeners = ref(new Map())
  const currentLineUserId = ref(null)
  
  const { $api } = useNuxtApp()
  
  // 配置參數
  const maxRetries = 5
  const config = {
    timeout: 25000, // 25 秒超時
    retryDelays: [1000, 2000, 4000, 8000, 16000], // 指數退避
    endpoint: '/api/chats/poll-updates'
  }
  
  // AbortController 用於取消請求
  let abortController = null
  
  /**
   * 開始 Long Polling
   */
  const startPolling = async (options = {}) => {
    if (isPolling.value) {
      console.log('Long polling already active')
      return
    }
    
    isPolling.value = true
    connectionStatus.value = 'connecting'
    lastError.value = null
    currentLineUserId.value = options.lineUserId || null
    
    // 合併選項
    const pollOptions = {
      lineUserId: options.lineUserId || null,
      onUpdate: options.onUpdate || null,
      onError: options.onError || null
    }
    
    console.log('開始真正的 Long Polling...', { lineUserId: pollOptions.lineUserId })
    
    // 開始輪詢循環
    await pollLoop(pollOptions)
  }
  
  /**
   * 開始積極輪詢（聊天室專用）- 向下兼容
   */
  const startAggressivePolling = (lineUserId = null) => {
    return startPolling({ 
      lineUserId,
      onUpdate: (updates) => handleUpdates(updates),
      onError: (error) => console.error('Polling error:', error)
    })
  }
  
  /**
   * Long Polling 主循環
   */
  const pollLoop = async (options) => {
    while (isPolling.value) {
      try {
        // 創建新的 AbortController
        abortController = new AbortController()
        
        // 發送 Long Polling 請求
        const response = await executePollRequest(options)
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`)
        }
        
        const data = await response.json()
        
        // 更新連接狀態
        connectionStatus.value = 'connected'
        retryCount.value = 0 // 重置重試計數
        
        // 處理響應數據
        if (data.success) {
          // 更新版本號
          if (data.version) {
            currentVersion.value = data.version
          }
          
          // 如果有數據更新，處理更新
          if (data.data && data.data.length > 0) {
            await handleUpdates(data.data)
            
            // 調用外部回調
            if (options.onUpdate) {
              await options.onUpdate(data.data)
            }
          }
          
          // 如果是超時響應，立即發起下一次請求
          if (data.timeout) {
            console.debug('Long polling timeout, reconnecting...')
            continue
          }
        }
        
        // 短暫延遲後繼續（避免過於頻繁的請求）
        await sleep(100)
        
      } catch (error) {
        // 處理錯誤
        await handlePollingError(error, options)
      }
    }
    
    connectionStatus.value = 'disconnected'
  }

  /**
   * 停止長輪詢
   */
  const stopPolling = () => {
    console.log('停止長輪詢')
    isPolling.value = false
    
    // 取消當前請求
    if (abortController) {
      abortController.abort()
      abortController = null
    }
    
    connectionStatus.value = 'disconnected'
    lastError.value = null
    retryCount.value = 0
  }
  
  /**
   * 暫停輪詢（保持狀態）- 向下兼容
   */
  const pausePolling = () => {
    console.log('暫停長輪詢')
    stopPolling()
  }
  
  /**
   * 恢復輪詢 - 向下兼容
   */
  const resumePolling = () => {
    if (!isPolling.value) {
      console.log('恢復長輪詢')
      startPolling({ 
        lineUserId: currentLineUserId.value,
        onUpdate: (updates) => handleUpdates(updates)
      })
    }
  }
  
  /**
   * 執行單次 Poll 請求
   */
  const executePollRequest = async (options) => {
    const params = new URLSearchParams({
      version: currentVersion.value,
      timeout: config.timeout / 1000 // 轉換為秒
    })
    
    if (options.lineUserId) {
      params.append('line_user_id', options.lineUserId)
    }
    
    // 設置請求超時
    const timeoutId = setTimeout(() => {
      if (abortController) {
        abortController.abort()
      }
    }, config.timeout + 5000) // 額外 5 秒緩衝
    
    try {
      const response = await fetch(`${config.endpoint}?${params}`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${getAuthToken()}`
        },
        signal: abortController.signal
      })
      
      clearTimeout(timeoutId)
      return response
      
    } catch (error) {
      clearTimeout(timeoutId)
      throw error
    }
  }
  
  /**
   * 處理輪詢錯誤
   */
  const handlePollingError = async (error, options) => {
    console.error('Long polling error:', error)
    lastError.value = error.message
    
    // 如果是主動停止，不重試
    if (!isPolling.value || error.name === 'AbortError') {
      return
    }
    
    connectionStatus.value = 'error'
    
    // 調用錯誤回調
    if (options.onError) {
      options.onError(error)
    }
    
    // 重試邏輯
    if (retryCount.value < maxRetries) {
      const delay = config.retryDelays[retryCount.value] || 30000
      console.log(`Retrying in ${delay}ms... (attempt ${retryCount.value + 1}/${maxRetries})`)
      
      retryCount.value++
      await sleep(delay)
      
      // 如果仍在輪詢狀態，繼續重試
      if (isPolling.value) {
        connectionStatus.value = 'connecting'
      }
    } else {
      // 達到最大重試次數，停止輪詢
      console.error('Max retries reached, stopping long polling')
      stopPolling()
    }
  }
  
  /**
   * 處理更新（批量處理）
   */
  const handleUpdates = (updates) => {
    if (!Array.isArray(updates)) {
      updates = [updates]
    }
    
    updates.forEach(update => {
      if (update && typeof update === 'object') {
        handleSingleUpdate(update)
      } else {
        console.warn('Invalid update object:', update)
      }
    })
  }

  /**
   * 處理單個更新
   */
  const handleSingleUpdate = (update) => {
    const { type } = update
    
    // 調用對應的監聽器
    if (activeListeners.value.has(type)) {
      const callbacks = activeListeners.value.get(type)
      if (Array.isArray(callbacks)) {
        callbacks.forEach(callback => {
          if (typeof callback === 'function') {
            try {
              callback(update)
            } catch (error) {
              console.error(`Error in update callback for type ${type}:`, error)
            }
          } else {
            console.error(`Invalid callback for type ${type}:`, typeof callback, callback)
          }
        })
      } else {
        console.error(`Callbacks for type ${type} is not an array:`, typeof callbacks, callbacks)
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
              console.error('Error in general update callback:', error)
            }
          } else {
            console.error('Invalid general callback:', typeof callback, callback)
          }
        })
      } else {
        console.error('General callbacks is not an array:', typeof generalCallbacks, generalCallbacks)
      }
    }
  }
  
  /**
   * 監聽特定類型的更新
   */
  const onUpdate = (type, callback) => {
    if (typeof callback !== 'function') {
      console.error('onUpdate callback must be a function:', typeof callback, callback)
      return
    }
    
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
   * 重啟 Long Polling
   */
  const restartPolling = async (options = {}) => {
    stopPolling()
    await sleep(500) // 短暫延遲
    await startPolling(options)
  }
  
  /**
   * 獲取認證 Token
   */
  const getAuthToken = () => {
    // 從 Cookie 或 localStorage 獲取 token
    const token = useCookie('auth-token').value || 
                  localStorage.getItem('auth_token')
    return token
  }
  
  /**
   * 延遲函數
   */
  const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms))

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
    // 狀態 - 新的 Long Polling API
    isPolling: readonly(isPolling),
    currentVersion: readonly(currentVersion),
    connectionStatus: readonly(connectionStatus),
    lastError: readonly(lastError),
    retryCount: readonly(retryCount),
    
    // 向下兼容的狀態
    isConnected: computed(() => connectionStatus.value === 'connected'),
    lastUpdate: computed(() => new Date().toISOString()),
    isAggressiveMode: computed(() => false), // 不再區分模式
    
    // 方法 - 新的 Long Polling API
    startPolling,
    stopPolling,
    restartPolling,
    
    // 向下兼容的方法
    startAggressivePolling,
    pausePolling,
    resumePolling,
    onUpdate,
    onAnyUpdate,
    offUpdate,
    cleanup
  }
}