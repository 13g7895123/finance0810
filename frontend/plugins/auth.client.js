export default defineNuxtPlugin(async () => {
  // Initialize auth store on client side only
  const authStore = useAuthStore()
  
  // Initialize authentication state on app startup using singleton pattern
  // This prevents race conditions with middleware
  try {
    const initSuccess = await authStore.waitForInitialization()
    console.log('Auth plugin - 初始化結果:', initSuccess)
    console.log('Auth plugin - 登入狀態:', authStore.isLoggedIn)
    
  } catch (error) {
    console.warn('Auth plugin - 初始化失敗:', error)
  }
})