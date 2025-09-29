/**
 * Error Monitoring Composable
 * 監控和處理重複錯誤，特別是認證相關的錯誤
 */

export const useErrorMonitoring = () => {
  const router = useRouter()
  const config = useRuntimeConfig()

  // 錯誤計數器
  const errorCounts = ref(new Map())
  const authFailureCount = ref(0)
  const lastAuthFailureTime = ref(null)

  // 錯誤閾值配置
  const ERROR_THRESHOLDS = {
    AUTH_FAILURE_MAX: 3, // 最多 3 次認證失敗
    AUTH_FAILURE_WINDOW: 5 * 60 * 1000, // 5 分鐘內
    GENERAL_ERROR_MAX: 5, // 一般錯誤最多 5 次
    GENERAL_ERROR_WINDOW: 2 * 60 * 1000 // 2 分鐘內
  }

  /**
   * 記錄錯誤
   */
  const recordError = (errorType, errorDetail = null) => {
    const now = Date.now()
    const key = errorType

    if (!errorCounts.value.has(key)) {
      errorCounts.value.set(key, [])
    }

    const errors = errorCounts.value.get(key)
    errors.push({ timestamp: now, detail: errorDetail })

    // 清理過期的錯誤記錄
    const window = errorType === 'auth_failure' ? ERROR_THRESHOLDS.AUTH_FAILURE_WINDOW : ERROR_THRESHOLDS.GENERAL_ERROR_WINDOW
    const validErrors = errors.filter(error => now - error.timestamp < window)
    errorCounts.value.set(key, validErrors)

    console.log(`Recorded error: ${errorType}, count in window: ${validErrors.length}`)
  }

  /**
   * 檢查錯誤是否超過閾值
   */
  const isErrorThresholdExceeded = (errorType) => {
    const errors = errorCounts.value.get(errorType) || []
    const maxErrors = errorType === 'auth_failure' ? ERROR_THRESHOLDS.AUTH_FAILURE_MAX : ERROR_THRESHOLDS.GENERAL_ERROR_MAX

    return errors.length >= maxErrors
  }

  /**
   * 處理認證失敗
   */
  const handleAuthFailure = async (error) => {
    if (config.public.skipAuth) return false

    const now = Date.now()
    authFailureCount.value++
    lastAuthFailureTime.value = now

    recordError('auth_failure', {
      status: error.status,
      message: error.message,
      endpoint: error.endpoint
    })

    console.warn(`Authentication failure ${authFailureCount.value}:`, error)

    // 如果認證失敗次數過多，強制重新登入
    if (isErrorThresholdExceeded('auth_failure')) {
      console.error('Too many authentication failures, forcing logout')

      // 清除所有認證資料
      if (process.client) {
        sessionStorage.clear()
        localStorage.removeItem('auth-token')
        localStorage.removeItem('admin-template-user')
        localStorage.removeItem('user-profile')
      }

      // 顯示錯誤訊息
      const { error: showError } = useNotification()
      showError('認證失效過多次，請重新登入')

      // 重定向到登入頁面
      await router.push('/auth/login')
      return true
    }

    return false
  }

  /**
   * 處理500錯誤
   */
  const handleServerError = async (error) => {
    recordError('server_error', {
      status: error.status,
      message: error.message,
      endpoint: error.endpoint,
      userAgent: navigator.userAgent
    })

    console.error('Server error recorded:', error)

    // 如果是認證相關的500錯誤，特別處理
    if (error.message && (
      error.message.includes('Token') ||
      error.message.includes('JWT') ||
      error.message.includes('Unauthenticated') ||
      error.message.includes('authentication')
    )) {
      console.warn('Server error appears to be authentication-related')
      return await handleAuthFailure(error)
    }

    // 如果500錯誤過多，提示用戶
    if (isErrorThresholdExceeded('server_error')) {
      const { error: showError } = useNotification()
      showError('系統暫時不穩定，請稍後再試或聯繫管理員')

      // 記錄詳細錯誤給技術人員
      console.error('Multiple server errors detected:', {
        errors: errorCounts.value.get('server_error'),
        userAgent: navigator.userAgent,
        currentPage: router.currentRoute.value.fullPath
      })
    }

    return false
  }

  /**
   * 通用錯誤處理
   */
  const handleError = async (error, context = {}) => {
    const errorInfo = {
      status: error.status || error.statusCode,
      message: error.message || error.data?.message,
      endpoint: context.endpoint,
      timestamp: Date.now(),
      context
    }

    console.group(`Error Handler: ${errorInfo.status || 'Unknown'}`)
    console.error('Error details:', errorInfo)

    let handled = false

    // 根據錯誤類型進行特定處理
    switch (errorInfo.status) {
      case 401:
        handled = await handleAuthFailure(errorInfo)
        break
      case 403:
        recordError('forbidden', errorInfo)
        const { error: showForbiddenError } = useNotification()
        showForbiddenError('沒有權限執行此操作')
        break
      case 500:
        handled = await handleServerError(errorInfo)
        break
      default:
        recordError('general_error', errorInfo)
    }

    console.groupEnd()
    return handled
  }

  /**
   * 重置錯誤計數器
   */
  const resetErrorCounts = (errorType = null) => {
    if (errorType) {
      errorCounts.value.delete(errorType)
      if (errorType === 'auth_failure') {
        authFailureCount.value = 0
        lastAuthFailureTime.value = null
      }
    } else {
      errorCounts.value.clear()
      authFailureCount.value = 0
      lastAuthFailureTime.value = null
    }

    console.log('Error counts reset for:', errorType || 'all')
  }

  /**
   * 獲取錯誤統計
   */
  const getErrorStats = () => {
    const stats = {}

    for (const [errorType, errors] of errorCounts.value.entries()) {
      stats[errorType] = {
        count: errors.length,
        latestError: errors.length > 0 ? errors[errors.length - 1] : null
      }
    }

    return {
      ...stats,
      authFailureCount: authFailureCount.value,
      lastAuthFailureTime: lastAuthFailureTime.value
    }
  }

  return {
    recordError,
    handleError,
    handleAuthFailure,
    handleServerError,
    resetErrorCounts,
    getErrorStats,
    isErrorThresholdExceeded,
    authFailureCount: readonly(authFailureCount),
    lastAuthFailureTime: readonly(lastAuthFailureTime)
  }
}