export default defineNuxtConfig({
  devtools: { enabled: true },
  ssr: true,
  modules: [
    '@nuxt/ui',
    '@pinia/nuxt'
  ],
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    public: {
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || 'http://localhost:9221/api'
    }
  },
  // Development configuration
  vite: {
    server: {
      hmr: {
        port: 24678 // Different port for HMR to avoid conflicts
      },
      allowedHosts: ['finance.local']
    }
  },
  // Enable hot module replacement in development
  nitro: {
    experimental: {
      wasm: true
    }
  },
  devServer: {
    host: 'frontend.localhost',
    port: 3000
  },
})