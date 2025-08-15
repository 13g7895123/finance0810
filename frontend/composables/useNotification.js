/**
 * Notification Composable
 * 統一的通知管理，替代原生 alert()
 */

export const useNotification = () => {
  const notifications = ref([])
  
  // 顯示通知
  const showNotification = (message, type = 'info', duration = 5000) => {
    const id = Date.now()
    const notification = {
      id,
      message,
      type, // 'success', 'error', 'warning', 'info'
      duration,
      show: true
    }
    
    notifications.value.push(notification)
    
    // 自動移除
    if (duration > 0) {
      setTimeout(() => {
        removeNotification(id)
      }, duration)
    }
    
    return id
  }
  
  // 移除通知
  const removeNotification = (id) => {
    const index = notifications.value.findIndex(n => n.id === id)
    if (index > -1) {
      notifications.value.splice(index, 1)
    }
  }
  
  // 清空所有通知
  const clearNotifications = () => {
    notifications.value = []
  }
  
  // 便捷方法
  const success = (message, duration) => showNotification(message, 'success', duration)
  const error = (message, duration) => showNotification(message, 'error', duration)
  const warning = (message, duration) => showNotification(message, 'warning', duration)
  const info = (message, duration) => showNotification(message, 'info', duration)
  
  // 確認對話框
  const confirm = (message, title = '確認') => {
    return new Promise((resolve) => {
      // 這裡可以實現自訂的確認對話框
      // 暫時使用原生 confirm，之後可以替換為自訂 Modal
      const result = window.confirm(`${title}\n\n${message}`)
      resolve(result)
    })
  }

  return {
    notifications: readonly(notifications),
    showNotification,
    removeNotification,
    clearNotifications,
    success,
    error,
    warning,
    info,
    confirm
  }
}