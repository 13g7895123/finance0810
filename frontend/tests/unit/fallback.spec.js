import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useFallbackStrategy } from '~/composables/useFallbackStrategy'

describe('降級機制測試', () => {
  let strategy
  
  beforeEach(() => {
    strategy = useFallbackStrategy()
  })
  
  describe('錯誤評估', () => {
    it('應該在連續錯誤後降級', () => {
      const error = new Error('Network error')
      
      strategy.onError(error)
      expect(strategy.currentStrategy.value).toBe('longPolling')
      
      strategy.onError(error)
      expect(strategy.currentStrategy.value).toBe('longPolling')
      
      strategy.onError(error)
      expect(strategy.currentStrategy.value).toBe('fallback')
      expect(strategy.fallbackReason.value).toContain('consecutive')
    })
    
    it('應該在高頻錯誤時降級', () => {
      const error = new Error('Timeout')
      
      // 模擬 10 個錯誤
      for (let i = 0; i < 10; i++) {
        strategy.onError(error)
      }
      
      expect(strategy.currentStrategy.value).toBe('fallback')
      expect(strategy.fallbackReason.value).toContain('frequency')
    })
    
    it('應該在認證錯誤時禁用', () => {
      const authError = new Error('HTTP 401')
      
      strategy.onError(authError)
      
      expect(strategy.currentStrategy.value).toBe('disabled')
      expect(strategy.fallbackReason.value).toContain('Authentication')
    })
  })
  
  describe('恢復機制', () => {
    it('應該在成功後重置錯誤計數', () => {
      const error = new Error('Network error')
      
      strategy.onError(error)
      strategy.onError(error)
      expect(strategy.consecutiveErrors.value).toBe(2)
      
      strategy.onSuccess()
      expect(strategy.consecutiveErrors.value).toBe(0)
    })
    
    it('應該能夠手動恢復', () => {
      // 先降級
      for (let i = 0; i < 3; i++) {
        strategy.onError(new Error('Network error'))
      }
      expect(strategy.currentStrategy.value).toBe('fallback')
      
      // 手動恢復
      strategy.recoverToLongPolling()
      expect(strategy.currentStrategy.value).toBe('longPolling')
      expect(strategy.fallbackReason.value).toBe(null)
    })
  })
  
  describe('統計功能', () => {
    it('應該正確統計錯誤', () => {
      strategy.onError(new Error('Network error'))
      strategy.onError(new Error('HTTP 500'))
      strategy.onError(new Error('Timeout'))
      
      const stats = strategy.getStrategyStatus()
      expect(stats.errorCount).toBe(3)
      expect(stats.current).toBe('fallback')
    })
  })
})