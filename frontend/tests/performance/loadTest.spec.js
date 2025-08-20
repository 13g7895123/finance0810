import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { PerformanceMonitor } from './setup'

class MockLoadTest {
  constructor(config = {}) {
    this.config = {
      concurrentUsers: config.concurrentUsers || 10,
      testDuration: config.testDuration || 5000, // 5 秒測試
      apiEndpoint: config.apiEndpoint || '/api/chats/poll-updates',
      ...config
    }
    this.monitor = new PerformanceMonitor()
    this.connections = []
    this.mockFetch = vi.fn()
    global.fetch = this.mockFetch
  }
  
  async run() {
    console.log(`Starting load test with ${this.config.concurrentUsers} users`)
    
    // 設置 fetch mock
    this.mockFetch.mockImplementation(async () => {
      // 模擬網絡延遲
      await new Promise(resolve => setTimeout(resolve, Math.random() * 100 + 50))
      
      return {
        ok: true,
        json: async () => ({
          success: true,
          version: 1,
          data: [],
          timeout: Math.random() > 0.9 // 10% 有數據
        })
      }
    })
    
    // 創建並發連接
    const promises = []
    for (let i = 0; i < this.config.concurrentUsers; i++) {
      promises.push(this.simulateUser(i))
    }
    
    // 等待測試完成
    await Promise.all(promises)
    
    return this.monitor.generateReport()
  }
  
  async simulateUser(userId) {
    const startTime = Date.now()
    const endTime = startTime + this.config.testDuration
    let requestCount = 0
    
    while (Date.now() < endTime) {
      const apiStart = performance.now()
      
      try {
        await this.mockFetch(this.config.apiEndpoint)
        const duration = performance.now() - apiStart
        this.monitor.recordApiCall(this.config.apiEndpoint, duration, true)
        
        // 模擬輪詢延遲
        if (Math.random() > 0.8) { // 20% 機率有延遲
          this.monitor.recordLongPollingLatency(Math.random() * 200 + 100)
        }
        
        requestCount++
        
      } catch (error) {
        const duration = performance.now() - apiStart
        this.monitor.recordApiCall(this.config.apiEndpoint, duration, false)
      }
      
      // 短暫延遲
      await new Promise(resolve => setTimeout(resolve, 100))
    }
    
    return requestCount
  }
}

describe('負載測試', () => {
  let loadTest
  
  beforeEach(() => {
    vi.useFakeTimers()
  })
  
  afterEach(() => {
    vi.useRealTimers()
    vi.clearAllMocks()
  })
  
  it('應該能夠處理並發用戶', async () => {
    loadTest = new MockLoadTest({
      concurrentUsers: 5,
      testDuration: 1000
    })
    
    const report = await loadTest.run()
    
    expect(report.summary.totalApiCalls).toBeGreaterThan(0)
    expect(report.summary.averageApiTime).toBeLessThan(200) // < 200ms
    expect(report.details.api.successRate).toBeGreaterThan(95) // > 95% 成功率
  })
  
  it('應該記錄正確的性能指標', async () => {
    loadTest = new MockLoadTest({
      concurrentUsers: 3,
      testDuration: 500
    })
    
    const report = await loadTest.run()
    
    expect(report.details.api).toHaveProperty('count')
    expect(report.details.api).toHaveProperty('average')
    expect(report.details.api).toHaveProperty('p95')
    expect(report.details.api).toHaveProperty('successRate')
    
    expect(report.details.api.count).toBeGreaterThan(0)
    expect(report.details.api.average).toBeGreaterThan(0)
  })
  
  it('應該測試不同並發級別', async () => {
    const concurrentLevels = [1, 5, 10]
    const results = []
    
    for (const level of concurrentLevels) {
      loadTest = new MockLoadTest({
        concurrentUsers: level,
        testDuration: 500
      })
      
      const report = await loadTest.run()
      results.push({
        concurrentUsers: level,
        averageApiTime: report.summary.averageApiTime,
        totalApiCalls: report.summary.totalApiCalls
      })
    }
    
    // 驗證隨著並發增加，總請求數增加
    expect(results[2].totalApiCalls).toBeGreaterThan(results[0].totalApiCalls)
    
    // 驗證響應時間在合理範圍內
    results.forEach(result => {
      expect(result.averageApiTime).toBeLessThan(500)
    })
  })
  
  it('應該處理錯誤情況', async () => {
    loadTest = new MockLoadTest({
      concurrentUsers: 2,
      testDuration: 500
    })
    
    // 模擬一些失敗請求
    loadTest.mockFetch.mockImplementation(async () => {
      if (Math.random() < 0.1) { // 10% 失敗率
        throw new Error('Network error')
      }
      
      return {
        ok: true,
        json: async () => ({ success: true, data: [] })
      }
    })
    
    const report = await loadTest.run()
    
    expect(report.details.api.successRate).toBeGreaterThan(80) // 至少 80% 成功
    expect(report.summary.totalApiCalls).toBeGreaterThan(0)
  })
})

describe('性能監控器', () => {
  let monitor
  
  beforeEach(() => {
    monitor = new PerformanceMonitor()
  })
  
  it('應該記錄 API 調用', () => {
    monitor.recordApiCall('/api/test', 150, true)
    monitor.recordApiCall('/api/test', 200, false)
    
    const report = monitor.generateReport()
    
    expect(report.details.api.count).toBe(2)
    expect(report.details.api.average).toBe(175)
    expect(report.details.api.successRate).toBe(50)
  })
  
  it('應該計算正確的百分位數', () => {
    const durations = [100, 150, 200, 250, 300]
    durations.forEach(d => monitor.recordApiCall('/api/test', d, true))
    
    const report = monitor.generateReport()
    
    expect(report.details.api.p50).toBe(200) // 中位數
    expect(report.details.api.p95).toBe(300) // 95百分位
  })
  
  it('應該記錄 Long Polling 延遲', () => {
    monitor.recordLongPollingLatency(100)
    monitor.recordLongPollingLatency(150)
    monitor.recordLongPollingLatency(200)
    
    const report = monitor.generateReport()
    
    expect(report.details.polling.count).toBe(3)
    expect(report.details.polling.average).toBe(150)
    expect(report.details.polling.max).toBe(200)
  })
  
  it('應該處理空數據', () => {
    const report = monitor.generateReport()
    
    expect(report.details.api.count).toBe(0)
    expect(report.details.render.average).toBe(0)
    expect(report.details.polling.average).toBe(0)
  })
})