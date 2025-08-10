export default defineNuxtPlugin(async () => {
  // Initialize auth store on client side only
  const authStore = useAuthStore()
  
  // Initialize authentication state
  try {
    await authStore.initializeAuth()
    console.log('Auth plugin initialized successfully')
  } catch (error) {
    console.warn('Auth initialization failed:', error)
  }
})