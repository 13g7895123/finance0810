import { ref } from 'vue'

export const useFallbackPolling = () => {
  const isFallbackMode = ref(false)
  let fallbackInterval = null
  
  /**
   * 啟用降級輪詢
   */
  const enableFallback = (fetchFunction, interval = 5000) => {
    console.warn('Enabling fallback polling mode')
    isFallbackMode.value = true
    
    // 清除可能存在的舊定時器
    if (fallbackInterval) {
      clearInterval(fallbackInterval)
    }
    
    // 立即執行一次
    fetchFunction()
    
    // 設置定時輪詢
    fallbackInterval = setInterval(fetchFunction, interval)
  }
  
  /**
   * 停用降級輪詢
   */
  const disableFallback = () => {
    console.log('Disabling fallback polling mode')
    isFallbackMode.value = false
    
    if (fallbackInterval) {
      clearInterval(fallbackInterval)
      fallbackInterval = null
    }
  }
  
  /**
   * 檢查是否需要降級
   */
  const checkFallbackNeeded = (errorCount, maxErrors = 5) => {
    return errorCount >= maxErrors
  }
  
  return {
    isFallbackMode,
    enableFallback,
    disableFallback,
    checkFallbackNeeded
  }
}