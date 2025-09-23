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
      // Point 78: Use vite proxy in local development to avoid CORS issues
      // Local development (NODE_ENV=development): use proxy '/api'
      // Develop/Production environments: use direct API URL
      apiBaseUrl: process.env.NODE_ENV === 'development' ? '/api' : (process.env.NUXT_PUBLIC_API_BASE_URL || 'https://dev-finance.mercylife.cc/api'),
      // Point 3: Development convenience mode - skip authentication
      // When set to 'true', automatically simulate admin login without manual authentication
      // Recommended for development only, should be 'false' in production
      skipAuth: process.env.NUXT_SKIP_AUTH === 'true',
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
      allowedHosts: ['finance.local', 'localhost'],
      // Point 78: Vite proxy configuration for local development CORS solution
      // This proxy forwards '/api/*' requests to backend server at localhost:9221
      // Only active in development mode (NODE_ENV=development)
      // Develop/Production environments bypass this proxy and use direct API calls
      proxy: {
        '/api': {
          target: 'http://localhost:9221',
          changeOrigin: true,
          secure: false,
          ws: true, // Support WebSocket
          configure: (proxy, options) => {
            // Log proxy requests for debugging
            proxy.on('proxyReq', (proxyReq, req, res) => {
              console.log(`[Vite Proxy] ${req.method} ${req.url} -> ${options.target}${req.url}`)
            })
          }
        }
      }
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
