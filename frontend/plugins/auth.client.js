export default defineNuxtPlugin(async () => {
  // Initialize auth store on client side only
  const authStore = useAuthStore()
  
  // Initialize authentication state on app startup
  // This prevents race conditions with middleware
  try {
    const initSuccess = await authStore.initializeAuth()
    console.log('Auth plugin - 初始化結果:', initSuccess)
    console.log('Auth plugin - 登入狀態:', authStore.isLoggedIn)
    
    // 標記為已初始化，避免中間件重複執行
    authStore._initialized.value = true
    
  } catch (error) {
    console.warn('Auth plugin - 初始化失敗:', error)
    authStore._initialized.value = true // 即使失敗也標記為已嘗試過
  }
})