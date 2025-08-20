import { ref, onUnmounted } from 'vue'
import { useAuthStore } from '~/stores/auth'

/**
 * 簡化的輪詢機制
 * 移除複雜的 Long Polling 和回退邏輯，使用穩定的定時輪詢
 */
export const useSimplePolling = () => {
  const authStore = useAuthStore()
  
  // 狀態管理
  const isPolling = ref(false)
  const connectionStatus = ref('disconnected')
  const lastUpdateTime = ref(null)
  const errorCount = ref(0)
  const maxRetries = 3
  
  // 輪詢控制
  let pollingInterval = null
  let pollingOptions = null
  
  /**
   * 執行單次 API 調用
   */
  const executePolling = async () => {
    try {
      if (!authStore.user?.token) {
        throw new Error('No authentication token available')
      }
      
      const { $api } = useNuxtApp()
      const response = await $api('/chats', {
        method: 'GET',
        query: { page: 1 },
        timeout: 8000, // 8秒超時
      })
      
      // 成功處理
      errorCount.value = 0
      connectionStatus.value = 'connected'
      lastUpdateTime.value = new Date()
      
      // 調用更新回調
      if (pollingOptions?.onUpdate && response?.data) {
        await pollingOptions.onUpdate(response.data)
      }
      
      return true
      
    } catch (error) {
      errorCount.value++
      console.warn(`Polling error (${errorCount.value}/${maxRetries}):`, error.message)
      
      // 設定連線狀態
      if (error.statusCode === 401) {
        connectionStatus.value = 'unauthorized'
        stopPolling()
        
        // 調用認證錯誤回調
        if (pollingOptions?.onAuthError) {
          pollingOptions.onAuthError(error)
        }
        return false
      }
      
      connectionStatus.value = 'error'
      
      // 達到最大重試次數時停止輪詢
      if (errorCount.value >= maxRetries) {
        console.error('Polling stopped due to too many errors')
        stopPolling()
        
        if (pollingOptions?.onError) {
          pollingOptions.onError(error)
        }
        return false
      }
      
      return false
    }
  }
  
  /**
   * 開始輪詢
   * @param {Object} options - 輪詢選項
   * @param {Function} options.onUpdate - 數據更新時的回調函數
   * @param {Function} options.onError - 錯誤處理回調
   * @param {Function} options.onAuthError - 認證錯誤回調
   * @param {number} options.interval - 輪詢間隔（毫秒）
   */
  const startPolling = (options = {}) => {
    // 如果已經在輪詢，先停止
    if (isPolling.value) {
      stopPolling()
    }
    
    pollingOptions = {
      interval: 2000, // 預設 2 秒間隔
      ...options
    }
    
    isPolling.value = true
    connectionStatus.value = 'connecting'
    errorCount.value = 0
    
    // 立即執行一次
    executePolling()
    
    // 設定定時輪詢
    pollingInterval = setInterval(executePolling, pollingOptions.interval)
    
    console.log(`Started polling with ${pollingOptions.interval}ms interval`)
  }
  
  /**
   * 停止輪詢
   */
  const stopPolling = () => {
    if (pollingInterval) {
      clearInterval(pollingInterval)
      pollingInterval = null
    }
    
    isPolling.value = false
    connectionStatus.value = 'disconnected'
    errorCount.value = 0
    
    console.log('Stopped polling')
  }
  
  /**
   * 手動刷新數據
   */
  const manualRefresh = async () => {
    connectionStatus.value = 'connecting'
    const success = await executePolling()
    
    if (!success && !isPolling.value) {
      connectionStatus.value = 'disconnected'
    }
    
    return success
  }
  
  /**
   * 重置錯誤計數
   */
  const resetErrors = () => {
    errorCount.value = 0
    if (!isPolling.value) {
      connectionStatus.value = 'disconnected'
    }
  }
  
  /**
   * 獲取連線狀態文字
   */
  const getConnectionStatusText = () => {
    switch (connectionStatus.value) {
      case 'connected':
        return '已連線'
      case 'connecting':
        return '連線中'
      case 'error':
        return `錯誤 (${errorCount.value}/${maxRetries})`
      case 'unauthorized':
        return '認證失敗'
      case 'disconnected':
      default:
        return '已斷線'
    }
  }
  
  // 組件卸載時清理
  onUnmounted(() => {
    stopPolling()
  })
  
  return {
    // 狀態
    isPolling: readonly(isPolling),
    connectionStatus: readonly(connectionStatus),
    lastUpdateTime: readonly(lastUpdateTime),
    errorCount: readonly(errorCount),
    
    // 方法
    startPolling,
    stopPolling,
    manualRefresh,
    resetErrors,
    getConnectionStatusText
  }
}