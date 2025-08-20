export class ErrorHandlerService {
  constructor() {
    this.errorLog = []
    this.maxLogSize = 100
    this.errorHandlers = new Map()
    this.errorStats = {
      total: 0,
      byType: {},
      byCode: {},
      lastError: null
    }
  }
  
  /**
   * 錯誤類型定義
   */
  static ErrorTypes = {
    NETWORK_ERROR: 'NETWORK_ERROR',
    TIMEOUT_ERROR: 'TIMEOUT_ERROR',
    AUTH_ERROR: 'AUTH_ERROR',
    SERVER_ERROR: 'SERVER_ERROR',
    CLIENT_ERROR: 'CLIENT_ERROR',
    UNKNOWN_ERROR: 'UNKNOWN_ERROR'
  }
  
  /**
   * 錯誤嚴重程度
   */
  static Severity = {
    LOW: 'low',
    MEDIUM: 'medium',
    HIGH: 'high',
    CRITICAL: 'critical'
  }
  
  /**
   * 分析錯誤類型
   */
  analyzeError(error) {
    // 網絡錯誤
    if (error.message?.includes('fetch') || 
        error.message?.includes('network') ||
        error.name === 'NetworkError') {
      return {
        type: ErrorHandlerService.ErrorTypes.NETWORK_ERROR,
        severity: ErrorHandlerService.Severity.HIGH,
        retryable: true
      }
    }
    
    // 超時錯誤
    if (error.name === 'AbortError' || 
        error.message?.includes('timeout')) {
      return {
        type: ErrorHandlerService.ErrorTypes.TIMEOUT_ERROR,
        severity: ErrorHandlerService.Severity.MEDIUM,
        retryable: true
      }
    }
    
    // HTTP 狀態碼錯誤
    const httpMatch = error.message?.match(/HTTP (\d+)/)
    if (httpMatch) {
      const statusCode = parseInt(httpMatch[1])
      
      if (statusCode === 401 || statusCode === 403) {
        return {
          type: ErrorHandlerService.ErrorTypes.AUTH_ERROR,
          severity: ErrorHandlerService.Severity.CRITICAL,
          retryable: false,
          statusCode
        }
      }
      
      if (statusCode >= 500) {
        return {
          type: ErrorHandlerService.ErrorTypes.SERVER_ERROR,
          severity: ErrorHandlerService.Severity.HIGH,
          retryable: true,
          statusCode
        }
      }
      
      if (statusCode >= 400) {
        return {
          type: ErrorHandlerService.ErrorTypes.CLIENT_ERROR,
          severity: ErrorHandlerService.Severity.MEDIUM,
          retryable: false,
          statusCode
        }
      }
    }
    
    // 未知錯誤
    return {
      type: ErrorHandlerService.ErrorTypes.UNKNOWN_ERROR,
      severity: ErrorHandlerService.Severity.LOW,
      retryable: true
    }
  }
  
  /**
   * 處理錯誤
   */
  handleError(error, context = {}) {
    const errorInfo = this.analyzeError(error)
    const timestamp = Date.now()
    
    // 建立錯誤記錄
    const errorRecord = {
      timestamp,
      error: {
        message: error.message,
        stack: error.stack,
        name: error.name
      },
      ...errorInfo,
      context
    }
    
    // 記錄錯誤
    this.logError(errorRecord)
    
    // 更新統計
    this.updateStats(errorRecord)
    
    // 執行註冊的處理器
    this.executeHandlers(errorRecord)
    
    return errorRecord
  }
  
  /**
   * 記錄錯誤
   */
  logError(errorRecord) {
    this.errorLog.unshift(errorRecord)
    
    // 限制日誌大小
    if (this.errorLog.length > this.maxLogSize) {
      this.errorLog = this.errorLog.slice(0, this.maxLogSize)
    }
    
    // 輸出到控制台
    if (errorRecord.severity === ErrorHandlerService.Severity.CRITICAL ||
        errorRecord.severity === ErrorHandlerService.Severity.HIGH) {
      console.error('Critical error:', errorRecord)
    } else {
      console.warn('Error:', errorRecord)
    }
  }
  
  /**
   * 更新錯誤統計
   */
  updateStats(errorRecord) {
    this.errorStats.total++
    this.errorStats.lastError = errorRecord
    
    // 按類型統計
    if (!this.errorStats.byType[errorRecord.type]) {
      this.errorStats.byType[errorRecord.type] = 0
    }
    this.errorStats.byType[errorRecord.type]++
    
    // 按狀態碼統計
    if (errorRecord.statusCode) {
      if (!this.errorStats.byCode[errorRecord.statusCode]) {
        this.errorStats.byCode[errorRecord.statusCode] = 0
      }
      this.errorStats.byCode[errorRecord.statusCode]++
    }
  }
  
  /**
   * 註冊錯誤處理器
   */
  registerHandler(type, handler) {
    if (!this.errorHandlers.has(type)) {
      this.errorHandlers.set(type, [])
    }
    this.errorHandlers.get(type).push(handler)
  }
  
  /**
   * 執行處理器
   */
  executeHandlers(errorRecord) {
    const handlers = this.errorHandlers.get(errorRecord.type) || []
    const globalHandlers = this.errorHandlers.get('*') || []
    
    const allHandlers = handlers.concat(globalHandlers)
    allHandlers.forEach(handler => {
      try {
        handler(errorRecord)
      } catch (e) {
        console.error('Error in error handler:', e)
      }
    })
  }
  
  /**
   * 獲取錯誤統計
   */
  getStats() {
    return {
      ...this.errorStats,
      recentErrors: this.errorLog.slice(0, 10)
    }
  }
  
  /**
   * 清除錯誤日誌
   */
  clearLog() {
    this.errorLog = []
    this.errorStats = {
      total: 0,
      byType: {},
      byCode: {},
      lastError: null
    }
  }
}