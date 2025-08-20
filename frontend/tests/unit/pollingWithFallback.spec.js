import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { usePollingWithFallback } from '~/composables/usePollingWithFallback'
import { flushPromises } from '@vue/test-utils'

describe('降級輪詢測試', () => {
  let polling
  let mockFetch
  
  beforeEach(() => {
    vi.useFakeTimers()
    mockFetch = vi.fn()
    global.fetch = mockFetch
    polling = usePollingWithFallback()
  })
  
  afterEach(() => {
    polling.stopPolling()
    vi.clearAllTimers()
    vi.useRealTimers()
    vi.clearAllMocks()
  })
  
  describe('基本功能', () => {
    it('應該能夠啟動Long Polling', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: [], version: 1 })
      })
      
      await polling.startPolling()
      
      expect(polling.isPolling.value).toBe(true)
      expect(polling.currentStrategy.value).toBe('longPolling')
      expect(mockFetch).toHaveBeenCalledWith('/api/chats/poll-updates', expect.any(Object))
    })
    
    it('應該能夠停止輪詢', async () => {
      mockFetch.mockResolvedValue({
        ok: true,
        json: async () => ({ success: true, data: [] })
      })
      
      await polling.startPolling()
      polling.stopPolling()
      
      expect(polling.isPolling.value).toBe(false)
    })
  })
  
  describe('降級機制', () => {
    it('應該在連續錯誤後切換到降級模式', async () => {
      // 模擬連續網絡錯誤
      mockFetch.mockRejectedValue(new Error('Network error'))
      
      await polling.startPolling()
      
      // 等待錯誤累積
      await flushPromises()
      vi.advanceTimersByTime(5000)
      await flushPromises()
      vi.advanceTimersByTime(5000)
      await flushPromises()
      vi.advanceTimersByTime(5000)
      await flushPromises()
      
      expect(polling.currentStrategy.value).toBe('fallback')
      expect(polling.fallbackReason.value).toContain('consecutive')
    })
    
    it('應該在降級模式下使用傳統輪詢', async () => {
      // 先觸發降級
      mockFetch.mockRejectedValue(new Error('Network error'))
      await polling.startPolling()
      
      // 等待降級
      for (let i = 0; i < 5; i++) {
        await flushPromises()
        vi.advanceTimersByTime(2000)
      }
      
      expect(polling.currentStrategy.value).toBe('fallback')
      
      // 現在模擬降級輪詢成功
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: [] })
      })
      
      vi.advanceTimersByTime(5000)
      await flushPromises()
      
      // 檢查是否調用了傳統API
      expect(mockFetch).toHaveBeenLastCalledWith('/api/chats', expect.any(Object))
    })
  })
  
  describe('錯誤處理', () => {
    it('應該在認證錯誤時禁用輪詢', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 401
      })
      
      await polling.startPolling()
      await flushPromises()
      
      expect(polling.currentStrategy.value).toBe('disabled')
      expect(polling.isPolling.value).toBe(false)
    })
    
    it('應該正確處理數據更新', async () => {
      const onUpdate = vi.fn()
      const testData = [
        { type: 'message', data: { id: 1, content: 'Test' } }
      ]
      
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ 
          success: true, 
          data: testData,
          version: 2
        })
      })
      
      await polling.startPolling({ onUpdate })
      await flushPromises()
      
      expect(onUpdate).toHaveBeenCalledWith(testData)
      expect(polling.currentVersion.value).toBe(2)
    })
  })
  
  describe('恢復機制', () => {
    it('應該在降級模式下嘗試恢復', async () => {
      // 先降級
      mockFetch.mockRejectedValue(new Error('Network error'))
      await polling.startPolling()
      
      // 等待降級
      for (let i = 0; i < 5; i++) {
        await flushPromises()
        vi.advanceTimersByTime(2000)
      }
      
      expect(polling.currentStrategy.value).toBe('fallback')
      
      // 模擬服務恢復
      mockFetch.mockResolvedValue({
        ok: true,
        json: async () => ({ success: true, data: [] })
      })
      
      // 等待足夠時間進行恢復檢查
      vi.advanceTimersByTime(60000)
      await flushPromises()
      
      // 應該檢查恢復條件
      expect(mockFetch).toHaveBeenCalledWith('/api/chats/poll-updates?test=1', expect.any(Object))
    })
  })
  
  describe('狀態管理', () => {
    it('應該提供正確的狀態信息', () => {
      const status = polling.getStrategyStatus()
      
      expect(status).toHaveProperty('current')
      expect(status).toHaveProperty('reason')
      expect(status).toHaveProperty('errorCount')
    })
  })
})