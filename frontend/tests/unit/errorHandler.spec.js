import { describe, it, expect, beforeEach } from 'vitest'
import { ErrorHandlerService } from '~/services/ErrorHandlerService'

describe('錯誤處理服務測試', () => {
  let errorHandler
  
  beforeEach(() => {
    errorHandler = new ErrorHandlerService()
  })
  
  describe('錯誤分析', () => {
    it('應該正確識別網絡錯誤', () => {
      const error = new Error('fetch failed')
      const result = errorHandler.analyzeError(error)
      
      expect(result.type).toBe(ErrorHandlerService.ErrorTypes.NETWORK_ERROR)
      expect(result.severity).toBe(ErrorHandlerService.Severity.HIGH)
      expect(result.retryable).toBe(true)
    })
    
    it('應該正確識別超時錯誤', () => {
      const error = new Error('timeout')
      const result = errorHandler.analyzeError(error)
      
      expect(result.type).toBe(ErrorHandlerService.ErrorTypes.TIMEOUT_ERROR)
      expect(result.severity).toBe(ErrorHandlerService.Severity.MEDIUM)
      expect(result.retryable).toBe(true)
    })
    
    it('應該正確識別認證錯誤', () => {
      const error = new Error('HTTP 401')
      const result = errorHandler.analyzeError(error)
      
      expect(result.type).toBe(ErrorHandlerService.ErrorTypes.AUTH_ERROR)
      expect(result.severity).toBe(ErrorHandlerService.Severity.CRITICAL)
      expect(result.retryable).toBe(false)
      expect(result.statusCode).toBe(401)
    })
    
    it('應該正確識別服務器錯誤', () => {
      const error = new Error('HTTP 500')
      const result = errorHandler.analyzeError(error)
      
      expect(result.type).toBe(ErrorHandlerService.ErrorTypes.SERVER_ERROR)
      expect(result.severity).toBe(ErrorHandlerService.Severity.HIGH)
      expect(result.retryable).toBe(true)
      expect(result.statusCode).toBe(500)
    })
    
    it('應該正確識別客戶端錯誤', () => {
      const error = new Error('HTTP 404')
      const result = errorHandler.analyzeError(error)
      
      expect(result.type).toBe(ErrorHandlerService.ErrorTypes.CLIENT_ERROR)
      expect(result.severity).toBe(ErrorHandlerService.Severity.MEDIUM)
      expect(result.retryable).toBe(false)
      expect(result.statusCode).toBe(404)
    })
  })
  
  describe('錯誤記錄', () => {
    it('應該記錄錯誤到日誌', () => {
      const error = new Error('Test error')
      errorHandler.handleError(error)
      
      expect(errorHandler.errorLog.length).toBe(1)
      expect(errorHandler.errorLog[0].error.message).toBe('Test error')
    })
    
    it('應該限制日誌大小', () => {
      // 創建超過最大大小的錯誤
      for (let i = 0; i < 150; i++) {
        errorHandler.handleError(new Error(`Error ${i}`))
      }
      
      expect(errorHandler.errorLog.length).toBe(100)
    })
    
    it('應該正確統計錯誤', () => {
      errorHandler.handleError(new Error('Network error'))
      errorHandler.handleError(new Error('HTTP 500'))
      errorHandler.handleError(new Error('Network error'))
      
      const stats = errorHandler.getStats()
      expect(stats.total).toBe(3)
      expect(stats.byType[ErrorHandlerService.ErrorTypes.NETWORK_ERROR]).toBe(2)
      expect(stats.byType[ErrorHandlerService.ErrorTypes.SERVER_ERROR]).toBe(1)
    })
  })
})