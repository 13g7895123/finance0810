import { ref, computed, onMounted, onUnmounted } from 'vue'

export const useSmartPolling = () => {
  // 輪詢間隔配置
  const intervals = {
    active: 1000,      // 用戶活躍時 1 秒
    inactive: 5000,    // 用戶非活躍時 5 秒
    background: 30000, // 後台時 30 秒
    offline: 60000     // 離線時 60 秒
  }
  
  const currentInterval = ref(intervals.active)
  const userActivity = ref(Date.now())
  const isOnline = ref(navigator.onLine)
  const isDocumentVisible = ref(!document.hidden)
  const pollingEnabled = ref(true)
  
  // 活動檢測閾值（毫秒）
  const ACTIVITY_THRESHOLD = 60000 // 1 分鐘無活動視為非活躍
  const OFFLINE_CHECK_INTERVAL = 5000 // 離線檢查間隔
  
  /**
   * 根據用戶活動調整輪詢頻率
   */
  const adjustPollingInterval = () => {
    if (!pollingEnabled.value) {
      return
    }
    
    const timeSinceLastActivity = Date.now() - userActivity.value
    
    // 離線狀態
    if (!isOnline.value) {
      currentInterval.value = intervals.offline
      return
    }
    
    // 後台狀態
    if (!isDocumentVisible.value) {
      currentInterval.value = intervals.background
      return
    }
    
    // 根據用戶活動調整
    if (timeSinceLastActivity > ACTIVITY_THRESHOLD) {
      currentInterval.value = intervals.inactive
    } else {
      currentInterval.value = intervals.active
    }
  }
  
  /**
   * 更新用戶活動時間
   */
  const updateUserActivity = () => {
    userActivity.value = Date.now()
    adjustPollingInterval()
  }
  
  /**
   * 處理頁面可見性變化
   */
  const handleVisibilityChange = () => {
    isDocumentVisible.value = !document.hidden
    adjustPollingInterval()
  }
  
  /**
   * 處理網絡狀態變化
   */
  const handleOnlineStatus = () => {
    isOnline.value = navigator.onLine
    adjustPollingInterval()
  }
  
  /**
   * 啟用/禁用輪詢
   */
  const setPollingEnabled = (enabled) => {
    pollingEnabled.value = enabled
    if (enabled) {
      adjustPollingInterval()
    }
  }
  
  /**
   * 獲取當前輪詢狀態
   */
  const getPollingState = computed(() => ({
    interval: currentInterval.value,
    enabled: pollingEnabled.value,
    online: isOnline.value,
    visible: isDocumentVisible.value,
    lastActivity: userActivity.value,
    timeSinceActivity: Date.now() - userActivity.value
  }))
  
  /**
   * 根據當前狀態獲取描述
   */
  const getPollingDescription = computed(() => {
    if (!pollingEnabled.value) return 'Polling disabled'
    if (!isOnline.value) return 'Offline mode'
    if (!isDocumentVisible.value) return 'Background mode'
    
    const timeSinceActivity = Date.now() - userActivity.value
    if (timeSinceActivity > ACTIVITY_THRESHOLD) {
      return 'Inactive user'
    }
    
    return 'Active user'
  })
  
  /**
   * 智能退避算法 - 在連續錯誤時增加間隔
   */
  const errorCount = ref(0)
  const maxRetries = 5
  
  const handlePollingError = () => {
    errorCount.value++
    
    if (errorCount.value >= maxRetries) {
      // 達到最大重試次數，使用更長的間隔
      const backoffMultiplier = Math.min(Math.pow(2, errorCount.value - maxRetries), 8)
      currentInterval.value = intervals.inactive * backoffMultiplier
    }
  }
  
  const handlePollingSuccess = () => {
    errorCount.value = 0
    adjustPollingInterval()
  }
  
  /**
   * 自適應間隔調整 - 根據響應時間調整
   */
  const responseTimeHistory = ref([])
  const MAX_RESPONSE_HISTORY = 10
  
  const recordResponseTime = (responseTime) => {
    responseTimeHistory.value.push(responseTime)
    
    if (responseTimeHistory.value.length > MAX_RESPONSE_HISTORY) {
      responseTimeHistory.value.shift()
    }
    
    // 如果平均響應時間較慢，適當增加間隔
    const avgResponseTime = responseTimeHistory.value.reduce((a, b) => a + b, 0) / responseTimeHistory.value.length
    
    if (avgResponseTime > 1000 && currentInterval.value < intervals.inactive) {
      // 響應時間超過 1 秒，適當放緩輪詢
      currentInterval.value = Math.min(currentInterval.value * 1.2, intervals.inactive)
    }
  }
  
  // 監聽用戶活動事件
  const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click']
  
  onMounted(() => {
    // 註冊活動事件監聽器
    activityEvents.forEach(event => {
      document.addEventListener(event, updateUserActivity, { passive: true })
    })
    
    // 註冊頁面可見性監聽器
    document.addEventListener('visibilitychange', handleVisibilityChange)
    
    // 註冊網絡狀態監聽器
    window.addEventListener('online', handleOnlineStatus)
    window.addEventListener('offline', handleOnlineStatus)
    
    // 定期檢查和調整間隔
    const checkInterval = setInterval(() => {
      adjustPollingInterval()
    }, OFFLINE_CHECK_INTERVAL)
    
    // 清理函數
    onUnmounted(() => {
      activityEvents.forEach(event => {
        document.removeEventListener(event, updateUserActivity)
      })
      
      document.removeEventListener('visibilitychange', handleVisibilityChange)
      window.removeEventListener('online', handleOnlineStatus)
      window.removeEventListener('offline', handleOnlineStatus)
      
      clearInterval(checkInterval)
    })
  })
  
  return {
    // 狀態
    currentInterval,
    pollingEnabled,
    isOnline,
    isDocumentVisible,
    getPollingState,
    getPollingDescription,
    
    // 方法
    adjustPollingInterval,
    updateUserActivity,
    setPollingEnabled,
    handlePollingError,
    handlePollingSuccess,
    recordResponseTime,
    
    // 配置
    intervals
  }
}