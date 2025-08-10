export default defineNuxtPlugin(() => {
  // This plugin only runs on client side due to .client.js suffix
  const themeStore = useThemeStore()
  
  // Initialize theme colors with error handling
  try {
    themeStore.initializeTheme()
  } catch (error) {
    console.warn('Theme initialization failed:', error)
  }
})