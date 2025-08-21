import { ref, onUnmounted, readonly } from 'vue'
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
    console.log('=== executePolling 開始執行 ===')
    console.log('pollingOptions 檢查:', {
      exists: !!pollingOptions,
      type: typeof pollingOptions,
      onUpdate: {
        exists: !!pollingOptions?.onUpdate,
        type: typeof pollingOptions?.onUpdate,
        isFunction: typeof pollingOptions?.onUpdate === 'function'
      },
      onError: {
        exists: !!pollingOptions?.onError,
        type: typeof pollingOptions?.onError,
        isFunction: typeof pollingOptions?.onError === 'function'
      },
      onAuthError: {
        exists: !!pollingOptions?.onAuthError,
        type: typeof pollingOptions?.onAuthError,
        isFunction: typeof pollingOptions?.onAuthError === 'function'
      }
    })
    
    try {
      // 檢查認證狀態
      console.log('認證檢查:', {
        authStore: !!authStore,
        user: !!authStore.user,
        token: !!authStore.user?.token,
        tokenType: typeof authStore.user?.token
      })
      
      if (!authStore.user?.token) {
        throw new Error('No authentication token available')
      }
      
      console.log('準備調用 API...')
      const { $api } = useNuxtApp()
      console.log('$api 函數:', {
        exists: !!$api,
        type: typeof $api,
        isFunction: typeof $api === 'function'
      })
      
      const response = await $api('/chats', {
        method: 'GET',
        query: { page: 1 },
        timeout: 8000, // 8秒超時
      })
      
      console.log('API 調用成功:', {
        response: !!response,
        data: !!response?.data,
        dataType: typeof response?.data,
        dataLength: Array.isArray(response?.data) ? response.data.length : 'not array'
      })
      
      // 成功處理
      errorCount.value = 0
      connectionStatus.value = 'connected'
      lastUpdateTime.value = new Date()
      
      // 調用更新回調 - 加強檢查
      console.log('準備調用 onUpdate 回調...')
      if (pollingOptions?.onUpdate && response?.data) {
        console.log('執行 onUpdate 回調:', {
          onUpdateType: typeof pollingOptions.onUpdate,
          isFunction: typeof pollingOptions.onUpdate === 'function',
          responseDataType: typeof response.data
        })
        
        // 額外檢查確保 onUpdate 是函數
        if (typeof pollingOptions.onUpdate !== 'function') {
          console.error('onUpdate 不是函數!', {
            actualType: typeof pollingOptions.onUpdate,
            value: pollingOptions.onUpdate
          })
          throw new Error(`onUpdate callback is not a function, got: ${typeof pollingOptions.onUpdate}`)
        }
        
        try {
          await pollingOptions.onUpdate(response.data)
          console.log('onUpdate 回調執行成功')
        } catch (callbackError) {
          console.error('onUpdate 回調執行失敗:', {
            error: callbackError.message,
            stack: callbackError.stack,
            callbackType: typeof pollingOptions.onUpdate
          })
          throw callbackError
        }
      } else {
        console.log('跳過 onUpdate 回調:', {
          hasOnUpdate: !!pollingOptions?.onUpdate,
          hasResponseData: !!response?.data
        })
      }
      
      console.log('=== executePolling 成功完成 ===')
      return true
      
    } catch (error) {
      errorCount.value++
      console.error('=== executePolling 發生錯誤 ===')
      console.error(`Polling error (${errorCount.value}/${maxRetries}):`, {
        message: error.message,
        stack: error.stack,
        statusCode: error.statusCode,
        errorType: typeof error,
        errorConstructor: error.constructor.name
      })
      
      // 設定連線狀態
      if (error.statusCode === 401) {
        console.log('處理 401 認證錯誤')
        connectionStatus.value = 'unauthorized'
        stopPolling()
        
        // 調用認證錯誤回調
        if (pollingOptions?.onAuthError) {
          console.log('執行 onAuthError 回調:', {
            type: typeof pollingOptions.onAuthError,
            isFunction: typeof pollingOptions.onAuthError === 'function'
          })
          
          if (typeof pollingOptions.onAuthError === 'function') {
            try {
              pollingOptions.onAuthError(error)
              console.log('onAuthError 回調執行成功')
            } catch (authCallbackError) {
              console.error('onAuthError 回調執行失敗:', authCallbackError)
            }
          } else {
            console.error('onAuthError 不是函數!', typeof pollingOptions.onAuthError)
          }
        }
        return false
      }
      
      connectionStatus.value = 'error'
      
      // 達到最大重試次數時停止輪詢
      if (errorCount.value >= maxRetries) {
        console.error('Polling stopped due to too many errors')
        stopPolling()
        
        if (pollingOptions?.onError) {
          console.log('執行 onError 回調:', {
            type: typeof pollingOptions.onError,
            isFunction: typeof pollingOptions.onError === 'function'
          })
          
          if (typeof pollingOptions.onError === 'function') {
            try {
              pollingOptions.onError(error)
              console.log('onError 回調執行成功')
            } catch (errorCallbackError) {
              console.error('onError 回調執行失敗:', errorCallbackError)
            }
          } else {
            console.error('onError 不是函數!', typeof pollingOptions.onError)
          }
        }
        return false
      }
      
      console.log('=== executePolling 錯誤處理完成 ===')
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
    console.log('=== startPolling 開始 ===')
    console.log('傳入參數檢查:', {
      options: !!options,
      optionsType: typeof options,
      keys: Object.keys(options || {}),
      onUpdate: {
        exists: 'onUpdate' in (options || {}),
        value: options?.onUpdate,
        type: typeof options?.onUpdate,
        isFunction: typeof options?.onUpdate === 'function'
      },
      onError: {
        exists: 'onError' in (options || {}),
        value: options?.onError,
        type: typeof options?.onError,
        isFunction: typeof options?.onError === 'function'
      },
      onAuthError: {
        exists: 'onAuthError' in (options || {}),
        value: options?.onAuthError,
        type: typeof options?.onAuthError,
        isFunction: typeof options?.onAuthError === 'function'
      },
      interval: {
        exists: 'interval' in (options || {}),
        value: options?.interval,
        type: typeof options?.interval
      }
    })
    
    // 驗證回調函數
    if (options?.onUpdate && typeof options.onUpdate !== 'function') {
      console.error('startPolling: onUpdate 必須是函數!', {
        received: typeof options.onUpdate,
        value: options.onUpdate
      })
      throw new Error(`onUpdate must be a function, got: ${typeof options.onUpdate}`)
    }
    
    if (options?.onError && typeof options.onError !== 'function') {
      console.error('startPolling: onError 必須是函數!', {
        received: typeof options.onError,
        value: options.onError
      })
      throw new Error(`onError must be a function, got: ${typeof options.onError}`)
    }
    
    if (options?.onAuthError && typeof options.onAuthError !== 'function') {
      console.error('startPolling: onAuthError 必須是函數!', {
        received: typeof options.onAuthError,
        value: options.onAuthError
      })
      throw new Error(`onAuthError must be a function, got: ${typeof options.onAuthError}`)
    }
    
    // 如果已經在輪詢，先停止
    if (isPolling.value) {
      console.log('停止現有輪詢')
      stopPolling()
    }
    
    pollingOptions = {
      interval: 2000, // 預設 2 秒間隔
      ...options
    }
    
    console.log('設定輪詢選項:', {
      interval: pollingOptions.interval,
      hasOnUpdate: !!pollingOptions.onUpdate,
      hasOnError: !!pollingOptions.onError,
      hasOnAuthError: !!pollingOptions.onAuthError
    })
    
    isPolling.value = true
    connectionStatus.value = 'connecting'
    errorCount.value = 0
    
    // 立即執行一次
    console.log('立即執行第一次輪詢')
    executePolling()
    
    // 設定定時輪詢
    console.log(`設定定時器，間隔 ${pollingOptions.interval}ms`)
    pollingInterval = setInterval(() => {
      console.log('定時器觸發 executePolling')
      executePolling()
    }, pollingOptions.interval)
    
    console.log(`Started polling with ${pollingOptions.interval}ms interval`)
    console.log('=== startPolling 完成 ===')
  }
  
  /**
   * 停止輪詢
   */
  const stopPolling = () => {
    console.log('=== stopPolling 開始 ===')
    console.log('當前狀態:', {
      isPolling: isPolling.value,
      hasInterval: !!pollingInterval,
      connectionStatus: connectionStatus.value,
      errorCount: errorCount.value
    })
    
    if (pollingInterval) {
      console.log('清除定時器')
      clearInterval(pollingInterval)
      pollingInterval = null
    } else {
      console.log('沒有定時器需要清除')
    }
    
    isPolling.value = false
    connectionStatus.value = 'disconnected'
    errorCount.value = 0
    
    console.log('Stopped polling')
    console.log('=== stopPolling 完成 ===')
  }
  
  /**
   * 手動刷新數據
   */
  const manualRefresh = async () => {
    console.log('=== manualRefresh 開始 ===')
    console.log('當前狀態:', {
      isPolling: isPolling.value,
      connectionStatus: connectionStatus.value,
      errorCount: errorCount.value
    })
    
    connectionStatus.value = 'connecting'
    console.log('開始手動執行輪詢')
    const success = await executePolling()
    
    console.log('手動輪詢結果:', success)
    
    if (!success && !isPolling.value) {
      console.log('手動輪詢失敗且未在輪詢中，設定為斷線狀態')
      connectionStatus.value = 'disconnected'
    }
    
    console.log('=== manualRefresh 完成 ===')
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