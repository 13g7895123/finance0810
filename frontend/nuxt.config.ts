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
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || 'https://dev-finance.mercylife.cc/api',
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
    port: 3000,
    host: '0.0.0.0'
  },
  // Development configuration
  vite: {
    server: {
      allowedHosts: ['finance.local', 'localhost']
    }
  },
  // Enable hot module replacement in development
  nitro: {
    experimental: {
      wasm: true
    }
  },
})
