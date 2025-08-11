import { vi } from 'vitest'
import { ref, computed } from 'vue'

// Mock Vue composition functions
global.ref = ref
global.computed = computed

// Mock Nuxt-specific globals
global.navigateTo = vi.fn()
global.useRuntimeConfig = vi.fn(() => ({
  public: {
    apiBaseUrl: 'http://localhost:8000/api'
  }
}))

// Mock useRouter
global.useRouter = vi.fn(() => ({
  push: vi.fn(),
  replace: vi.fn(),
  go: vi.fn(),
  back: vi.fn(),
  forward: vi.fn()
}))

// Mock useRoute
global.useRoute = vi.fn(() => ({
  path: '/test',
  params: {},
  query: {},
  hash: ''
}))

// Mock useNuxtApp
global.useNuxtApp = vi.fn(() => ({
  $router: {
    push: vi.fn(),
    replace: vi.fn(),
    go: vi.fn(),
    back: vi.fn(),
    forward: vi.fn()
  }
}))

// Mock $fetch
global.$fetch = vi.fn()

// Mock Pinia
global.defineStore = vi.fn((name, setup) => {
  return () => setup()
})

// Mock useAuthStore (will be overridden in specific tests)
global.useAuthStore = vi.fn(() => ({
  user: null,
  isLoggedIn: false,
  isAdmin: false,
  isManager: false,
  isStaff: false,
  hasPermission: vi.fn(() => false),
  login: vi.fn(),
  logout: vi.fn(),
  initializeAuth: vi.fn()
}))

// Mock process while preserving existing process functions
if (typeof process !== 'undefined') {
  process.client = true
  process.server = false
} else {
  global.process = {
    client: true,
    server: false,
    listeners: vi.fn(() => []),
    on: vi.fn(),
    off: vi.fn(),
    emit: vi.fn()
  }
}

// Mock sessionStorage and localStorage
Object.defineProperty(window, 'sessionStorage', {
  value: {
    getItem: vi.fn(),
    setItem: vi.fn(),
    removeItem: vi.fn(),
    clear: vi.fn()
  }
})

Object.defineProperty(window, 'localStorage', {
  value: {
    getItem: vi.fn(),
    setItem: vi.fn(),
    removeItem: vi.fn(),
    clear: vi.fn()
  }
})

// Mock SweetAlert2
global.Swal = {
  fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
  mixin: vi.fn(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: true })
  }))
}

// Mock cookie function
global.cookie = vi.fn(() => ({
  value: null,
  sameSite: 'strict'
}))

// Mock definePageMeta
global.definePageMeta = vi.fn()