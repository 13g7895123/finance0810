export default defineNuxtPlugin(() => {
  // Force light mode on client side
  if (process.client) {
    // Remove any dark mode classes and force light mode
    document.documentElement.classList.remove('dark')
    document.documentElement.classList.add('light')
    document.body.classList.remove('dark')
    document.body.classList.add('light')
    
    // Set color mode in localStorage to prevent future dark mode activation
    localStorage.setItem('nuxt-ui-color-mode', 'light')
    localStorage.setItem('vueuse-color-scheme', 'light')
    localStorage.setItem('color-mode', 'light')
    localStorage.setItem('nuxt-color-mode', 'light')
    
    // Override any system preference detection
    try {
      const colorMode = useColorMode()
      if (colorMode) {
        colorMode.preference = 'light'
        colorMode.value = 'light'
      }
    } catch (error) {
      console.log('ColorMode composable not available:', error.message)
    }
    
    // Force light mode CSS variables
    document.documentElement.style.setProperty('--color-mode', 'light')
    
    console.log('Light mode forced successfully')
  }
})