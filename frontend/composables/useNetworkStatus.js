import { ref, onMounted, onUnmounted } from 'vue'

export const useNetworkStatus = () => {
  const isOnline = ref(true)
  const connectionType = ref('unknown')
  const connectionSpeed = ref(null)
  const lastOnlineTime = ref(null)
  const lastOfflineTime = ref(null)
  
  const updateOnlineStatus = () => {
    const wasOnline = isOnline.value
    isOnline.value = navigator.onLine
    
    if (wasOnline && !isOnline.value) {
      lastOfflineTime.value = Date.now()
      console.warn('Network connection lost')
    } else if (!wasOnline && isOnline.value) {
      lastOnlineTime.value = Date.now()
      console.log('Network connection restored')
    }
    
    // 更新連接類型
    if ('connection' in navigator) {
      const connection = navigator.connection
      connectionType.value = connection.effectiveType || 'unknown'
      connectionSpeed.value = connection.downlink || null
    }
  }
  
  onMounted(() => {
    updateOnlineStatus()
    window.addEventListener('online', updateOnlineStatus)
    window.addEventListener('offline', updateOnlineStatus)
    
    // 監聽連接變化
    if ('connection' in navigator && navigator.connection) {
      navigator.connection.addEventListener('change', updateOnlineStatus)
    }
  })
  
  onUnmounted(() => {
    window.removeEventListener('online', updateOnlineStatus)
    window.removeEventListener('offline', updateOnlineStatus)
    
    if ('connection' in navigator && navigator.connection) {
      navigator.connection.removeEventListener('change', updateOnlineStatus)
    }
  })
  
  return {
    isOnline,
    connectionType,
    connectionSpeed,
    lastOnlineTime,
    lastOfflineTime
  }
}