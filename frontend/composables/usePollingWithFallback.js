import { ref, onUnmounted, watch } from 'vue'
import { useFallbackStrategy } from './useFallbackStrategy'
import Swal from 'sweetalert2'

export const usePollingWithFallback = () => {
  const fallbackStrategy = useFallbackStrategy()
  
  // 狀態管理
  const isPolling = ref(false)
  const currentVersion = ref(0)
  const lastUpdateTime = ref(null)
  
  // 輪詢控制
  let longPollingController = null
  let fallbackInterval = null
  let currentOptions = null
  
  /**
   * Long Polling 實現
   */
  const executeLongPolling = async () => {
    longPollingController = new AbortController()
    
    try {
      const response = await fetch('/api/chats/poll-updates', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${getAuthToken()}`
        },
        signal: longPollingController.signal,
        // Long Polling 特有的長超時
        timeout: 25000
      })
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`)
      }
      
      const data = await response.json()
      
      // 成功回調
      fallbackStrategy.onSuccess()
      lastUpdateTime.value = Date.now()
      
      // 處理數據
      if (data.version) {
        currentVersion.value = data.version
      }
      
      if (data.data && currentOptions?.onUpdate) {
        await currentOptions.onUpdate(data.data)
      }
      
      return true
      
    } catch (error) {
      // 錯誤處理
      const newStrategy = fallbackStrategy.onError(error)
      
      if (newStrategy === 'disabled') {
        stopPolling()
        showAuthError()
        return false
      }
      
      if (newStrategy === 'fallback') {
        switchToFallback()
        return false
      }
      
      throw error
    }
  }
  
  /**
   * 傳統輪詢實現
   */
  const executeFallbackPolling = async () => {
    try {
      const response = await fetch('/api/chats', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${getAuthToken()}`
        },
        // 傳統輪詢使用較短超時
        timeout: 5000
      })
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`)
      }
      
      const data = await response.json()
      
      // 成功回調
      fallbackStrategy.onSuccess()
      lastUpdateTime.value = Date.now()
      
      // 處理數據
      if (data.data && currentOptions?.onUpdate) {
        await currentOptions.onUpdate(data.data)
      }
      
      // 嘗試恢復 Long Polling
      if (fallbackStrategy.consecutiveErrors.value === 0) {
        attemptRecovery()
      }
      
      return true
      
    } catch (error) {
      // 錯誤處理
      fallbackStrategy.onError(error)
      
      if (currentOptions?.onError) {
        currentOptions.onError(error)
      }
      
      return false
    }
  }
  
  /**
   * Long Polling 循環
   */
  const longPollingLoop = async () => {
    while (isPolling.value && fallbackStrategy.currentStrategy.value === 'longPolling') {
      try {
        await executeLongPolling()
        
        // 短暫延遲
        await sleep(100)
        
      } catch (error) {
        console.error('Long polling error:', error)
        
        // 如果策略改變，退出循環
        if (fallbackStrategy.currentStrategy.value !== 'longPolling') {
          break
        }
        
        // 延遲後重試
        await sleep(1000)
      }
    }
  }
  
  /**
   * 切換到降級模式
   */
  const switchToFallback = () => {
    console.warn('Switching to fallback polling mode')
    
    // 停止 Long Polling
    if (longPollingController) {
      longPollingController.abort()
      longPollingController = null
    }
    
    // 顯示通知
    Swal.fire({
      title: '系統提示',
      text: '由於網絡問題，已切換到備用連接模式',
      icon: 'warning',
      timer: 5000,
      timerProgressBar: true,
      showConfirmButton: false,
      toast: true,
      position: 'top-end'
    })
    
    // 啟動傳統輪詢
    if (isPolling.value) {
      startFallbackPolling()
    }
  }
  
  /**
   * 啟動降級輪詢
   */
  const startFallbackPolling = () => {
    stopFallbackPolling()
    
    // 立即執行一次
    executeFallbackPolling()
    
    // 設置定時器
    fallbackInterval = setInterval(() => {
      if (isPolling.value && fallbackStrategy.currentStrategy.value === 'fallback') {
        executeFallbackPolling()
      }
    }, fallbackStrategy.fallbackInterval)
  }
  
  /**
   * 停止降級輪詢
   */
  const stopFallbackPolling = () => {
    if (fallbackInterval) {
      clearInterval(fallbackInterval)
      fallbackInterval = null
    }
  }
  
  /**
   * 嘗試恢復到 Long Polling
   */
  const attemptRecovery = async () => {
    // 檢查是否可以恢復
    const timeSinceChange = Date.now() - fallbackStrategy.lastStrategyChange.value
    if (timeSinceChange < 30000) {
      // 至少等待 30 秒再嘗試恢復
      return
    }
    
    console.log('Attempting to recover to Long Polling...')
    
    try {
      // 測試 Long Polling 端點
      const response = await fetch('/api/chats/poll-updates?test=1', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${getAuthToken()}`
        },
        timeout: 5000
      })
      
      if (response.ok) {
        // 恢復成功
        fallbackStrategy.recoverToLongPolling()
        stopFallbackPolling()
        
        Swal.fire({
          title: '系統提示',
          text: '已恢復到最佳連接模式',
          icon: 'success',
          timer: 3000,
          timerProgressBar: true,
          showConfirmButton: false,
          toast: true,
          position: 'top-end'
        })
        
        // 重啟 Long Polling
        if (isPolling.value) {
          longPollingLoop()
        }
      }
    } catch (error) {
      // 恢復失敗，繼續降級模式
      console.log('Recovery failed, continuing with fallback mode')
    }
  }
  
  /**
   * 顯示認證錯誤
   */
  const showAuthError = () => {
    Swal.fire({
      title: '系統提示',
      text: '請重新登錄',
      icon: 'error',
      confirmButtonText: '確定',
      allowOutsideClick: false
    }).then(() => {
      window.location.href = '/auth/login'
    })
  }
  
  /**
   * 開始輪詢
   */
  const startPolling = async (options = {}) => {
    if (isPolling.value) {
      return
    }
    
    currentOptions = options
    isPolling.value = true
    
    // 重置錯誤計數
    fallbackStrategy.resetErrors()
    
    // 根據當前策略啟動
    if (fallbackStrategy.currentStrategy.value === 'longPolling') {
      longPollingLoop()
    } else if (fallbackStrategy.currentStrategy.value === 'fallback') {
      startFallbackPolling()
    }
  }
  
  /**
   * 停止輪詢
   */
  const stopPolling = () => {
    isPolling.value = false
    
    // 停止 Long Polling
    if (longPollingController) {
      longPollingController.abort()
      longPollingController = null
    }
    
    // 停止降級輪詢
    stopFallbackPolling()
  }
  
  /**
   * 監聽策略變化
   */
  watch(() => fallbackStrategy.currentStrategy.value, (newStrategy, oldStrategy) => {
    if (!isPolling.value) return
    
    console.log(`Strategy changed: ${oldStrategy} -> ${newStrategy}`)
    
    if (newStrategy === 'longPolling' && oldStrategy === 'fallback') {
      stopFallbackPolling()
      longPollingLoop()
    } else if (newStrategy === 'fallback' && oldStrategy === 'longPolling') {
      if (longPollingController) {
        longPollingController.abort()
      }
      startFallbackPolling()
    }
  })
  
  /**
   * 工具函數
   */
  const getAuthToken = () => {
    return useCookie('auth-token').value || localStorage.getItem('auth_token')
  }
  
  const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms))
  
  // 清理
  onUnmounted(() => {
    stopPolling()
  })
  
  return {
    // 狀態
    isPolling,
    currentVersion,
    lastUpdateTime,
    currentStrategy: fallbackStrategy.currentStrategy,
    fallbackReason: fallbackStrategy.fallbackReason,
    errorCount: fallbackStrategy.errorCount,
    
    // 方法
    startPolling,
    stopPolling,
    getStrategyStatus: fallbackStrategy.getStrategyStatus
  }
}