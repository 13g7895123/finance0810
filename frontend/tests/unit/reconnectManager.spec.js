import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useReconnectManager } from '~/composables/useReconnectManager'

describe('重連管理器測試', () => {
  let reconnectManager
  
  beforeEach(() => {
    vi.useFakeTimers()
    reconnectManager = useReconnectManager()
  })
  
  it('應該實現指數退避', async () => {
    const connectFn = vi.fn().mockResolvedValue(false)
    
    reconnectManager.startReconnect(connectFn)
    
    // 第一次重試: 1秒
    await vi.advanceTimersByTime(1000)
    expect(connectFn).toHaveBeenCalledTimes(1)
    
    // 第二次重試: 2秒
    await vi.advanceTimersByTime(2000)
    expect(connectFn).toHaveBeenCalledTimes(2)
    
    // 第三次重試: 4秒
    await vi.advanceTimersByTime(4000)
    expect(connectFn).toHaveBeenCalledTimes(3)
  })
  
  it('應該在最大重試次數後停止', async () => {
    const connectFn = vi.fn().mockResolvedValue(false)
    reconnectManager.setMaxAttempts(3)
    
    await reconnectManager.startReconnect(connectFn)
    
    // 快進所有定時器
    await vi.runAllTimers()
    
    expect(reconnectManager.reconnectAttempts.value).toBe(3)
    expect(reconnectManager.canReconnect.value).toBe(false)
  })
  
  it('應該在成功連接時重置', async () => {
    const connectFn = vi.fn().mockResolvedValue(true)
    
    await reconnectManager.startReconnect(connectFn)
    await vi.runAllTimers()
    
    expect(reconnectManager.reconnectAttempts.value).toBe(0)
    expect(reconnectManager.isReconnecting.value).toBe(false)
  })
})