export default defineAppConfig({
  ui: {
    primary: 'blue',
    gray: 'slate',
    // Force light mode as default
    colorMode: {
      preference: 'light',
      fallback: 'light'
    }
  }
})