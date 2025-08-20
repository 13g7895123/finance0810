export class PerformanceMonitorService {
  constructor() {
    this.metrics = {
      apiCalls: [],
      renderTimes: [],
      memoryUsage: [],
      errorCounts: {}
    }
  }
  
  recordApiCall(endpoint, duration, success) {
    this.metrics.apiCalls.push({
      endpoint,
      duration,
      success,
      timestamp: Date.now()
    })
    
    // 保持最近 100 條記錄
    if (this.metrics.apiCalls.length > 100) {
      this.metrics.apiCalls.shift()
    }
  }
  
  recordRenderTime(component, duration) {
    this.metrics.renderTimes.push({
      component,
      duration,
      timestamp: Date.now()
    })
  }
  
  recordMemoryUsage() {
    if (performance.memory) {
      this.metrics.memoryUsage.push({
        used: performance.memory.usedJSHeapSize,
        total: performance.memory.totalJSHeapSize,
        timestamp: Date.now()
      })
    }
  }
  
  recordError(type, error) {
    if (!this.metrics.errorCounts[type]) {
      this.metrics.errorCounts[type] = 0
    }
    this.metrics.errorCounts[type]++
  }
  
  getMetrics() {
    return {
      ...this.metrics,
      averageApiTime: this.calculateAverageApiTime(),
      averageRenderTime: this.calculateAverageRenderTime(),
      memoryTrend: this.calculateMemoryTrend()
    }
  }
  
  calculateAverageApiTime() {
    const recent = this.metrics.apiCalls.slice(-10)
    if (recent.length === 0) return 0
    
    const total = recent.reduce((sum, call) => sum + call.duration, 0)
    return total / recent.length
  }
  
  calculateAverageRenderTime() {
    const recent = this.metrics.renderTimes.slice(-10)
    if (recent.length === 0) return 0
    
    const total = recent.reduce((sum, render) => sum + render.duration, 0)
    return total / recent.length
  }
  
  calculateMemoryTrend() {
    if (this.metrics.memoryUsage.length < 2) return 'stable'
    
    const recent = this.metrics.memoryUsage.slice(-5)
    const first = recent[0].used
    const last = recent[recent.length - 1].used
    const change = ((last - first) / first) * 100
    
    if (change > 10) return 'increasing'
    if (change < -10) return 'decreasing'
    return 'stable'
  }
}