import { defineStore } from 'pinia'
import { ref, computed, readonly, nextTick } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  // 用戶狀態
  const user = ref(null)
  const isLoggedIn = computed(() => !!user.value && !!user.value.token)
  const token = computed(() => user.value?.token || null)
  
  // 初始化狀態追蹤
  const _isInitializing = ref(false)
  const _isInitialized = ref(false)
  const _initPromise = ref(null)
  
  // 權限檢查
  const isExecutive = computed(() => user.value?.role === roles.EXECUTIVE)
  const isAdmin = computed(() => user.value?.role === roles.ADMIN)
  const isManager = computed(() => user.value?.role === roles.MANAGER)
  const isStaff = computed(() => user.value?.role === roles.STAFF)
  
  // 檢查特定權限
  const hasPermission = (permission) => {
    if (!user.value) return false
    
    // Admin 和 Executive 角色自動擁有所有權限
    if (user.value.role === 'admin' || user.value.role === 'executive') {
      return true
    }
    
    // 檢查是否有 all_access 權限（超級管理員）
    if (user.value.permissions?.includes('all_access')) {
      return true
    }
    
    // 檢查特定權限
    return user.value.permissions?.includes(permission)
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
      
      // 調試：檢查傳入的密碼
      console.log('Login credentials:', {
        username: credentials.username,
        password: credentials.password,
        passwordLength: credentials.password?.length,
        passwordType: typeof credentials.password
      })
      
      // 使用統一的 API composable
      const { data: response, error } = await post('/auth/login', {
        username: credentials.username,  // 後端期望 username 欄位
        password: credentials.password
      })
      
      if (error) {
        throw new Error(error.message || '登入失敗')
      }
      
      if (response.access_token && response.user) {
        // 調試：檢查後端回應格式
        console.log('Backend response:', {
          access_token: !!response.access_token,
          user: response.user,
          roles: response.user.roles,
          rolesType: typeof response.user.roles,
          rolesLength: response.user.roles?.length,
          firstRole: response.user.roles?.[0]
        })
        
        // 設定用戶資料，確保包含 token 和主要角色
        const userData = {
          ...response.user,
          token: response.access_token,  // 後端回傳 access_token
          role: Array.isArray(response.user.roles) 
            ? response.user.roles[0] 
            : (response.user.roles && response.user.roles[0]) || null  // 取得主要角色（第一個角色）
        }
        
        console.log('設定的用戶資料:', userData)
        user.value = userData
        
        // 強制觸發響應式更新
        await nextTick()
        
        // 檢查 user 是否正確設定
        console.log('設定後的 user.value:', user.value)
        console.log('設定後的 isLoggedIn:', isLoggedIn.value)
        
        // 儲存包含 JWT token 的用戶資料到 sessionStorage
        if (process.client) {
          sessionStorage.setItem('user-profile', JSON.stringify({
            id: userData.id,
            username: userData.username,
            email: userData.email,
            name: userData.name,
            roles: userData.roles,
            permissions: userData.permissions,
            is_admin: userData.is_admin,
            is_manager: userData.is_manager,
            token: userData.token  // 儲存 JWT token
          }))
        }
        console.log('登入成功，使用 JWT Token + SessionStorage')
        
        // 確保所有狀態更新完成
        await new Promise(resolve => setTimeout(resolve, 50))
        
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
  const logout = async () => {
    try {
      // 呼叫後端登出 API 清除 HTTP-Only Cookie
      const { post } = useApi()
      await post('/auth/logout')
    } catch (error) {
      console.error('Logout API error:', error)
    }
    
    user.value = null
    
    // 清除 sessionStorage 中的用戶資料
    if (process.client) {
      sessionStorage.removeItem('user-profile')
      // 不需要清除 localStorage，因為已經不使用了
      localStorage.removeItem('admin-template-user')
      localStorage.removeItem('auth-token')
    }
    
    console.log('已登出，清除會話資料')
    
    // 重定向到登入頁面
    navigateTo('/auth/login')
  }

  // 設定用戶資料
  const setUser = (userData) => {
    user.value = userData
  }

  // Point 3: Create mock admin user for development convenience mode
  const createMockAdminUser = () => {
    return {
      id: 1,
      username: 'admin',
      email: 'admin@finance.local',
      name: 'System Administrator (開發模式)',
      role: 'admin',
      roles: ['admin'],
      permissions: ['all_access'],
      is_admin: true,
      is_manager: true,
      token: 'mock_jwt_token_for_development_' + Date.now()
    }
  }

  // Point 3: Initialize skip auth mode with mock admin user
  const initializeSkipAuthMode = () => {
    console.log('初始化跳過認證模式 - 建立模擬 admin 用戶')
    const mockUser = createMockAdminUser()
    user.value = mockUser

    // Store mock user data in sessionStorage for consistency
    if (process.client) {
      sessionStorage.setItem('user-profile', JSON.stringify({
        id: mockUser.id,
        username: mockUser.username,
        email: mockUser.email,
        name: mockUser.name,
        roles: mockUser.roles,
        permissions: mockUser.permissions,
        is_admin: mockUser.is_admin,
        is_manager: mockUser.is_manager,
        token: mockUser.token
      }))
      console.log('模擬 admin 用戶已建立:', mockUser.username)
    }

    return true
  }

  // 初始化用戶狀態 - 單例模式，防止多次並發初始化
  const initializeAuth = async (force = false) => {
    // Point 3: Check skip auth mode first
    const config = useRuntimeConfig()
    if (config.public.skipAuth && process.client) {
      console.log('跳過認證模式已啟用')
      if (!user.value || force) {
        return initializeSkipAuthMode()
      }
      return isLoggedIn.value
    }

    // 如果已經初始化完成且不是強制重新初始化，直接返回結果
    if (_isInitialized.value && !force) {
      console.log('Auth already initialized, skipping')
      return isLoggedIn.value
    }

    // 如果正在初始化中，返回現有的 Promise
    if (_isInitializing.value && _initPromise.value && !force) {
      console.log('Auth initialization in progress, waiting for existing promise')
      try {
        return await _initPromise.value
      } catch (error) {
        console.error('Failed to wait for existing initialization:', error)
        return false
      }
    }

    if (!process.client) {
      return false
    }

    // 開始初始化
    _isInitializing.value = true
    
    const initPromise = (async () => {
      try {
        console.log('Starting auth initialization...')
        
        // 嘗試從 sessionStorage 恢復用戶資料
        const storedProfile = sessionStorage.getItem('user-profile')
        
        if (storedProfile) {
          try {
            const userProfile = JSON.parse(storedProfile)
            
            // 檢查是否有 JWT token
            if (!userProfile.token) {
              console.log('No JWT token found in stored profile')
              sessionStorage.removeItem('user-profile')
              user.value = null
              return false
            }
            
            // 驗證 JWT token 是否有效（通過 API 呼叫測試）
            try {
              const { get } = useApi()
              const { data: userData, error } = await get('/auth/me')
              
              if (!error && userData?.user) {
                // Token 有效，恢復用戶狀態，保留 token
                user.value = {
                  ...userData.user,
                  token: userProfile.token,  // 保留原始 token
                  role: Array.isArray(userData.user.roles) 
                    ? userData.user.roles[0] 
                    : (userData.user.roles && userData.user.roles[0]) || null
                }
                console.log('已恢復登入狀態:', userData.user.username)
                return true // 成功恢復登入狀態
              } else {
                // Token 無效，清除資料
                console.log('JWT token is invalid')
                sessionStorage.removeItem('user-profile')
                user.value = null
                return false
              }
            } catch (apiError) {
              console.error('API 驗證失敗:', apiError)
              // API 呼叫失敗時，保留用戶資料但標記為未驗證狀態
              // 這樣可以避免頁面刷新時立即重定向
              user.value = {
                ...userProfile,
                role: Array.isArray(userProfile.roles) 
                  ? userProfile.roles[0] 
                  : (userProfile.roles && userProfile.roles[0]) || null,
                _unverified: true // 標記為未驗證狀態
              }
              console.log('API 暫時無法連線，保留登入狀態但標記為未驗證')
              return true // 暫時允許通過
            }
          } catch (error) {
            console.error('解析用戶資料失敗:', error)
            sessionStorage.removeItem('user-profile')
            user.value = null
            return false
          }
        } else {
          user.value = null
          return false
        }
      } catch (error) {
        console.error('初始化認證失敗:', error)
        user.value = null
        return false
      } finally {
        // 清除舊的 localStorage 資料（向後相容）
        if (localStorage.getItem('admin-template-user') || localStorage.getItem('auth-token')) {
          localStorage.removeItem('admin-template-user')
          localStorage.removeItem('auth-token')
          console.log('已清除舊的 localStorage 資料')
        }
        
        // 標記初始化完成
        _isInitializing.value = false
        _isInitialized.value = true
        _initPromise.value = null
        console.log('Auth initialization completed')
      }
    })()
    
    // 保存 Promise 以供其他調用等待
    _initPromise.value = initPromise
    
    return await initPromise
  }
  
  // 等待初始化完成的輔助方法
  const waitForInitialization = async () => {
    if (_isInitialized.value) {
      return isLoggedIn.value
    }
    
    if (_isInitializing.value && _initPromise.value) {
      try {
        return await _initPromise.value
      } catch (error) {
        console.error('Failed to wait for initialization:', error)
        return false
      }
    }
    
    // 如果沒有進行初始化，啟動初始化
    return await initializeAuth()
  }

  // 所有用戶管理功能現在都透過 useUserManagement composable 處理

  return {
    // 狀態
    user: readonly(user),
    token,
    isLoggedIn,
    isAdmin,
    isExecutive,
    isManager,
    isStaff,
    roles,
    _isInitialized,
    _isInitializing,

    // 方法
    login,
    register,
    logout,
    setUser,
    initializeAuth,
    waitForInitialization,
    hasPermission,

    // Point 3: Development convenience mode methods
    createMockAdminUser,
    initializeSkipAuthMode
  }
})
