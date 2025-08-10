export default defineNuxtPlugin(() => {
  // Initialize auth store on client side only
  const authStore = useAuthStore()
  
  // Initialize authentication state
  try {
    authStore.initializeAuth()
  } catch (error) {
    console.warn('Auth initialization failed:', error)
  }
})