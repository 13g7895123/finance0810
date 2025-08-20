import { ref, computed, onMounted, onUnmounted } from 'vue'

export const useUserActivity = () => {
  const lastActivity = ref(Date.now())
  const isActive = ref(true)
  const activityThreshold = 30000 // 30 秒無活動視為非活躍
  const longInactiveThreshold = 300000 // 5 分鐘無活動視為長時間非活躍
  
  // 活動統計
  const activityStats = ref({
    totalActiveTime: 0,
    totalInactiveTime: 0,
    activityChanges: 0,
    sessionStartTime: Date.now(),
    lastActiveTime: Date.now(),
    lastInactiveTime: null,
    activityScore: 100 // 活躍度評分 (0-100)
  })
  
  // 活動歷史記錄
  const activityHistory = ref([])
  const MAX_HISTORY_LENGTH = 100
  
  // 監聽的事件類型
  const events = [
    'mousedown', 'mousemove', 'mousewheel',
    'keypress', 'keydown', 'keyup',
    'scroll', 'touchstart', 'touchmove',
    'click', 'dblclick', 'contextmenu',
    'focus', 'blur', 'resize'
  ]
  
  // 活動模式檢測
  const activityPatterns = ref({
    clicksPerMinute: 0,
    scrollsPerMinute: 0,
    keyPressesPerMinute: 0,
    mouseMoveDistance: 0,
    lastMousePosition: { x: 0, y: 0 }
  })
  
  // 活動計數器
  const activityCounters = ref({
    clicks: 0,
    scrolls: 0,
    keyPresses: 0,
    mouseMovements: 0,
    lastResetTime: Date.now()
  })
  
  /**
   * 更新用戶活動時間
   */
  const updateActivity = (eventType = null) => {
    const now = Date.now()
    const wasActive = isActive.value
    
    lastActivity.value = now
    
    // 記錄特定活動類型
    if (eventType) {
      recordActivityType(eventType)
    }
    
    // 如果之前是非活躍狀態，現在變為活躍
    if (!wasActive) {
      isActive.value = true
      activityStats.value.activityChanges++
      activityStats.value.lastActiveTime = now
      
      // 計算非活躍時間
      if (activityStats.value.lastInactiveTime) {
        activityStats.value.totalInactiveTime += now - activityStats.value.lastInactiveTime
      }
      
      // 記錄活動變化
      recordActivityChange('active', now)
      
      // 觸發活動恢復事件
      triggerActivityEvent('user-became-active', { timestamp: now })
    }
    
    // 更新活躍度評分
    updateActivityScore()
  }
  
  /**
   * 記錄特定活動類型
   */
  const recordActivityType = (eventType) => {
    const counters = activityCounters.value
    
    switch (eventType) {
      case 'click':
      case 'dblclick':
        counters.clicks++
        break
      case 'scroll':
        counters.scrolls++
        break
      case 'keypress':
      case 'keydown':
        counters.keyPresses++
        break
      case 'mousemove':
        counters.mouseMovements++
        break
    }
  }
  
  /**
   * 檢查用戶活動狀態
   */
  const checkActivity = () => {
    const now = Date.now()
    const timeSinceLastActivity = now - lastActivity.value
    const wasActive = isActive.value
    
    if (timeSinceLastActivity > activityThreshold && wasActive) {
      // 變為非活躍狀態
      isActive.value = false
      activityStats.value.activityChanges++
      activityStats.value.lastInactiveTime = now
      
      // 計算活躍時間
      if (activityStats.value.lastActiveTime) {
        activityStats.value.totalActiveTime += now - activityStats.value.lastActiveTime
      }
      
      // 記錄活動變化
      recordActivityChange('inactive', now, timeSinceLastActivity)
      
      // 觸發非活動事件
      triggerActivityEvent('user-became-inactive', { 
        timestamp: now, 
        inactiveDuration: timeSinceLastActivity 
      })
    }
    
    // 更新活動模式統計
    updateActivityPatterns()
    
    // 更新活躍度評分
    updateActivityScore()
  }
  
  /**
   * 記錄活動變化
   */
  const recordActivityChange = (state, timestamp, duration = 0) => {
    activityHistory.value.push({
      state,
      timestamp,
      duration,
      activityScore: activityStats.value.activityScore
    })
    
    // 保持歷史記錄長度
    if (activityHistory.value.length > MAX_HISTORY_LENGTH) {
      activityHistory.value.shift()
    }
  }
  
  /**
   * 更新活動模式統計
   */
  const updateActivityPatterns = () => {
    const now = Date.now()
    const timeSinceReset = now - activityCounters.value.lastResetTime
    
    if (timeSinceReset >= 60000) { // 每分鐘重置一次
      const patterns = activityPatterns.value
      const counters = activityCounters.value
      
      patterns.clicksPerMinute = counters.clicks
      patterns.scrollsPerMinute = counters.scrolls
      patterns.keyPressesPerMinute = counters.keyPresses
      
      // 重置計數器
      Object.keys(counters).forEach(key => {
        if (key !== 'lastResetTime') {
          counters[key] = 0
        }
      })
      counters.lastResetTime = now
    }
  }
  
  /**
   * 更新活躍度評分
   */
  const updateActivityScore = () => {
    const now = Date.now()
    const sessionDuration = now - activityStats.value.sessionStartTime
    const currentActiveTime = activityStats.value.totalActiveTime + 
      (isActive.value ? now - activityStats.value.lastActiveTime : 0)
    
    // 基於活躍時間比例計算評分
    let score = sessionDuration > 0 ? (currentActiveTime / sessionDuration) * 100 : 100
    
    // 基於活動頻率調整評分
    const totalActivity = activityPatterns.value.clicksPerMinute + 
                         activityPatterns.value.scrollsPerMinute + 
                         activityPatterns.value.keyPressesPerMinute
    
    if (totalActivity > 50) {
      score = Math.min(score + 10, 100) // 高活動頻率加分
    } else if (totalActivity < 5) {
      score = Math.max(score - 10, 0) // 低活動頻率減分
    }
    
    activityStats.value.activityScore = Math.round(score)
  }
  
  /**
   * 處理鼠標移動
   */
  const handleMouseMove = (event) => {
    const patterns = activityPatterns.value
    const currentPos = { x: event.clientX, y: event.clientY }
    
    if (patterns.lastMousePosition.x !== 0 || patterns.lastMousePosition.y !== 0) {
      const distance = Math.sqrt(
        Math.pow(currentPos.x - patterns.lastMousePosition.x, 2) +
        Math.pow(currentPos.y - patterns.lastMousePosition.y, 2)
      )
      patterns.mouseMoveDistance += distance
    }
    
    patterns.lastMousePosition = currentPos
    updateActivity('mousemove')
  }
  
  /**
   * 觸發活動事件
   */
  const triggerActivityEvent = (eventType, detail) => {
    const event = new CustomEvent(eventType, { detail })
    window.dispatchEvent(event)
  }
  
  /**
   * 計算屬性
   */
  const timeSinceLastActivity = computed(() => {
    return Date.now() - lastActivity.value
  })
  
  const isLongInactive = computed(() => {
    return timeSinceLastActivity.value > longInactiveThreshold
  })
  
  const getSessionStats = computed(() => {
    const now = Date.now()
    const sessionDuration = now - activityStats.value.sessionStartTime
    
    let currentActiveTime = activityStats.value.totalActiveTime
    let currentInactiveTime = activityStats.value.totalInactiveTime
    
    // 加上當前狀態的時間
    if (isActive.value && activityStats.value.lastActiveTime) {
      currentActiveTime += now - activityStats.value.lastActiveTime
    } else if (!isActive.value && activityStats.value.lastInactiveTime) {
      currentInactiveTime += now - activityStats.value.lastInactiveTime
    }
    
    return {
      sessionDuration,
      totalActiveTime: currentActiveTime,
      totalInactiveTime: currentInactiveTime,
      activityChanges: activityStats.value.activityChanges,
      activePercentage: sessionDuration > 0 ? 
        ((currentActiveTime / sessionDuration) * 100).toFixed(2) : '100.00',
      activityScore: activityStats.value.activityScore,
      averageActiveSession: activityStats.value.activityChanges > 0 ? 
        Math.round(currentActiveTime / (activityStats.value.activityChanges || 1)) : currentActiveTime
    }
  })
  
  const getActivityLevel = computed(() => {
    const score = activityStats.value.activityScore
    if (score >= 80) return 'high'
    if (score >= 50) return 'medium'
    if (score >= 20) return 'low'
    return 'very-low'
  })
  
  /**
   * 添加活動監聽器
   */
  const onActivityChange = (callback) => {
    const activeHandler = (event) => callback('active', event.detail)
    const inactiveHandler = (event) => callback('inactive', event.detail)
    
    window.addEventListener('user-became-active', activeHandler)
    window.addEventListener('user-became-inactive', inactiveHandler)
    
    return () => {
      window.removeEventListener('user-became-active', activeHandler)
      window.removeEventListener('user-became-inactive', inactiveHandler)
    }
  }
  
  /**
   * 重置活動統計
   */
  const resetActivityStats = () => {
    const now = Date.now()
    activityStats.value = {
      totalActiveTime: 0,
      totalInactiveTime: 0,
      activityChanges: 0,
      sessionStartTime: now,
      lastActiveTime: now,
      lastInactiveTime: null,
      activityScore: 100
    }
    activityHistory.value = []
    lastActivity.value = now
    isActive.value = true
  }
  
  // 設置定期檢查
  let checkInterval = null
  
  onMounted(() => {
    // 註冊活動事件監聽器
    events.forEach(event => {
      if (event === 'mousemove') {
        document.addEventListener(event, handleMouseMove, { passive: true })
      } else {
        document.addEventListener(event, () => updateActivity(event), { passive: true })
      }
    })
    
    // 設置定期檢查
    checkInterval = setInterval(checkActivity, 5000) // 每 5 秒檢查一次
    
    // 清理函數
    onUnmounted(() => {
      events.forEach(event => {
        if (event === 'mousemove') {
          document.removeEventListener(event, handleMouseMove)
        } else {
          document.removeEventListener(event, () => updateActivity(event))
        }
      })
      
      if (checkInterval) {
        clearInterval(checkInterval)
      }
    })
  })
  
  return {
    // 狀態
    isActive,
    lastActivity,
    timeSinceLastActivity,
    isLongInactive,
    
    // 統計
    getSessionStats,
    activityHistory,
    activityPatterns,
    getActivityLevel,
    
    // 方法
    updateActivity,
    resetActivityStats,
    onActivityChange
  }
}