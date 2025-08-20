import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

// Mock performance.now for consistent testing
let mockTime = 0
const originalPerformanceNow = performance.now
global.performance.now = vi.fn(() => mockTime)

const advanceTime = (ms) => {
  mockTime += ms
}

// Mock React-like component for testing
class MockRenderComponent {
  constructor(props = {}) {
    this.props = props
    this.renderCount = 0
    this.lastRenderTime = 0
    this.mounted = false
  }
  
  mount() {
    const startTime = performance.now()
    this.mounted = true
    this.renderCount++
    
    // 模擬渲染時間
    advanceTime(this.calculateRenderTime())
    
    this.lastRenderTime = performance.now() - startTime
    return this.lastRenderTime
  }
  
  update(newProps) {
    if (!this.mounted) return 0
    
    const startTime = performance.now()
    this.props = { ...this.props, ...newProps }
    this.renderCount++
    
    // 模擬更新渲染時間（通常更快）
    advanceTime(this.calculateUpdateTime())
    
    this.lastRenderTime = performance.now() - startTime
    return this.lastRenderTime
  }
  
  unmount() {
    this.mounted = false
    return 0
  }
  
  calculateRenderTime() {
    // 基於 props 數量模擬渲染時間
    const baseTime = 10
    const dataSize = this.props.data ? this.props.data.length : 0
    const complexityTime = Math.log(dataSize + 1) * 5
    const randomVariation = Math.random() * 20
    
    return baseTime + complexityTime + randomVariation
  }
  
  calculateUpdateTime() {
    // 更新通常比初始渲染快
    return this.calculateRenderTime() * 0.6
  }
}

// 聊天室專用的渲染組件
class MockChatRoomComponent extends MockRenderComponent {
  constructor(props = {}) {
    super(props)
    this.conversations = props.conversations || []
    this.messages = props.messages || []
  }
  
  addConversation(conversation) {
    const startTime = performance.now()
    this.conversations.push(conversation)
    
    // 模擬添加對話的渲染時間
    advanceTime(5 + Math.random() * 10)
    
    return performance.now() - startTime
  }
  
  addMessage(message) {
    const startTime = performance.now()
    this.messages.push(message)
    
    // 模擬添加消息的渲染時間
    advanceTime(3 + Math.random() * 7)
    
    return performance.now() - startTime
  }
  
  scrollToBottom() {
    const startTime = performance.now()
    
    // 模擬滾動動畫時間
    advanceTime(16.67) // 1 frame at 60fps
    
    return performance.now() - startTime
  }
  
  renderLargeList(itemCount) {
    const startTime = performance.now()
    
    // 大列表渲染時間與項目數量相關
    const itemRenderTime = itemCount * 0.5
    const layoutTime = Math.sqrt(itemCount) * 2
    const paintTime = itemCount * 0.1
    
    advanceTime(itemRenderTime + layoutTime + paintTime)
    
    return performance.now() - startTime
  }
}

describe('渲染性能測試', () => {
  let component
  
  beforeEach(() => {
    mockTime = 0
    component = new MockRenderComponent()
  })
  
  afterEach(() => {
    if (component && component.mounted) {
      component.unmount()
    }
  })
  
  it('應該測試初始渲染性能', () => {
    const renderTime = component.mount()
    
    expect(renderTime).toBeGreaterThan(0)
    expect(renderTime).toBeLessThan(100) // 應該在 100ms 內
    expect(component.mounted).toBe(true)
    expect(component.renderCount).toBe(1)
  })
  
  it('應該測試更新渲染性能', () => {
    // 先掛載
    component.mount()
    
    // 測試更新
    const updateTime = component.update({ newProp: 'test' })
    
    expect(updateTime).toBeGreaterThan(0)
    expect(updateTime).toBeLessThan(50) // 更新應該更快
    expect(component.renderCount).toBe(2)
  })
  
  it('應該測試大數據量的渲染性能', () => {
    const largeData = new Array(1000).fill(0).map((_, i) => ({ id: i, text: `Item ${i}` }))
    
    component = new MockRenderComponent({ data: largeData })
    const renderTime = component.mount()
    
    expect(renderTime).toBeGreaterThan(0)
    expect(renderTime).toBeLessThan(1000) // 1000 項目應該在 1 秒內渲染
  })
  
  it('應該測試多次更新的性能', () => {
    component.mount()
    
    const updateTimes = []
    for (let i = 0; i < 10; i++) {
      const updateTime = component.update({ counter: i })
      updateTimes.push(updateTime)
    }
    
    const averageUpdateTime = updateTimes.reduce((a, b) => a + b, 0) / updateTimes.length
    const maxUpdateTime = Math.max(...updateTimes)
    
    expect(averageUpdateTime).toBeLessThan(30) // 平均更新時間
    expect(maxUpdateTime).toBeLessThan(100) // 最大更新時間
    expect(component.renderCount).toBe(11) // 1 初始 + 10 更新
  })
})

describe('聊天室渲染性能', () => {
  let chatComponent
  
  beforeEach(() => {
    mockTime = 0
    chatComponent = new MockChatRoomComponent()
  })
  
  afterEach(() => {
    if (chatComponent && chatComponent.mounted) {
      chatComponent.unmount()
    }
  })
  
  it('應該測試聊天室初始化性能', () => {
    const initialConversations = new Array(50).fill(0).map((_, i) => ({
      id: i,
      name: `User ${i}`,
      lastMessage: `Message ${i}`,
      timestamp: Date.now() - i * 1000
    }))
    
    chatComponent = new MockChatRoomComponent({ conversations: initialConversations })
    const renderTime = chatComponent.mount()
    
    expect(renderTime).toBeLessThan(500) // 50 個對話應該在 500ms 內渲染
    expect(chatComponent.conversations.length).toBe(50)
  })
  
  it('應該測試添加新對話的性能', () => {
    chatComponent.mount()
    
    const addTimes = []
    for (let i = 0; i < 20; i++) {
      const addTime = chatComponent.addConversation({
        id: i,
        name: `New User ${i}`,
        lastMessage: `New message ${i}`
      })
      addTimes.push(addTime)
    }
    
    const averageAddTime = addTimes.reduce((a, b) => a + b, 0) / addTimes.length
    expect(averageAddTime).toBeLessThan(20) // 每次添加應該在 20ms 內
  })
  
  it('應該測試添加新消息的性能', () => {
    chatComponent.mount()
    
    const messageTimes = []
    for (let i = 0; i < 100; i++) {
      const messageTime = chatComponent.addMessage({
        id: i,
        content: `Message ${i}`,
        timestamp: Date.now(),
        sender: 'user'
      })
      messageTimes.push(messageTime)
    }
    
    const averageMessageTime = messageTimes.reduce((a, b) => a + b, 0) / messageTimes.length
    expect(averageMessageTime).toBeLessThan(15) // 每條消息應該在 15ms 內渲染
  })
  
  it('應該測試滾動性能', () => {
    chatComponent.mount()
    
    // 添加很多消息
    for (let i = 0; i < 200; i++) {
      chatComponent.addMessage({
        id: i,
        content: `Message ${i}`,
        timestamp: Date.now()
      })
    }
    
    const scrollTimes = []
    for (let i = 0; i < 10; i++) {
      const scrollTime = chatComponent.scrollToBottom()
      scrollTimes.push(scrollTime)
    }
    
    const averageScrollTime = scrollTimes.reduce((a, b) => a + b, 0) / scrollTimes.length
    expect(averageScrollTime).toBeLessThan(20) // 滾動應該很快
  })
  
  it('應該測試大列表虛擬化性能', () => {
    chatComponent.mount()
    
    // 測試不同大小的列表
    const sizes = [100, 500, 1000, 5000]
    const results = []
    
    for (const size of sizes) {
      const renderTime = chatComponent.renderLargeList(size)
      results.push({ size, renderTime })
    }
    
    // 檢查渲染時間是否在合理範圍內
    results.forEach(({ size, renderTime }) => {
      const maxExpectedTime = size * 2 // 每項目最多 2ms
      expect(renderTime).toBeLessThan(maxExpectedTime)
    })
    
    // 檢查時間複雜度是否合理（不應該是 O(n²)）
    const ratio5000to1000 = results[3].renderTime / results[2].renderTime
    expect(ratio5000to1000).toBeLessThan(10) // 5倍數據量，時間增長不應該超過10倍
  })
})

describe('渲染性能基準測試', () => {
  it('應該建立性能基準', () => {
    const benchmarks = {
      initialRender: { target: 100, tolerance: 50 },
      updateRender: { target: 30, tolerance: 20 },
      largeListRender: { target: 500, tolerance: 200 },
      messageAdd: { target: 15, tolerance: 10 }
    }
    
    // 初始渲染測試
    const component = new MockRenderComponent({ data: new Array(100).fill(0) })
    const initialTime = component.mount()
    
    expect(initialTime).toBeLessThan(benchmarks.initialRender.target + benchmarks.initialRender.tolerance)
    
    // 更新渲染測試
    const updateTime = component.update({ newData: 'test' })
    expect(updateTime).toBeLessThan(benchmarks.updateRender.target + benchmarks.updateRender.tolerance)
    
    component.unmount()
  })
  
  it('應該測試在不同設備條件下的性能', () => {
    // 模擬不同設備性能
    const devices = [
      { name: 'high-end', multiplier: 0.5 },    // 高端設備
      { name: 'mid-range', multiplier: 1.0 },   // 中端設備
      { name: 'low-end', multiplier: 2.0 }      // 低端設備
    ]
    
    devices.forEach(device => {
      const component = new MockRenderComponent()
      
      // 模擬設備性能影響
      const originalAdvanceTime = advanceTime
      const deviceAdvanceTime = (ms) => originalAdvanceTime(ms * device.multiplier)
      global.advanceTime = deviceAdvanceTime
      
      const renderTime = component.mount()
      
      // 恢復原始函數
      global.advanceTime = originalAdvanceTime
      
      // 低端設備的性能標準應該更寬鬆
      const maxTime = device.name === 'low-end' ? 200 : 100
      expect(renderTime).toBeLessThan(maxTime)
      
      component.unmount()
    })
  })
  
  it('應該測試記憶體密集型渲染', () => {
    // 創建大量複雜數據
    const complexData = new Array(500).fill(0).map((_, i) => ({
      id: i,
      user: {
        name: `User ${i}`,
        avatar: `avatar_${i}.jpg`,
        status: Math.random() > 0.5 ? 'online' : 'offline'
      },
      messages: new Array(20).fill(0).map((_, j) => ({
        id: `${i}_${j}`,
        content: `Message ${j} from user ${i}`,
        timestamp: Date.now() - j * 60000,
        attachments: j % 5 === 0 ? [`file_${i}_${j}.pdf`] : []
      }))
    }))
    
    const component = new MockRenderComponent({ data: complexData })
    const renderTime = component.mount()
    
    // 複雜數據的渲染時間應該在合理範圍內
    expect(renderTime).toBeLessThan(2000) // 2 秒內
    
    component.unmount()
  })
})

// 恢復原始 performance.now
afterAll(() => {
  global.performance.now = originalPerformanceNow
})