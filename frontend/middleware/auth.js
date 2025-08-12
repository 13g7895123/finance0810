export default defineNuxtRouteMiddleware(async (to, from) => {
  const authStore = useAuthStore()
  
  console.log('Auth middleware - 來源頁面:', from?.path, '目標頁面:', to.path)
  console.log('Auth middleware - 當前登入狀態:', authStore.isLoggedIn)
  
  // 如果是登入頁面，直接允許通過
  if (to.path === '/auth/login') {
    return
  }
  
  // 如果已經登入，直接允許通過（避免重複驗證）
  if (authStore.isLoggedIn) {
    console.log('用戶已登入，直接通過')
    return
  }
  
  // 初始化認證狀態 - 確保非同步完成
  if (process.client) {
    try {
      await nextTick() // 確保 DOM 已準備好
      const initSuccess = await authStore.initializeAuth() // 等待初始化完成
      
      console.log('Auth middleware - 初始化結果:', initSuccess)
      console.log('Auth middleware - 初始化後登入狀態:', authStore.isLoggedIn)
      
      // 如果初始化失敗且確實沒有登入狀態，才重定向
      if (!initSuccess && !authStore.isLoggedIn) {
        console.log('初始化失敗且用戶未登入，重定向到登入頁')
        return navigateTo('/auth/login')
      }
      
    } catch (error) {
      console.error('Auth initialization failed:', error)
      // 初始化異常且沒有登入狀態時才重定向
      if (!authStore.isLoggedIn) {
        return navigateTo('/auth/login')
      }
    }
  }
  
  // 最終檢查登入狀態
  if (!authStore.isLoggedIn) {
    console.log('用戶未登入，重定向到登入頁')
    return navigateTo('/auth/login')
  }
})