export default defineNuxtRouteMiddleware(async (to, from) => {
  // 暫時關閉前端驗證 - 直接放行
  return
  
  const authStore = useAuthStore()
  
  console.log('Auth middleware - 來源頁面:', from?.path, '目標頁面:', to.path)
  console.log('Auth middleware - 當前登入狀態:', authStore.isLoggedIn)
  
  // 如果已經登入，直接允許通過（避免重複驗證）
  if (authStore.isLoggedIn) {
    console.log('用戶已登入，直接通過')
    return
  }
  
  // 初始化認證狀態 - 確保非同步完成
  if (process.client) {
    await nextTick() // 確保 DOM 已準備好
    await authStore.initializeAuth() // 等待初始化完成
  }
  
  // 再次檢查登入狀態
  console.log('Auth middleware - 初始化後登入狀態:', authStore.isLoggedIn)
  
  // 如果未登入，重定向到登入頁面
  if (!authStore.isLoggedIn) {
    console.log('用戶未登入，重定向到登入頁')
    return navigateTo('/auth/login')
  }
})