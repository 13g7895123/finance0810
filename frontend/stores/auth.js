export const useAuthStore = defineStore('auth', () => {
  // 用戶狀態
  const user = ref(null)
  const isLoggedIn = computed(() => !!user.value)
  
  // 權限檢查
  const isDealer = computed(() => user.value?.role === roles.DEALER_EXECUTIVE)
  const isAdmin = computed(() => user.value?.role === roles.ADMIN_MANAGER)
  const isSales = computed(() => user.value?.role === roles.SALES_STAFF)
  
  // 檢查特定權限
  const hasPermission = (permission) => {
    if (!user.value) return false
    return user.value.permissions?.includes('all_access') || user.value.permissions?.includes(permission)
  }
  
  // 權限角色定義
  const roles = {
    DEALER_EXECUTIVE: 'dealer_executive', // 經銷商/公司高層
    ADMIN_MANAGER: 'admin_manager', // 行政人員/主管
    SALES_STAFF: 'sales_staff' // 業務人員
  }

  // 移除模擬用戶數據，改為完全使用 API

  // 登入功能
  const login = async (credentials) => {
    try {
      const config = useRuntimeConfig()
      
      // 只使用真實 API 登入
      const response = await $fetch('/login', {
        baseURL: config.public.apiBaseUrl || '/api',
        method: 'POST',
        body: {
          email: credentials.username,  // 支援 email 或 username
          password: credentials.password
        },
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      })
      
      if (response.success) {
        // 設定用戶資料，確保包含 token
        const userData = {
          ...response.user,
          token: response.token
        }
        
        user.value = userData
        
        // 不儲存到 localStorage - 每次都需要重新登入
        console.log('登入成功，會話模式啟動（不持久化）')
        
        return { success: true, user: userData }
      } else {
        throw new Error(response.message || '登入失敗')
      }
    } catch (error) {
      console.error('Login failed:', error)
      throw new Error(error.data?.message || error.message || '登入失敗，請檢查您的帳號密碼')
    }
  }

  // 註冊功能
  const register = async (userData) => {
    try {
      const config = useRuntimeConfig()
      
      const response = await $fetch('/register', {
        baseURL: config.public.apiBaseUrl || '/api',
        method: 'POST',
        body: userData,
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      })
      
      return { success: true, message: response.message || '註冊成功，請使用您的帳號密碼登入' }
    } catch (error) {
      console.error('Registration failed:', error)
      throw new Error(error.data?.message || error.message || '註冊失敗，請稍後再試')
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
    isDealer,
    isSales,
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