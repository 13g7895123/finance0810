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
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || 'https://dev-finance.mercylife.cc/api'
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
