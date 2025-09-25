export default defineNuxtRouteMiddleware(async (to) => {
  try {
    const authStore = useAuthStore()
    const config = useRuntimeConfig()

  console.log('Guest middleware - 檢查認證狀態:', authStore.isLoggedIn)
  console.log('Guest middleware - 目標路徑:', to.path)
  console.log('Guest middleware - redirect 參數:', to.query.redirect)
  console.log('Guest middleware - 跳過認證模式:', config.public.skipAuth)

  // Point 3: Development convenience mode - skip guest restrictions if enabled
  if (config.public.skipAuth) {
    console.log('跳過認證模式已啟用，允許訪問所有頁面')
    return
  }

  // 在客戶端初始化認證狀態（使用單例模式）
  if (process.client) {
    try {
      await authStore.waitForInitialization()
      console.log('Guest middleware - 初始化完成，登入狀態:', authStore.isLoggedIn)
    } catch (error) {
      console.warn('Guest middleware 初始化失敗:', error)
    }
  }
  
  // 如果已經登入，重定向到指定頁面或首頁
  if (authStore.isLoggedIn) {
    const redirectPath = to.query.redirect || '/dashboard/analytics'
    console.log('用戶已登入，從登入頁重定向到:', redirectPath)
    
    // 避免重定向到登入頁自己
    if (redirectPath === '/auth/login') {
      return navigateTo('/dashboard/analytics')
    }
    
    return navigateTo(redirectPath)
  }
  } catch (error) {
    console.error('Guest middleware - useAuthStore failed:', error)
    // If auth store is not available, allow guest access (stay on current page)
    return
  }
})