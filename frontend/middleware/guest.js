export default defineNuxtRouteMiddleware((to) => {
  const authStore = useAuthStore()
  
  // 初始化認證狀態
  authStore.initializeAuth()
  
  // 如果已經登入，重定向到首頁
  if (authStore.isLoggedIn) {
    return navigateTo('/')
  }
})