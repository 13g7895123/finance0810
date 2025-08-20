import { ref, onUnmounted, watch } from 'vue'
import { useReconnectManager } from './useReconnectManager'
import { useNetworkStatus } from './useNetworkStatus'

export const useLongPollingWithReconnect = () => {
  // 引入重連管理器和網絡狀態
  const reconnectManager = useReconnectManager()
  const networkStatus = useNetworkStatus()
  
  // 狀態管理
  const isPolling = ref(false)
  const currentVersion = ref(0)
  const connectionStatus = ref('disconnected')
  const pollingStats = ref({
    totalRequests: 0,
    successfulRequests: 0,
    failedRequests: 0,
    lastSuccessTime: null,
    lastErrorTime: null
  })
  
  // 配置
  const config = {
    endpoint: '/api/chats/poll-updates',
    timeout: 25000,
    healthCheckInterval: 60000 // 每分鐘健康檢查
  }
  
  let abortController = null
  let healthCheckTimer = null
  let currentOptions = null
  
  /**
   * 執行單次輪詢請求
   */
  const executePollRequest = async () => {
    abortController = new AbortController()
    
    const params = new URLSearchParams({
      version: currentVersion.value,
      timeout: config.timeout / 1000
    })
    
    if (currentOptions?.lineUserId) {
      params.append('line_user_id', currentOptions.lineUserId)
    }
    
    pollingStats.value.totalRequests++
    
    try {
      const response = await fetch(`${config.endpoint}?${params}`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${getAuthToken()}`
        },
        signal: abortController.signal,
        // 添加超時控制
        ...createTimeoutSignal(config.timeout + 5000)
      })
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`)
      }
      
      const data = await response.json()
      
      // 更新統計
      pollingStats.value.successfulRequests++
      pollingStats.value.lastSuccessTime = Date.now()
      
      // 處理響應
      if (data.version) {
        currentVersion.value = data.version
      }
      
      if (data.data && currentOptions?.onUpdate) {
        await currentOptions.onUpdate(data.data)
      }
      
      return true
      
    } catch (error) {
      // 更新統計
      pollingStats.value.failedRequests++
      pollingStats.value.lastErrorTime = Date.now()
      
      // 錯誤分類處理
      if (error.name === 'AbortError') {
        console.log('Request aborted')
        return false
      }
      
      console.error('Poll request failed:', error)
      
      if (currentOptions?.onError) {
        currentOptions.onError(error)
      }
      
      // 判斷是否需要重連
      if (shouldReconnect(error)) {
        return false
      }
      
      throw error
    }
  }
  
  /**
   * 判斷是否應該重連
   */
  const shouldReconnect = (error) => {
    // 網絡錯誤、超時、5xx 錯誤需要重連
    const errorMessage = error.message.toLowerCase()
    return (
      errorMessage.includes('network') ||
      errorMessage.includes('timeout') ||
      errorMessage.includes('fetch') ||
      /http 5\d{2}/i.test(errorMessage)
    )
  }
  
  /**
   * 創建超時信號
   */
  const createTimeoutSignal = (timeout) => {
    const controller = new AbortController()
    setTimeout(() => controller.abort(), timeout)
    return { signal: controller.signal }
  }
  
  /**
   * 輪詢主循環
   */
  const pollLoop = async () => {
    while (isPolling.value) {
      try {
        connectionStatus.value = 'connected'
        const success = await executePollRequest()
        
        if (!success && isPolling.value) {
          // 請求失敗，啟動重連
          connectionStatus.value = 'reconnecting'
          const reconnected = await reconnectManager.startReconnect(
            () => executePollRequest(),
            { 
              autoRetry: true,
              onError: currentOptions?.onError 
            }
          )
          
          if (!reconnected) {
            // 重連失敗
            connectionStatus.value = 'error'
            stopPolling()
            break
          }
        }
        
        // 短暫延遲避免過於頻繁
        await sleep(100)
        
      } catch (error) {
        console.error('Poll loop error:', error)
        
        if (!isPolling.value) break
        
        // 嘗試重連
        connectionStatus.value = 'reconnecting'
        const reconnected = await reconnectManager.startReconnect(
          () => executePollRequest(),
          { 
            autoRetry: true,
            onError: currentOptions?.onError 
          }
        )
        
        if (!reconnected) {
          connectionStatus.value = 'error'
          stopPolling()
          break
        }
      }
    }
  }
  
  /**
   * 開始輪詢
   */
  const startPolling = async (options = {}) => {
    if (isPolling.value) {
      console.log('Already polling')
      return
    }
    
    currentOptions = options
    isPolling.value = true
    connectionStatus.value = 'connecting'
    
    // 重置重連管理器
    reconnectManager.resetReconnect()
    
    // 啟動健康檢查
    startHealthCheck()
    
    // 開始輪詢
    pollLoop()
  }
  
  /**
   * 停止輪詢
   */
  const stopPolling = () => {
    isPolling.value = false
    connectionStatus.value = 'disconnected'
    
    if (abortController) {
      abortController.abort()
      abortController = null
    }
    
    stopHealthCheck()
    reconnectManager.cancelReconnect()
  }
  
  /**
   * 重啟輪詢
   */
  const restartPolling = async (options) => {
    stopPolling()
    await sleep(500)
    await startPolling(options || currentOptions)
  }
  
  /**
   * 健康檢查
   */
  const startHealthCheck = () => {
    stopHealthCheck()
    
    healthCheckTimer = setInterval(() => {
      const stats = pollingStats.value
      const now = Date.now()
      
      // 如果超過2分鐘沒有成功請求，嘗試重連
      if (stats.lastSuccessTime && (now - stats.lastSuccessTime) > 120000) {
        console.warn('Health check failed, attempting reconnection...')
        restartPolling()
      }
    }, config.healthCheckInterval)
  }
  
  /**
   * 停止健康檢查
   */
  const stopHealthCheck = () => {
    if (healthCheckTimer) {
      clearInterval(healthCheckTimer)
      healthCheckTimer = null
    }
  }
  
  /**
   * 監聽網絡狀態變化
   */
  watch(() => networkStatus.isOnline.value, (online) => {
    if (online && connectionStatus.value === 'error') {
      console.log('Network restored, attempting to reconnect...')
      restartPolling()
    } else if (!online && isPolling.value) {
      console.log('Network lost, pausing polling...')
      connectionStatus.value = 'offline'
    }
  })
  
  /**
   * 獲取認證 Token
   */
  const getAuthToken = () => {
    return useCookie('auth-token').value || 
           localStorage.getItem('auth_token')
  }
  
  /**
   * 延遲函數
   */
  const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms))
  
  // 清理
  onUnmounted(() => {
    stopPolling()
  })
  
  return {
    // 狀態
    isPolling,
    currentVersion,
    connectionStatus,
    pollingStats,
    
    // 重連管理器狀態
    ...reconnectManager,
    
    // 網絡狀態
    isOnline: networkStatus.isOnline,
    
    // 方法
    startPolling,
    stopPolling,
    restartPolling
  }
}