export default defineNuxtRouteMiddleware(async (to, from) => {
  try {
    const authStore = useAuthStore()
    const config = useRuntimeConfig()

  console.log('Auth middleware - 來源頁面:', from?.path, '目標頁面:', to.path)
  console.log('Auth middleware - 當前登入狀態:', authStore.isLoggedIn)
  console.log('Auth middleware - 跳過認證模式:', config.public.skipAuth)

  // Point 3: Development convenience mode - skip authentication if enabled
  if (config.public.skipAuth) {
    console.log('跳過認證模式已啟用，允許直接通過')
    return
  }

  // 如果是登入頁面，直接允許通過
  if (to.path === '/auth/login') {
    return
  }
  
  // 如果已經登入，直接允許通過（避免重複驗證）
  if (authStore.isLoggedIn) {
    console.log('用戶已登入，直接通過')
    return
  }
  
  // 等待初始化完成 - 使用單例模式，避免重複初始化
  if (process.client) {
    try {
      await nextTick() // 確保 DOM 已準備好
      const authSuccess = await authStore.waitForInitialization()
      
      console.log('Auth middleware - 等待初始化結果:', authSuccess)
      console.log('Auth middleware - 初始化後登入狀態:', authStore.isLoggedIn)
      
      // 如果初始化後仍未登入，給一點額外時間等待狀態更新
      if (!authStore.isLoggedIn) {
        console.log('等待狀態更新...')
        await new Promise(resolve => setTimeout(resolve, 50))
        
        // 再次檢查登入狀態
        if (!authStore.isLoggedIn) {
          console.log('確認用戶未登入，重定向到登入頁')
          // 保存原始目標路徑，避免循環重定向
          const redirectPath = to.path !== '/' && to.path !== '/auth/login' ? to.path : undefined
          return navigateTo(redirectPath ? `/auth/login?redirect=${encodeURIComponent(redirectPath)}` : '/auth/login')
        } else {
          console.log('狀態更新後確認用戶已登入，允許通過')
        }
      }
      
    } catch (error) {
      console.error('Auth middleware - 等待初始化失敗:', error)
      // 保存原始目標路徑
      const redirectPath = to.path !== '/' && to.path !== '/auth/login' ? to.path : undefined
      return navigateTo(redirectPath ? `/auth/login?redirect=${encodeURIComponent(redirectPath)}` : '/auth/login')
    }
  } else {
    // Server side: 如果沒有登入狀態就重定向
    if (!authStore.isLoggedIn) {
      // 保存原始目標路徑
      const redirectPath = to.path !== '/' ? to.path : undefined
      return navigateTo(redirectPath ? `/auth/login?redirect=${encodeURIComponent(redirectPath)}` : '/auth/login')
    }
  }
  } catch (error) {
    console.error('Auth middleware - useAuthStore failed:', error)
    // If auth store is not available, redirect to login
    const redirectPath = to.path !== '/' && to.path !== '/auth/login' ? to.path : undefined
    return navigateTo(redirectPath ? `/auth/login?redirect=${encodeURIComponent(redirectPath)}` : '/auth/login')
  }
})