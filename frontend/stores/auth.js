export const useAuthStore = defineStore('auth', () => {
  // 用戶狀態
  const user = ref(null)
  const isLoggedIn = computed(() => !!user.value)
  
  // 權限檢查
  const isExecutive = computed(() => user.value?.role === roles.EXECUTIVE)
  const isAdmin = computed(() => user.value?.role === roles.ADMIN)
  const isManager = computed(() => user.value?.role === roles.MANAGER)
  const isStaff = computed(() => user.value?.role === roles.STAFF)
  
  // 檢查特定權限
  const hasPermission = (permission) => {
    if (!user.value) return false
    return user.value.permissions?.includes('all_access') || user.value.permissions?.includes(permission)
  }
  
  // 權限角色定義（與後端保持一致）
  const roles = {
    ADMIN: 'admin', // 系統管理員
    EXECUTIVE: 'executive', // 經銷商/公司高層
    MANAGER: 'manager', // 行政人員/主管
    STAFF: 'staff' // 業務人員
  }

  // 移除模擬用戶數據，改為完全使用 API

  // 登入功能
  const login = async (credentials) => {
    try {
      const { post } = useApi()
      
      // 使用統一的 API composable
      const { data: response, error } = await post('/auth/login', {
        username: credentials.username,  // 後端期望 username 欄位
        password: credentials.password
      })
      
      if (error) {
        throw new Error(error.message || '登入失敗')
      }
      
      if (response.access_token && response.user) {
        // 設定用戶資料，確保包含 token 和主要角色
        const userData = {
          ...response.user,
          token: response.access_token,  // 後端回傳 access_token
          role: response.user.roles?.[0] || null  // 取得主要角色（第一個角色）
        }
        
        user.value = userData
        
        // 不儲存到 localStorage - 每次都需要重新登入
        console.log('登入成功，會話模式啟動（不持久化）')
        
        return { success: true, user: userData }
      } else {
        throw new Error('登入回應格式錯誤')
      }
    } catch (error) {
      console.error('Login failed:', error)
      throw new Error(error.message || '登入失敗，請檢查您的帳號密碼')
    }
  }

  // 註冊功能
  const register = async (userData) => {
    try {
      const { post } = useApi()
      
      const { data: response, error } = await post('/auth/register', userData)
      
      if (error) {
        throw new Error(error.message || '註冊失敗，請稍後再試')
      }
      
      return { success: true, message: response.message || '註冊成功，請使用您的帳號密碼登入' }
    } catch (error) {
      console.error('Registration failed:', error)
      throw new Error(error.message || '註冊失敗，請稍後再試')
    }
  }

  // 登出功能
  const logout = () => {
    user.value = null
    
    // 確保清除任何可能的 localStorage 殘留
    if (process.client) {
      localStorage.removeItem('admin-template-user')
    }
    
    console.log('已登出，會話結束')
    
    // 重定向到登入頁面
    navigateTo('/auth/login')
  }

  // 設定用戶資料
  const setUser = (userData) => {
    user.value = userData
  }

  // 初始化用戶狀態 - 不自動恢復登入狀態，用戶每次都需要重新登入
  const initializeAuth = () => {
    if (process.client) {
      // 清除任何舊的登入狀態
      const storedUser = localStorage.getItem('admin-template-user')
      if (storedUser) {
        localStorage.removeItem('admin-template-user')
        console.log('已清除舊的登入狀態，請重新登入')
      }
      // 確保用戶狀態為空
      user.value = null
    }
  }

  // 所有用戶管理功能現在都透過 useUserManagement composable 處理

  return {
    // 狀態
    user: readonly(user),
    isLoggedIn,
    isAdmin,
    isExecutive,
    isManager,
    isStaff,
    roles,
    
    // 方法
    login,
    register,
    logout,
    setUser,
    initializeAuth,
    hasPermission
  }
})