import { ref, computed, onMounted, onUnmounted } from 'vue'

export const usePageVisibility = () => {
  const isVisible = ref(!document.hidden)
  const visibilityState = ref(document.visibilityState)
  const lastVisibleTime = ref(Date.now())
  const lastHiddenTime = ref(null)
  const visibilityHistory = ref([])
  
  // 可見性狀態統計
  const stats = ref({
    totalVisibleTime: 0,
    totalHiddenTime: 0,
    visibilityChanges: 0,
    sessionStartTime: Date.now()
  })
  
  /**
   * 處理頁面可見性變化
   */
  const handleVisibilityChange = () => {
    const now = Date.now()
    const wasVisible = isVisible.value
    
    // 更新基本狀態
    isVisible.value = !document.hidden
    visibilityState.value = document.visibilityState
    stats.value.visibilityChanges++
    
    // 記錄時間統計
    if (wasVisible && !isVisible.value) {
      // 從可見變為隱藏
      lastHiddenTime.value = now
      if (lastVisibleTime.value) {
        stats.value.totalVisibleTime += now - lastVisibleTime.value
      }
    } else if (!wasVisible && isVisible.value) {
      // 從隱藏變為可見
      lastVisibleTime.value = now
      if (lastHiddenTime.value) {
        stats.value.totalHiddenTime += now - lastHiddenTime.value
      }
    }
    
    // 記錄可見性歷史
    visibilityHistory.value.push({
      timestamp: now,
      state: visibilityState.value,
      isVisible: isVisible.value,
      duration: wasVisible !== isVisible.value ? 
        (wasVisible ? now - lastVisibleTime.value : now - lastHiddenTime.value) : 0
    })
    
    // 保持最近 50 條記錄
    if (visibilityHistory.value.length > 50) {
      visibilityHistory.value.shift()
    }
    
    // 觸發自定義事件
    const event = new CustomEvent('page-visibility-change', {
      detail: {
        isVisible: isVisible.value,
        visibilityState: visibilityState.value,
        timestamp: now
      }
    })
    window.dispatchEvent(event)
  }
  
  /**
   * 獲取頁面隱藏時長
   */
  const getHiddenDuration = computed(() => {
    if (isVisible.value || !lastHiddenTime.value) {
      return 0
    }
    return Date.now() - lastHiddenTime.value
  })
  
  /**
   * 獲取頁面可見時長
   */
  const getVisibleDuration = computed(() => {
    if (!isVisible.value || !lastVisibleTime.value) {
      return 0
    }
    return Date.now() - lastVisibleTime.value
  })
  
  /**
   * 獲取會話統計
   */
  const getSessionStats = computed(() => {
    const now = Date.now()
    const sessionDuration = now - stats.value.sessionStartTime
    
    let currentVisibleTime = stats.value.totalVisibleTime
    let currentHiddenTime = stats.value.totalHiddenTime
    
    // 加上當前狀態的時間
    if (isVisible.value && lastVisibleTime.value) {
      currentVisibleTime += now - lastVisibleTime.value
    } else if (!isVisible.value && lastHiddenTime.value) {
      currentHiddenTime += now - lastHiddenTime.value
    }
    
    return {
      sessionDuration,
      totalVisibleTime: currentVisibleTime,
      totalHiddenTime: currentHiddenTime,
      visibilityChanges: stats.value.visibilityChanges,
      visiblePercentage: sessionDuration > 0 ? 
        ((currentVisibleTime / sessionDuration) * 100).toFixed(2) : '0.00',
      averageVisibleSession: stats.value.visibilityChanges > 0 ? 
        Math.round(currentVisibleTime / (stats.value.visibilityChanges / 2 || 1)) : 0
    }
  })
  
  /**
   * 檢查是否為長時間隱藏
   */
  const isLongHidden = computed(() => {
    return getHiddenDuration.value > 300000 // 5 分鐘
  })
  
  /**
   * 檢查是否剛剛變為可見
   */
  const isRecentlyVisible = computed(() => {
    return isVisible.value && getVisibleDuration.value < 5000 // 5 秒內
  })
  
  /**
   * 預測下次可見性變化
   */
  const predictNextChange = computed(() => {
    if (visibilityHistory.value.length < 3) {
      return null
    }
    
    const recentHistory = visibilityHistory.value.slice(-10)
    const averageDuration = recentHistory.reduce((sum, record) => 
      sum + record.duration, 0) / recentHistory.length
    
    const currentDuration = isVisible.value ? 
      getVisibleDuration.value : getHiddenDuration.value
    
    return {
      estimatedChangeIn: Math.max(0, averageDuration - currentDuration),
      confidence: recentHistory.length >= 5 ? 'medium' : 'low',
      nextState: isVisible.value ? 'hidden' : 'visible'
    }
  })
  
  /**
   * 重置統計數據
   */
  const resetStats = () => {
    stats.value = {
      totalVisibleTime: 0,
      totalHiddenTime: 0,
      visibilityChanges: 0,
      sessionStartTime: Date.now()
    }
    visibilityHistory.value = []
    lastVisibleTime.value = isVisible.value ? Date.now() : null
    lastHiddenTime.value = !isVisible.value ? Date.now() : null
  }
  
  /**
   * 添加可見性變化監聽器
   */
  const onVisibilityChange = (callback) => {
    const handler = (event) => callback(event.detail)
    window.addEventListener('page-visibility-change', handler)
    
    // 返回清理函數
    return () => window.removeEventListener('page-visibility-change', handler)
  }
  
  /**
   * 檢查瀏覽器支持
   */
  const isSupported = computed(() => {
    return typeof document.hidden !== 'undefined' && 
           typeof document.visibilityState !== 'undefined'
  })
  
  // 頁面卸載時保存最終統計
  const handleBeforeUnload = () => {
    const finalStats = getSessionStats.value
    
    // 可以在這裡發送統計數據到服務器
    console.log('Page visibility session stats:', finalStats)
    
    // 存儲到 localStorage 以便下次使用
    try {
      localStorage.setItem('pageVisibilityStats', JSON.stringify(finalStats))
    } catch (error) {
      console.warn('Failed to save visibility stats:', error)
    }
  }
  
  onMounted(() => {
    // 初始化狀態
    if (isVisible.value) {
      lastVisibleTime.value = Date.now()
    } else {
      lastHiddenTime.value = Date.now()
    }
    
    // 註冊事件監聽器
    document.addEventListener('visibilitychange', handleVisibilityChange)
    window.addEventListener('beforeunload', handleBeforeUnload)
    
    // 嘗試恢復之前的統計數據
    try {
      const savedStats = localStorage.getItem('pageVisibilityStats')
      if (savedStats) {
        const parsed = JSON.parse(savedStats)
        console.log('Previous session visibility stats:', parsed)
      }
    } catch (error) {
      console.warn('Failed to load previous visibility stats:', error)
    }
  })
  
  onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange)
    window.removeEventListener('beforeunload', handleBeforeUnload)
    handleBeforeUnload() // 確保統計數據被保存
  })
  
  return {
    // 基本狀態
    isVisible,
    visibilityState,
    isSupported,
    
    // 時間相關
    getHiddenDuration,
    getVisibleDuration,
    lastVisibleTime,
    lastHiddenTime,
    
    // 統計和分析
    getSessionStats,
    visibilityHistory,
    isLongHidden,
    isRecentlyVisible,
    predictNextChange,
    
    // 方法
    resetStats,
    onVisibilityChange
  }
}