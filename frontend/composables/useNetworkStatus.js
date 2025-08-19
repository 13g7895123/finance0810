import { ref, onMounted, onUnmounted } from 'vue'

export const useNetworkStatus = () => {
  const isOnline = ref(navigator.onLine)
  const connectionType = ref('')
  const connectionSpeed = ref('')
  
  /**
   * 更新連接信息
   */
  const updateConnectionInfo = () => {
    if ('connection' in navigator) {
      const connection = navigator.connection
      connectionType.value = connection.effectiveType || 'unknown'
      connectionSpeed.value = connection.downlink ? `${connection.downlink}Mbps` : 'unknown'
    }
  }
  
  /**
   * 處理在線狀態
   */
  const handleOnline = () => {
    console.log('Network: Online')
    isOnline.value = true
    updateConnectionInfo()
  }
  
  /**
   * 處理離線狀態
   */
  const handleOffline = () => {
    console.log('Network: Offline')
    isOnline.value = false
  }
  
  /**
   * 處理連接變化
   */
  const handleConnectionChange = () => {
    console.log('Network: Connection changed')
    updateConnectionInfo()
  }
  
  onMounted(() => {
    // 初始化連接信息
    updateConnectionInfo()
    
    // 監聽網絡事件
    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)
    
    if ('connection' in navigator) {
      navigator.connection.addEventListener('change', handleConnectionChange)
    }
  })
  
  onUnmounted(() => {
    window.removeEventListener('online', handleOnline)
    window.removeEventListener('offline', handleOffline)
    
    if ('connection' in navigator) {
      navigator.connection.removeEventListener('change', handleConnectionChange)
    }
  })
  
  return {
    isOnline,
    connectionType,
    connectionSpeed
  }
}