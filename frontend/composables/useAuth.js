/**
 * Authentication Composable
 * 處理用戶認證相關功能
 */

export const useAuth = () => {
  const { post, get } = useApi()
  
  const user = ref(null)
  const token = useCookie('auth-token', {
    default: () => null,
    httpOnly: false,
    secure: true,
    sameSite: 'strict'
  })

  /**
   * 登入
   */
  const login = async (credentials) => {
    const { data, error } = await post('/auth/login', credentials)
    
    if (error) {
      return { success: false, error }
    }

    // 儲存Token和用戶資訊
    token.value = data.access_token
    user.value = data.user
    
    // 儲存到localStorage作為備份
    if (process.client) {
      localStorage.setItem('finance_user', JSON.stringify(data.user))
    }

    return { success: true, data }
  }

  /**
   * 登出
   */
  const logout = async () => {
    try {
      // 呼叫後端登出API
      await post('/auth/logout')
    } catch (error) {
      console.warn('Backend logout failed:', error)
    }

    // 清除本地資料
    token.value = null
    user.value = null
    
    if (process.client) {
      localStorage.removeItem('finance_user')
    }

    // 重導向到登入頁
    await navigateTo('/auth/login')
  }

  /**
   * 獲取當前用戶資訊
   */
  const getMe = async () => {
    const { data, error } = await get('/auth/me')
    
    if (error) {
      return { success: false, error }
    }

    user.value = data.user
    
    if (process.client) {
      localStorage.setItem('finance_user', JSON.stringify(data.user))
    }

    return { success: true, data: data.user }
  }

  /**
   * 刷新Token
   */
  const refreshToken = async () => {
    const { data, error } = await post('/auth/refresh')
    
    if (error) {
      // 刷新失敗，可能Token已過期
      await logout()
      return { success: false, error }
    }

    token.value = data.access_token
    user.value = data.user

    return { success: true, data }
  }

  /**
   * 檢查用戶是否已登入
   */
  const isAuthenticated = computed(() => {
    return !!token.value && !!user.value
  })

  /**
   * 檢查用戶權限
   */
  const hasRole = (roles) => {
    if (!user.value?.roles) return false
    
    const userRoles = user.value.roles
    if (Array.isArray(roles)) {
      return roles.some(role => userRoles.includes(role))
    }
    return userRoles.includes(roles)
  }

  /**
   * 檢查用戶權限
   */
  const hasPermission = (permissions) => {
    if (!user.value?.permissions) return false
    
    const userPermissions = user.value.permissions
    if (Array.isArray(permissions)) {
      return permissions.some(permission => userPermissions.includes(permission))
    }
    return userPermissions.includes(permissions)
  }

  /**
   * 檢查是否為管理員
   */
  const isAdmin = computed(() => {
    return user.value?.is_admin || hasRole(['admin', 'executive'])
  })

  /**
   * 檢查是否為管理者
   */
  const isManager = computed(() => {
    return user.value?.is_manager || hasRole(['admin', 'executive', 'manager'])
  })

  /**
   * 初始化認證狀態 - 移除自動登入功能
   */
  const initAuth = () => {
    if (process.client) {
      // 清除任何舊的認證數據，用戶必須手動登入
      localStorage.removeItem('finance_user')
      console.log('已清除自動登入功能，請手動登入')
    }
  }

  // 在組件掛載時初始化
  onMounted(() => {
    initAuth()
  })

  return {
    // 狀態
    user: readonly(user),
    token: readonly(token),
    isAuthenticated,
    isAdmin,
    isManager,
    
    // 方法
    login,
    logout,
    getMe,
    refreshToken,
    hasRole,
    hasPermission,
    initAuth
  }
}