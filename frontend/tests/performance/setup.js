export class PerformanceMonitor {
  constructor() {
    this.metrics = {
      apiCalls: [],
      memoryUsage: [],
      renderTimes: [],
      longPollingLatency: [],
      errorRates: []
    }
    this.startTime = Date.now()
  }
  
  /**
   * 記錄 API 調用性能
   */
  recordApiCall(endpoint, duration, success = true) {
    this.metrics.apiCalls.push({
      endpoint,
      duration,
      success,
      timestamp: Date.now() - this.startTime
    })
  }
  
  /**
   * 記錄內存使用
   */
  recordMemoryUsage() {
    if (performance.memory) {
      this.metrics.memoryUsage.push({
        used: performance.memory.usedJSHeapSize,
        total: performance.memory.totalJSHeapSize,
        limit: performance.memory.jsHeapSizeLimit,
        timestamp: Date.now() - this.startTime
      })
    }
  }
  
  /**
   * 記錄渲染性能
   */
  recordRenderTime(componentName, duration) {
    this.metrics.renderTimes.push({
      component: componentName,
      duration,
      timestamp: Date.now() - this.startTime
    })
  }
  
  /**
   * 記錄 Long Polling 延遲
   */
  recordLongPollingLatency(latency) {
    this.metrics.longPollingLatency.push({
      latency,
      timestamp: Date.now() - this.startTime
    })
  }
  
  /**
   * 生成性能報告
   */
  generateReport() {
    const apiStats = this.calculateApiStats()
    const memoryStats = this.calculateMemoryStats()
    const renderStats = this.calculateRenderStats()
    const pollingStats = this.calculatePollingStats()
    
    return {
      summary: {
        totalDuration: Date.now() - this.startTime,
        totalApiCalls: this.metrics.apiCalls.length,
        averageApiTime: apiStats.average,
        p95ApiTime: apiStats.p95,
        memoryLeaked: memoryStats.leaked,
        averageRenderTime: renderStats.average,
        averagePollingLatency: pollingStats.average
      },
      details: {
        api: apiStats,
        memory: memoryStats,
        render: renderStats,
        polling: pollingStats
      },
      raw: this.metrics
    }
  }
  
  /**
   * 計算 API 統計
   */
  calculateApiStats() {
    const durations = this.metrics.apiCalls.map(c => c.duration).sort((a, b) => a - b)
    const successCount = this.metrics.apiCalls.filter(c => c.success).length
    
    return {
      count: durations.length,
      average: durations.reduce((a, b) => a + b, 0) / durations.length,
      min: durations[0],
      max: durations[durations.length - 1],
      p50: this.percentile(durations, 50),
      p95: this.percentile(durations, 95),
      p99: this.percentile(durations, 99),
      successRate: (successCount / durations.length) * 100
    }
  }
  
  /**
   * 計算內存統計
   */
  calculateMemoryStats() {
    if (this.metrics.memoryUsage.length === 0) {
      return { leaked: false, trend: 'stable' }
    }
    
    const first = this.metrics.memoryUsage[0]
    const last = this.metrics.memoryUsage[this.metrics.memoryUsage.length - 1]
    const increase = last.used - first.used
    const percentIncrease = (increase / first.used) * 100
    
    return {
      initial: first.used,
      final: last.used,
      increase,
      percentIncrease,
      leaked: percentIncrease > 50,
      trend: percentIncrease > 10 ? 'increasing' : 'stable'
    }
  }
  
  /**
   * 計算渲染統計
   */
  calculateRenderStats() {
    const durations = this.metrics.renderTimes.map(r => r.duration)
    
    if (durations.length === 0) {
      return { average: 0, max: 0 }
    }
    
    return {
      count: durations.length,
      average: durations.reduce((a, b) => a + b, 0) / durations.length,
      max: Math.max(...durations),
      min: Math.min(...durations)
    }
  }
  
  /**
   * 計算輪詢統計
   */
  calculatePollingStats() {
    const latencies = this.metrics.longPollingLatency.map(l => l.latency)
    
    if (latencies.length === 0) {
      return { average: 0, max: 0 }
    }
    
    return {
      count: latencies.length,
      average: latencies.reduce((a, b) => a + b, 0) / latencies.length,
      max: Math.max(...latencies),
      min: Math.min(...latencies),
      p95: this.percentile(latencies, 95)
    }
  }
  
  /**
   * 計算百分位數
   */
  percentile(arr, p) {
    const index = Math.ceil((p / 100) * arr.length) - 1
    return arr[index] || 0
  }
}