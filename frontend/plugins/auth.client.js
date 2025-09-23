export default defineNuxtPlugin(async () => {
  // Initialize auth store on client side only
  const authStore = useAuthStore()
  const config = useRuntimeConfig()

  // Point 3: Log skip auth mode status
  console.log('Auth plugin - 跳過認證模式:', config.public.skipAuth)

  // Initialize authentication state on app startup using singleton pattern
  // This prevents race conditions with middleware
  try {
    const initSuccess = await authStore.waitForInitialization()
    console.log('Auth plugin - 初始化結果:', initSuccess)
    console.log('Auth plugin - 登入狀態:', authStore.isLoggedIn)

    // Point 3: Additional logging for skip auth mode
    if (config.public.skipAuth) {
      console.log('Auth plugin - 跳過認證模式已啟用，模擬用戶:', authStore.user?.username)
    }

  } catch (error) {
    console.warn('Auth plugin - 初始化失敗:', error)
  }
})