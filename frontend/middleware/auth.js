export default defineNuxtRouteMiddleware(async (to) => {
  const authStore = useAuthStore()
  
  // 初始化認證狀態 - 確保非同步完成
  if (process.client) {
    await nextTick() // 確保 DOM 已準備好
    authStore.initializeAuth()
  }
  
  // 如果未登入，重定向到登入頁面
  if (!authStore.isLoggedIn) {
    return navigateTo('/auth/login')
  }
})