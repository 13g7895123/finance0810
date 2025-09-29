/**
 * Token Refresh Composable
 * 處理 JWT token 自動刷新和過期管理
 */

export const useTokenRefresh = () => {
  const authStore = useAuthStore()
  const router = useRouter()
  const config = useRuntimeConfig()

  // Token 刷新狀態
  const isRefreshing = ref(false)
  let refreshPromise = null

  /**
   * 檢查 token 是否即將過期
   */
  const isTokenExpiringSoon = (token) => {
    if (!token || config.public.skipAuth) return false

    try {
      // 解析 JWT token
      const payload = JSON.parse(atob(token.split('.')[1]))
      const exp = payload.exp * 1000 // 轉換為毫秒
      const now = Date.now()
      const timeToExpiry = exp - now

      // 如果在 30 分鐘內過期，認為即將過期
      return timeToExpiry < 30 * 60 * 1000
    } catch (error) {
      console.error('Error parsing token:', error)
      return true // 如果無法解析，認為需要刷新
    }
  }

  /**
   * 檢查 token 是否已經過期
   */
  const isTokenExpired = (token) => {
    if (!token || config.public.skipAuth) return false

    try {
      const payload = JSON.parse(atob(token.split('.')[1]))
      const exp = payload.exp * 1000
      return Date.now() >= exp
    } catch (error) {
      console.error('Error parsing token:', error)
      return true
    }
  }

  /**
   * 刷新 token
   */
  const refreshToken = async () => {
    // 如果已經在刷新中，返回現有的 Promise
    if (isRefreshing.value && refreshPromise) {
      return refreshPromise
    }

    isRefreshing.value = true
    refreshPromise = (async () => {
      try {
        const { post } = useApi()
        const { data: response, error } = await post('/auth/refresh')

        if (error || !response?.access_token) {
          throw new Error(error?.message || 'Token refresh failed')
        }

        // 更新用戶的 token
        if (authStore.user) {
          const updatedUser = {
            ...authStore.user,
            token: response.access_token
          }
          authStore.setUser(updatedUser)

          // 更新 sessionStorage
          if (process.client) {
            const storedProfile = sessionStorage.getItem('user-profile')
            if (storedProfile) {
              const profile = JSON.parse(storedProfile)
              profile.token = response.access_token
              sessionStorage.setItem('user-profile', JSON.stringify(profile))
            }
          }

          console.log('Token refreshed successfully')
          return response.access_token
        }

        throw new Error('No user found to update token')

      } catch (error) {
        console.error('Token refresh failed:', error)

        // 刷新失敗，清除認證狀態並重定向到登入頁
        if (process.client) {
          sessionStorage.removeItem('user-profile')
          localStorage.removeItem('auth-token')
          localStorage.removeItem('admin-template-user')
        }

        authStore.setUser(null)
        await router.push('/auth/login')

        throw error
      } finally {
        isRefreshing.value = false
        refreshPromise = null
      }
    })()

    return refreshPromise
  }

  /**
   * 檢查並刷新 token（如果需要）
   */
  const checkAndRefreshToken = async () => {
    if (config.public.skipAuth) return true

    const token = authStore.token
    if (!token) return false

    // 如果 token 已過期，嘗試刷新
    if (isTokenExpired(token)) {
      console.log('Token has expired, attempting refresh')
      try {
        await refreshToken()
        return true
      } catch (error) {
        console.error('Failed to refresh expired token:', error)
        return false
      }
    }

    // 如果 token 即將過期，預先刷新
    if (isTokenExpiringSoon(token)) {
      console.log('Token expiring soon, attempting refresh')
      try {
        await refreshToken()
        return true
      } catch (error) {
        console.error('Failed to refresh expiring token:', error)
        // 即使刷新失敗，如果 token 還沒過期，仍然允許繼續
        return !isTokenExpired(token)
      }
    }

    return true
  }

  /**
   * 處理 API 認證錯誤
   */
  const handleAuthError = async (error) => {
    if (config.public.skipAuth) return false

    // 如果是 401 錯誤且不是刷新請求
    if (error.status === 401 && !error.isRefreshRequest) {
      console.log('Received 401 error, attempting token refresh')

      try {
        await refreshToken()
        return true // 表示可以重試請求
      } catch (refreshError) {
        console.error('Token refresh failed after 401:', refreshError)
        return false // 重定向到登入頁面
      }
    }

    return false
  }

  return {
    isRefreshing: readonly(isRefreshing),
    isTokenExpiringSoon,
    isTokenExpired,
    refreshToken,
    checkAndRefreshToken,
    handleAuthError
  }
}