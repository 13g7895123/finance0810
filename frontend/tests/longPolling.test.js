import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { useLongPolling } from '~/composables/useLongPolling'

// Mock fetch globally
global.fetch = vi.fn()

// Mock cookie composable
vi.mock('#app', () => ({
  useCookie: vi.fn(() => ({ value: 'test-token' }))
}))

describe('Long Polling', () => {
  let longPolling
  
  beforeEach(() => {
    vi.clearAllMocks()
    longPolling = useLongPolling()
  })
  
  afterEach(() => {
    if (longPolling) {
      longPolling.stopPolling()
    }
  })
  
  it('should initialize with correct default values', () => {
    expect(longPolling.isPolling.value).toBe(false)
    expect(longPolling.currentVersion.value).toBe(0)
    expect(longPolling.connectionStatus.value).toBe('disconnected')
    expect(longPolling.retryCount.value).toBe(0)
  })
  
  it('should start polling correctly', async () => {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        success: true,
        version: 1,
        data: [],
        timeout: true
      })
    })
    
    await longPolling.startPolling()
    
    expect(longPolling.isPolling.value).toBe(true)
    expect(longPolling.connectionStatus.value).toBe('connecting')
  })
  
  it('should handle updates correctly', async () => {
    const onUpdate = vi.fn()
    const testData = [{ type: 'message', data: 'test' }]
    
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        success: true,
        version: 2,
        data: testData
      })
    })
    
    await longPolling.startPolling({ onUpdate })
    
    // 等待處理
    await new Promise(resolve => setTimeout(resolve, 100))
    
    expect(onUpdate).toHaveBeenCalledWith(testData)
    expect(longPolling.currentVersion.value).toBe(2)
  })
  
  it('should handle HTTP errors', async () => {
    const onError = vi.fn()
    
    global.fetch.mockResolvedValueOnce({
      ok: false,
      status: 500,
      json: async () => ({ error: 'Server error' })
    })
    
    await longPolling.startPolling({ onError })
    
    // 等待錯誤處理
    await new Promise(resolve => setTimeout(resolve, 100))
    
    expect(onError).toHaveBeenCalled()
    expect(longPolling.connectionStatus.value).toBe('error')
  })
  
  it('should retry on network error', async () => {
    const onError = vi.fn()
    
    global.fetch.mockRejectedValueOnce(new Error('Network error'))
    
    await longPolling.startPolling({ onError })
    
    // 等待錯誤處理
    await new Promise(resolve => setTimeout(resolve, 100))
    
    expect(onError).toHaveBeenCalled()
    expect(longPolling.retryCount.value).toBeGreaterThan(0)
  })
  
  it('should stop after max retries', async () => {
    global.fetch.mockRejectedValue(new Error('Network error'))
    
    await longPolling.startPolling()
    
    // 等待多次重試（模擬）
    for (let i = 0; i < 6; i++) {
      await new Promise(resolve => setTimeout(resolve, 100))
    }
    
    expect(longPolling.isPolling.value).toBe(false)
  })
  
  it('should handle abort correctly', async () => {
    let resolvePromise
    global.fetch.mockImplementationOnce(() => {
      return new Promise((resolve) => {
        resolvePromise = resolve
      })
    })
    
    // 開始 polling
    const pollingPromise = longPolling.startPolling()
    
    // 立即停止
    longPolling.stopPolling()
    
    // 確認狀態
    expect(longPolling.isPolling.value).toBe(false)
    expect(longPolling.connectionStatus.value).toBe('disconnected')
    
    // 清理
    if (resolvePromise) {
      resolvePromise({
        ok: true,
        json: async () => ({ success: true, data: [] })
      })
    }
    
    await pollingPromise.catch(() => {}) // 忽略可能的錯誤
  })
  
  it('should restart polling correctly', async () => {
    global.fetch.mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        version: 1,
        data: [],
        timeout: true
      })
    })
    
    // 開始 polling
    await longPolling.startPolling()
    expect(longPolling.isPolling.value).toBe(true)
    
    // 重啟 polling
    await longPolling.restartPolling()
    
    expect(longPolling.isPolling.value).toBe(true)
    expect(longPolling.retryCount.value).toBe(0)
  })
  
  it('should handle timeout responses correctly', async () => {
    let callCount = 0
    
    global.fetch.mockImplementation(() => {
      callCount++
      return Promise.resolve({
        ok: true,
        json: async () => ({
          success: true,
          version: callCount,
          data: [],
          timeout: true // 表示是超時響應
        })
      })
    })
    
    await longPolling.startPolling()
    
    // 等待多次循環
    await new Promise(resolve => setTimeout(resolve, 300))
    
    expect(callCount).toBeGreaterThan(1) // 應該發起多次請求
    expect(longPolling.connectionStatus.value).toBe('connected')
  })
  
  it('should build correct request parameters', () => {
    // 這個測試檢查請求參數是否正確構建
    const mockFetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ success: true, data: [] })
    })
    
    global.fetch = mockFetch
    
    longPolling.startPolling({
      lineUserId: 'test-user-123'
    })
    
    // 檢查是否調用了 fetch
    expect(mockFetch).toHaveBeenCalledWith(
      expect.stringContaining('/api/chats/poll-updates'),
      expect.objectContaining({
        method: 'GET',
        headers: expect.objectContaining({
          'Accept': 'application/json',
          'Authorization': 'Bearer test-token'
        })
      })
    )
  })
})