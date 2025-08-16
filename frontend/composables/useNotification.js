/**
 * Notification Composable
 * 統一的通知管理，使用 SweetAlert2 替代原生 alert()
 */

import Swal from 'sweetalert2'

export const useNotification = () => {
  const notifications = ref([])
  
  // SweetAlert2 配置
  const defaultSwalConfig = {
    title: '系統提示',
    confirmButtonText: '確定',
    cancelButtonText: '取消',
    customClass: {
      confirmButton: 'btn btn-primary',
      cancelButton: 'btn btn-secondary'
    },
    buttonsStyling: false
  }
  
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
  
  // SweetAlert2 簡訊提示 - 替代 alert()
  const alert = (message, title = '系統提示', icon = 'info') => {
    return Swal.fire({
      ...defaultSwalConfig,
      title,
      text: message,
      icon,
      showConfirmButton: true
    })
  }
  
  // 便捷方法 - 使用 SweetAlert2，統一使用"系統提示"作為標題
  const success = (message, title = '系統提示') => {
    return Swal.fire({
      ...defaultSwalConfig,
      title,
      text: message,
      icon: 'success',
      timer: 3000,
      showConfirmButton: false,
      timerProgressBar: true
    })
  }
  
  const error = (message, title = '系統提示') => {
    return Swal.fire({
      ...defaultSwalConfig,
      title,
      text: message,
      icon: 'error',
      showConfirmButton: true
    })
  }
  
  const warning = (message, title = '系統提示') => {
    return Swal.fire({
      ...defaultSwalConfig,
      title,
      text: message,
      icon: 'warning',
      showConfirmButton: true
    })
  }
  
  const info = (message, title = '系統提示') => {
    return Swal.fire({
      ...defaultSwalConfig,
      title,
      text: message,
      icon: 'info',
      showConfirmButton: true
    })
  }
  
  // 確認對話框 - 使用 SweetAlert2
  const confirm = (message, title = '系統提示') => {
    return Swal.fire({
      ...defaultSwalConfig,
      title,
      text: message,
      icon: 'question',
      showCancelButton: true,
      showConfirmButton: true
    }).then((result) => {
      return result.isConfirmed
    })
  }
  
  // 輸入對話框
  const prompt = (message, title = '系統提示', placeholder = '') => {
    return Swal.fire({
      ...defaultSwalConfig,
      title,
      text: message,
      input: 'text',
      inputPlaceholder: placeholder,
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return '請輸入內容！'
        }
      }
    }).then((result) => {
      if (result.isConfirmed) {
        return result.value
      }
      return null
    })
  }
  
  // 載入中提示
  const loading = (message = '處理中...') => {
    Swal.fire({
      title: message,
      allowOutsideClick: false,
      showConfirmButton: false,
      willOpen: () => {
        Swal.showLoading()
      }
    })
  }
  
  // 關閉載入提示
  const closeLoading = () => {
    Swal.close()
  }

  return {
    notifications: readonly(notifications),
    showNotification,
    removeNotification,
    clearNotifications,
    alert,        // 替代原生 alert()
    success,
    error,
    warning,
    info,
    confirm,
    prompt,
    loading,
    closeLoading
  }
}