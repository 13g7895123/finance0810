export default defineNuxtRouteMiddleware(async (to) => {
  const authStore = useAuthStore()
  
  console.log('Guest middleware - 檢查認證狀態:', authStore.isLoggedIn)
  
  // 如果已經登入，重定向到首頁
  if (authStore.isLoggedIn) {
    console.log('用戶已登入，從登入頁重定向到首頁')
    return navigateTo('/')
  }
  
  // 在客戶端初始化認證狀態（不阻塞）
  if (process.client && !authStore.user) {
    try {
      await authStore.initializeAuth()
      // 再次檢查登入狀態
      if (authStore.isLoggedIn) {
        console.log('初始化後發現用戶已登入，重定向到首頁')
        return navigateTo('/')
      }
    } catch (error) {
      console.warn('Guest middleware 初始化失敗:', error)
    }
  }
})