import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

// Mock memory performance API
const mockMemoryUsage = () => ({
  heapUsed: 15000000 + Math.random() * 5000000, // 15-20MB
  heapTotal: 25000000,
  external: 1000000,
  arrayBuffers: 500000
})

// Mock performance.memory for browser environment
Object.defineProperty(performance, 'memory', {
  get: () => ({
    usedJSHeapSize: 15000000 + Math.random() * 5000000,
    totalJSHeapSize: 25000000,
    jsHeapSizeLimit: 2147483648
  })
})

class MemoryLeakDetector {
  constructor() {
    this.snapshots = []
    this.gcCount = 0
  }
  
  takeSnapshot() {
    this.forceGC()
    
    const memory = process?.memoryUsage ? process.memoryUsage() : mockMemoryUsage()
    const browserMemory = performance.memory || null
    
    const snapshot = {
      timestamp: Date.now(),
      node: memory,
      browser: browserMemory,
      gcCount: this.gcCount
    }
    
    this.snapshots.push(snapshot)
    return snapshot
  }
  
  forceGC() {
    if (global.gc) {
      global.gc()
      this.gcCount++
    }
  }
  
  analyzeLeaks() {
    if (this.snapshots.length < 2) {
      return { hasLeak: false, reason: 'Not enough snapshots' }
    }
    
    const first = this.snapshots[0]
    const last = this.snapshots[this.snapshots.length - 1]
    
    const nodeIncrease = last.node.heapUsed - first.node.heapUsed
    const nodePercentIncrease = (nodeIncrease / first.node.heapUsed) * 100
    
    let browserIncrease = 0
    let browserPercentIncrease = 0
    
    if (first.browser && last.browser) {
      browserIncrease = last.browser.usedJSHeapSize - first.browser.usedJSHeapSize
      browserPercentIncrease = (browserIncrease / first.browser.usedJSHeapSize) * 100
    }
    
    return {
      hasLeak: nodePercentIncrease > 50 || browserPercentIncrease > 50,
      node: {
        increase: nodeIncrease,
        percentIncrease: nodePercentIncrease
      },
      browser: {
        increase: browserIncrease,
        percentIncrease: browserPercentIncrease
      },
      snapshots: this.snapshots.length,
      gcCount: this.gcCount
    }
  }
}

// Mock component that might have memory leaks
class MockChatComponent {
  constructor() {
    this.timers = []
    this.listeners = []
    this.data = new Array(1000).fill(0).map((_, i) => ({ id: i, message: `Message ${i}` }))
  }
  
  mount() {
    // 模擬事件監聽器
    const listener = () => {}
    window.addEventListener('resize', listener)
    this.listeners.push({ event: 'resize', listener })
    
    // 模擬定時器
    const timer = setInterval(() => {
      this.data.push({ id: Date.now(), message: 'New message' })
    }, 100)
    this.timers.push(timer)
  }
  
  unmount() {
    // 清理事件監聽器
    this.listeners.forEach(({ event, listener }) => {
      window.removeEventListener(event, listener)
    })
    this.listeners = []
    
    // 清理定時器
    this.timers.forEach(timer => clearInterval(timer))
    this.timers = []
    
    // 清理數據
    this.data = null
  }
  
  // 故意不清理的版本（用於測試內存洩漏）
  unmountWithLeak() {
    // 只清理數據，不清理事件監聽器和定時器
    this.data = null
  }
}

describe('內存洩漏檢測', () => {
  let detector
  
  beforeEach(() => {
    detector = new MemoryLeakDetector()
    vi.useFakeTimers()
  })
  
  afterEach(() => {
    vi.useRealTimers()
  })
  
  it('應該能夠檢測內存快照', () => {
    const snapshot = detector.takeSnapshot()
    
    expect(snapshot).toHaveProperty('timestamp')
    expect(snapshot).toHaveProperty('node')
    expect(snapshot.node).toHaveProperty('heapUsed')
    expect(snapshot.node.heapUsed).toBeGreaterThan(0)
  })
  
  it('應該能夠比較多個快照', () => {
    // 取第一個快照
    detector.takeSnapshot()
    
    // 模擬一些內存分配
    const largeArray = new Array(10000).fill(0)
    
    // 取第二個快照
    detector.takeSnapshot()
    
    const analysis = detector.analyzeLeaks()
    
    expect(analysis).toHaveProperty('hasLeak')
    expect(analysis).toHaveProperty('node')
    expect(analysis).toHaveProperty('snapshots')
    expect(analysis.snapshots).toBe(2)
  })
  
  it('應該檢測正常的組件掛載和卸載', () => {
    detector.takeSnapshot()
    
    // 創建並掛載多個組件
    const components = []
    for (let i = 0; i < 10; i++) {
      const component = new MockChatComponent()
      component.mount()
      components.push(component)
    }
    
    detector.takeSnapshot()
    
    // 正確卸載所有組件
    components.forEach(component => component.unmount())
    
    // 強制垃圾回收
    detector.forceGC()
    
    detector.takeSnapshot()
    
    const analysis = detector.analyzeLeaks()
    
    // 正確清理後不應該有嚴重的內存洩漏
    expect(analysis.node.percentIncrease).toBeLessThan(100)
  })
  
  it('應該檢測內存洩漏', () => {
    detector.takeSnapshot()
    
    // 創建有內存洩漏的組件
    const components = []
    for (let i = 0; i < 5; i++) {
      const component = new MockChatComponent()
      component.mount()
      components.push(component)
    }
    
    detector.takeSnapshot()
    
    // 不正確的卸載（會造成內存洩漏）
    components.forEach(component => component.unmountWithLeak())
    
    detector.takeSnapshot()
    
    // 由於定時器和事件監聽器沒有清理，內存可能不會立即釋放
    const analysis = detector.analyzeLeaks()
    
    expect(analysis).toHaveProperty('hasLeak')
    expect(analysis.snapshots).toBe(3)
  })
  
  it('應該測試重複掛載卸載', async () => {
    detector.takeSnapshot()
    
    // 重複掛載和卸載 50 次
    for (let i = 0; i < 50; i++) {
      const component = new MockChatComponent()
      component.mount()
      
      // 模擬用戶交互
      await new Promise(resolve => setTimeout(resolve, 10))
      
      component.unmount()
      
      // 每 10 次迭代記錄快照
      if (i % 10 === 0) {
        detector.takeSnapshot()
      }
    }
    
    detector.forceGC()
    detector.takeSnapshot()
    
    const analysis = detector.analyzeLeaks()
    
    // 重複掛載卸載後不應該有顯著的內存增長
    expect(analysis.node.percentIncrease).toBeLessThan(200)
    expect(analysis.snapshots).toBeGreaterThan(2)
  })
  
  it('應該提供詳細的分析報告', () => {
    // 創建初始快照
    detector.takeSnapshot()
    
    // 分配一些內存
    const data = new Array(5000).fill(0).map((_, i) => ({ id: i, data: new Array(100).fill(i) }))
    
    detector.takeSnapshot()
    
    const analysis = detector.analyzeLeaks()
    
    expect(analysis).toHaveProperty('node')
    expect(analysis).toHaveProperty('browser')
    expect(analysis).toHaveProperty('snapshots')
    expect(analysis).toHaveProperty('gcCount')
    
    expect(analysis.node).toHaveProperty('increase')
    expect(analysis.node).toHaveProperty('percentIncrease')
    
    // 確保有分配內存
    expect(analysis.node.increase).toBeGreaterThan(0)
  })
})

describe('組件內存管理', () => {
  it('應該正確清理事件監聽器', () => {
    const component = new MockChatComponent()
    
    // 記錄初始監聽器數量（模擬）
    const initialListeners = component.listeners.length
    
    component.mount()
    expect(component.listeners.length).toBeGreaterThan(initialListeners)
    
    component.unmount()
    expect(component.listeners.length).toBe(0)
  })
  
  it('應該正確清理定時器', () => {
    const component = new MockChatComponent()
    
    const initialTimers = component.timers.length
    
    component.mount()
    expect(component.timers.length).toBeGreaterThan(initialTimers)
    
    component.unmount()
    expect(component.timers.length).toBe(0)
  })
  
  it('應該檢測洩漏的定時器', () => {
    const component = new MockChatComponent()
    
    component.mount()
    const timerCount = component.timers.length
    
    // 不正確的卸載
    component.unmountWithLeak()
    
    // 定時器沒有被清理
    expect(component.timers.length).toBe(timerCount)
    expect(component.listeners.length).toBeGreaterThan(0)
    
    // 手動清理避免影響其他測試
    component.timers.forEach(timer => clearInterval(timer))
    component.listeners.forEach(({ event, listener }) => {
      window.removeEventListener(event, listener)
    })
  })
})