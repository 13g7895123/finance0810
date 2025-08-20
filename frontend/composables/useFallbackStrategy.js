import { ref, computed } from 'vue'
import { ErrorHandlerService } from '~/services/ErrorHandlerService'

export const useFallbackStrategy = () => {
  const errorHandler = new ErrorHandlerService()
  
  // 狀態管理
  const currentStrategy = ref('longPolling') // longPolling, fallback, disabled
  const fallbackReason = ref(null)
  const errorCount = ref(0)
  const consecutiveErrors = ref(0)
  const lastStrategyChange = ref(null)
  
  // 配置
  const config = {
    maxConsecutiveErrors: 3,
    fallbackInterval: 5000,
    recoveryCheckInterval: 60000,
    errorThresholdPerMinute: 10
  }
  
  // 錯誤時間窗口（用於頻率計算）
  const errorTimestamps = ref([])
  
  /**
   * 評估是否需要降級
   */
  const evaluateFallback = (error) => {
    const errorInfo = errorHandler.handleError(error)
    errorCount.value++
    
    // 記錄錯誤時間
    const now = Date.now()
    errorTimestamps.value.push(now)
    
    // 清理舊的錯誤記錄（超過1分鐘）
    errorTimestamps.value = errorTimestamps.value.filter(
      t => now - t < 60000
    )
    
    // 檢查錯誤頻率
    if (errorTimestamps.value.length >= config.errorThresholdPerMinute) {
      return {
        shouldFallback: true,
        reason: 'High error frequency'
      }
    }
    
    // 檢查連續錯誤
    if (errorInfo.type === ErrorHandlerService.ErrorTypes.NETWORK_ERROR ||
        errorInfo.type === ErrorHandlerService.ErrorTypes.TIMEOUT_ERROR) {
      consecutiveErrors.value++
      
      if (consecutiveErrors.value >= config.maxConsecutiveErrors) {
        return {
          shouldFallback: true,
          reason: `${consecutiveErrors.value} consecutive errors`
        }
      }
    }
    
    // 檢查嚴重錯誤
    if (errorInfo.severity === ErrorHandlerService.Severity.CRITICAL) {
      return {
        shouldFallback: true,
        reason: 'Critical error'
      }
    }
    
    // 認證錯誤不降級，需要重新登錄
    if (errorInfo.type === ErrorHandlerService.ErrorTypes.AUTH_ERROR) {
      return {
        shouldFallback: false,
        shouldDisable: true,
        reason: 'Authentication required'
      }
    }
    
    return {
      shouldFallback: false
    }
  }
  
  /**
   * 啟用降級模式
   */
  const enableFallback = (reason) => {
    if (currentStrategy.value === 'fallback') {
      return false
    }
    
    console.warn('Enabling fallback mode:', reason)
    currentStrategy.value = 'fallback'
    fallbackReason.value = reason
    lastStrategyChange.value = Date.now()
    
    // 啟動恢復檢查
    scheduleRecoveryCheck()
    
    return true
  }
  
  /**
   * 禁用所有策略
   */
  const disableStrategy = (reason) => {
    console.error('Disabling all strategies:', reason)
    currentStrategy.value = 'disabled'
    fallbackReason.value = reason
    lastStrategyChange.value = Date.now()
  }
  
  /**
   * 恢復到 Long Polling
   */
  const recoverToLongPolling = () => {
    if (currentStrategy.value === 'longPolling') {
      return false
    }
    
    console.info('Recovering to Long Polling mode')
    currentStrategy.value = 'longPolling'
    fallbackReason.value = null
    consecutiveErrors.value = 0
    errorTimestamps.value = []
    lastStrategyChange.value = Date.now()
    
    return true
  }
  
  /**
   * 重置錯誤計數
   */
  const resetErrors = () => {
    errorCount.value = 0
    consecutiveErrors.value = 0
    errorTimestamps.value = []
  }
  
  /**
   * 成功回調
   */
  const onSuccess = () => {
    // 重置連續錯誤計數
    if (consecutiveErrors.value > 0) {
      consecutiveErrors.value = 0
    }
  }
  
  /**
   * 錯誤回調
   */
  const onError = (error) => {
    const evaluation = evaluateFallback(error)
    
    if (evaluation.shouldDisable) {
      disableStrategy(evaluation.reason)
      return 'disabled'
    }
    
    if (evaluation.shouldFallback) {
      enableFallback(evaluation.reason)
      return 'fallback'
    }
    
    return currentStrategy.value
  }
  
  /**
   * 安排恢復檢查
   */
  let recoveryTimer = null
  const scheduleRecoveryCheck = () => {
    if (recoveryTimer) {
      clearTimeout(recoveryTimer)
    }
    
    recoveryTimer = setTimeout(() => {
      if (currentStrategy.value === 'fallback') {
        console.log('Checking if can recover to Long Polling...')
        // 這裡可以執行健康檢查
        tryRecovery()
      }
    }, config.recoveryCheckInterval)
  }
  
  /**
   * 嘗試恢復
   */
  const tryRecovery = async () => {
    try {
      // 執行健康檢查
      const response = await fetch('/api/health', {
        method: 'GET',
        timeout: 5000
      })
      
      if (response.ok) {
        recoverToLongPolling()
      } else {
        // 繼續降級模式，安排下次檢查
        scheduleRecoveryCheck()
      }
    } catch (error) {
      // 健康檢查失敗，繼續降級模式
      scheduleRecoveryCheck()
    }
  }
  
  /**
   * 獲取策略狀態
   */
  const getStrategyStatus = () => ({
    current: currentStrategy.value,
    reason: fallbackReason.value,
    errorCount: errorCount.value,
    consecutiveErrors: consecutiveErrors.value,
    errorRate: errorTimestamps.value.length,
    lastChange: lastStrategyChange.value
  })
  
  return {
    // 狀態
    currentStrategy,
    fallbackReason,
    errorCount,
    consecutiveErrors,
    
    // 方法
    onSuccess,
    onError,
    enableFallback,
    disableStrategy,
    recoverToLongPolling,
    resetErrors,
    getStrategyStatus,
    
    // 配置
    fallbackInterval: config.fallbackInterval
  }
}