/**
 * Authentication Composable
 * 處理用戶認證相關功能
 */

export const useAuth = () => {
  const { get, post, put } = useApi()
  const router = useRouter()

  /**
   * 用戶登入
   */
  const login = async (credentials) => {
    const { data, error } = await post('/auth/login', credentials)
    
    if (error) {
      return { success: false, error }
    }

    // 登入成功，保存用戶資料到 sessionStorage
    if (process.client && data.user) {
      sessionStorage.setItem('user-profile', JSON.stringify(data.user))
    }

    return { 
      success: true, 
      user: data.user,
      token: data.access_token
    }
  }

  /**
   * 用戶登出
   */
  const logout = async () => {
    try {
      await post('/auth/logout')
    } catch (error) {
      console.warn('Logout API call failed:', error)
    }
    
    // 清除本地儲存的用戶資料
    if (process.client) {
      sessionStorage.removeItem('user-profile')
      // 清除舊的 localStorage 資料（向後相容）
      localStorage.removeItem('auth-token')
      localStorage.removeItem('admin-template-user')
    }
    
    // 重導向到登入頁
    await router.push('/auth/login')
  }

  /**
   * 獲取當前用戶資料
   */
  const getCurrentUser = async () => {
    const { data, error } = await get('/auth/me')
    
    if (error) {
      return { success: false, error, user: null }
    }

    // 更新本地儲存的用戶資料
    if (process.client && data.user) {
      sessionStorage.setItem('user-profile', JSON.stringify(data.user))
    }

    return { success: true, user: data.user }
  }

  /**
   * 更新個人資料
   */
  const updateProfile = async (profileData) => {
    const { data, error } = await put('/auth/profile', profileData)
    
    if (error) {
      return { success: false, error }
    }

    // 更新本地儲存的用戶資料
    if (process.client && data.user) {
      sessionStorage.setItem('user-profile', JSON.stringify(data.user))
    }

    return { 
      success: true, 
      user: data.user,
      message: data.message
    }
  }

  /**
   * 刷新 Token
   */
  const refreshToken = async () => {
    const { data, error } = await post('/auth/refresh')
    
    if (error) {
      return { success: false, error }
    }

    return { 
      success: true, 
      token: data.access_token,
      user: data.user
    }
  }

  /**
   * 檢查用戶是否已登入
   */
  const isAuthenticated = () => {
    if (!process.client) return false
    
    const userProfile = sessionStorage.getItem('user-profile')
    return !!userProfile
  }

  /**
   * 獲取本地儲存的用戶資料
   */
  const getLocalUser = () => {
    if (!process.client) return null
    
    const userProfile = sessionStorage.getItem('user-profile')
    return userProfile ? JSON.parse(userProfile) : null
  }

  /**
   * 檢查用戶權限
   */
  const hasPermission = (permission) => {
    const user = getLocalUser()
    if (!user || !user.permissions) return false
    
    return user.permissions.includes(permission)
  }

  /**
   * 檢查用戶角色
   */
  const hasRole = (role) => {
    const user = getLocalUser()
    if (!user || !user.roles) return false
    
    return user.roles.includes(role)
  }

  return {
    // 認證操作
    login,
    logout,
    getCurrentUser,
    updateProfile,
    refreshToken,
    
    // 狀態檢查
    isAuthenticated,
    getLocalUser,
    hasPermission,
    hasRole
  }
}