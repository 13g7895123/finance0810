import { config } from '@vue/test-utils'
import { vi } from 'vitest'

// 設置全局測試配置
config.global.mocks = {
  $fetch: vi.fn(),
  $config: {
    public: {
      apiBase: 'http://localhost:3000/api'
    }
  }
}

// Mock 瀏覽器 API
global.fetch = vi.fn()
global.AbortController = class {
  constructor() {
    this.signal = { aborted: false }
  }
  abort() {
    this.signal.aborted = true
  }
}

// Mock localStorage
global.localStorage = {
  data: {},
  getItem(key) {
    return this.data[key] || null
  },
  setItem(key, value) {
    this.data[key] = value
  },
  removeItem(key) {
    delete this.data[key]
  },
  clear() {
    this.data = {}
  }
}

// Mock Cookie
global.useCookie = vi.fn((name) => ({
  value: null,
  refresh: vi.fn()
}))

// Mock navigator.onLine
Object.defineProperty(navigator, 'onLine', {
  writable: true,
  value: true
})