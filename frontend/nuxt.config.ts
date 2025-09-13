export default defineNuxtConfig({
  devtools: { enabled: true },
  ssr: true,
  modules: [
    '@nuxt/ui',
    '@pinia/nuxt'
  ],
  ui: {
    global: true,
    colorMode: {
      preference: 'light'
    }
  },
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    public: {
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || 'http://localhost:9221/api',
      // Firebase configuration
      firebaseApiKey: process.env.NUXT_FIREBASE_API_KEY,
      firebaseDatabaseUrl: process.env.NUXT_FIREBASE_DATABASE_URL || 'https://finance0810new-default-rtdb.asia-southeast1.firebasedatabase.app/',
      firebaseProjectId: process.env.NUXT_FIREBASE_PROJECT_ID || 'finance0810new',
      firebaseMessagingSenderId: process.env.NUXT_FIREBASE_MESSAGING_SENDER_ID,
      firebaseAppId: process.env.NUXT_FIREBASE_APP_ID
    }
  },
  // Development server configuration
  devServer: {
    port: 3301,
    host: '0.0.0.0'
  },
  // Development configuration
  vite: {
    server: {
      allowedHosts: ['finance.local', 'localhost']
    },
    build: {
      rollupOptions: {
        external: (id) => {
          // Firebase modules should be treated as external in production
          if (id.includes('firebase/')) {
            return false // Let rollup bundle firebase modules instead of treating them as external
          }
          return false
        }
      }
    },
    optimizeDeps: {
      include: ['firebase/app', 'firebase/database']
    }
  },
  // Enable hot module replacement in development
  nitro: {
    experimental: {
      wasm: true
    }
  },
})
