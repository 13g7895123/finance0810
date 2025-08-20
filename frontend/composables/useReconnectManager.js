import { ref, computed } from 'vue'

export const useReconnectManager = () => {
  // 狀態管理
  const reconnectAttempts = ref(0)
  const maxReconnectAttempts = ref(5)
  const isReconnecting = ref(false)
  const lastReconnectTime = ref(null)
  const nextReconnectTime = ref(null)
  const reconnectTimer = ref(null)
  
  // 重連延遲配置（指數退避）
  const baseDelay = 1000 // 基礎延遲 1 秒
  const maxDelay = 30000 // 最大延遲 30 秒
  const backoffMultiplier = 2 // 退避倍數
  
  // 計算下次重連延遲
  const calculateDelay = (attempt) => {
    const delay = Math.min(
      baseDelay * Math.pow(backoffMultiplier, attempt),
      maxDelay
    )
    // 添加隨機抖動（±25%）避免雷同效應
    const jitter = delay * 0.25
    return delay + (Math.random() * jitter * 2 - jitter)
  }
  
  // 計算屬性
  const canReconnect = computed(() => 
    reconnectAttempts.value < maxReconnectAttempts.value
  )
  
  const reconnectProgress = computed(() => 
    (reconnectAttempts.value / maxReconnectAttempts.value) * 100
  )
  
  const reconnectMessage = computed(() => {
    if (!isReconnecting.value) return ''
    
    if (!canReconnect.value) {
      return '已達到最大重連次數，請手動重新連接'
    }
    
    const remaining = maxReconnectAttempts.value - reconnectAttempts.value
    return `正在重新連接... (${reconnectAttempts.value + 1}/${maxReconnectAttempts.value})`
  })
  
  /**
   * 開始重連流程
   */
  const startReconnect = async (connectFunction, options = {}) => {
    if (!canReconnect.value) {
      console.error('Maximum reconnection attempts reached')
      return false
    }
    
    if (isReconnecting.value) {
      console.log('Already reconnecting...')
      return false
    }
    
    isReconnecting.value = true
    reconnectAttempts.value++
    
    const delay = calculateDelay(reconnectAttempts.value - 1)
    console.log(`Reconnecting in ${Math.round(delay)}ms (attempt ${reconnectAttempts.value})`)
    
    // 設置下次重連時間
    nextReconnectTime.value = Date.now() + delay
    
    // 清除舊的定時器
    if (reconnectTimer.value) {
      clearTimeout(reconnectTimer.value)
    }
    
    return new Promise((resolve) => {
      reconnectTimer.value = setTimeout(async () => {
        lastReconnectTime.value = Date.now()
        
        try {
          // 執行連接函數
          const result = await connectFunction()
          
          if (result) {
            // 連接成功，重置狀態
            resetReconnect()
            resolve(true)
          } else {
            // 連接失敗
            isReconnecting.value = false
            
            // 如果還能重試，繼續重連
            if (canReconnect.value && options.autoRetry !== false) {
              resolve(await startReconnect(connectFunction, options))
            } else {
              resolve(false)
            }
          }
        } catch (error) {
          console.error('Reconnection error:', error)
          isReconnecting.value = false
          
          // 錯誤處理回調
          if (options.onError) {
            options.onError(error)
          }
          
          // 如果還能重試，繼續重連
          if (canReconnect.value && options.autoRetry !== false) {
            resolve(await startReconnect(connectFunction, options))
          } else {
            resolve(false)
          }
        }
      }, delay)
    })
  }
  
  /**
   * 重置重連狀態
   */
  const resetReconnect = () => {
    reconnectAttempts.value = 0
    isReconnecting.value = false
    lastReconnectTime.value = null
    nextReconnectTime.value = null
    
    if (reconnectTimer.value) {
      clearTimeout(reconnectTimer.value)
      reconnectTimer.value = null
    }
  }
  
  /**
   * 取消重連
   */
  const cancelReconnect = () => {
    console.log('Cancelling reconnection...')
    
    if (reconnectTimer.value) {
      clearTimeout(reconnectTimer.value)
      reconnectTimer.value = null
    }
    
    isReconnecting.value = false
    nextReconnectTime.value = null
  }
  
  /**
   * 手動重連
   */
  const manualReconnect = async (connectFunction) => {
    console.log('Manual reconnection initiated')
    resetReconnect()
    return await startReconnect(connectFunction, { autoRetry: false })
  }
  
  /**
   * 設置最大重試次數
   */
  const setMaxAttempts = (max) => {
    maxReconnectAttempts.value = Math.max(1, max)
  }
  
  /**
   * 獲取重連統計
   */
  const getReconnectStats = () => ({
    attempts: reconnectAttempts.value,
    maxAttempts: maxReconnectAttempts.value,
    isReconnecting: isReconnecting.value,
    canReconnect: canReconnect.value,
    lastReconnectTime: lastReconnectTime.value,
    nextReconnectTime: nextReconnectTime.value,
    progress: reconnectProgress.value
  })
  
  return {
    // 狀態
    reconnectAttempts,
    maxReconnectAttempts,
    isReconnecting,
    canReconnect,
    reconnectProgress,
    reconnectMessage,
    
    // 方法
    startReconnect,
    resetReconnect,
    cancelReconnect,
    manualReconnect,
    setMaxAttempts,
    getReconnectStats
  }
}