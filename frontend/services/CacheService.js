/**
 * 客戶端緩存服務
 * 提供內存緩存功能，支持 TTL 過期時間
 */
export class CacheService {
  constructor() {
    this.cache = new Map()
    this.ttl = new Map()
    this.defaultTTL = 300000 // 5 分鐘
    this.maxSize = 100 // 最大緩存項目數
    this.hitCount = 0
    this.missCount = 0
    this.accessTimes = new Map() // 追蹤訪問時間，用於 LRU
  }
  
  /**
   * 設置緩存項目
   */
  set(key, value, ttl = this.defaultTTL) {
    // 如果緩存已滿，清理最舊的項目
    if (this.cache.size >= this.maxSize && !this.cache.has(key)) {
      this._evictOldest()
    }
    
    this.cache.set(key, value)
    this.ttl.set(key, Date.now() + ttl)
    this.accessTimes.set(key, Date.now())
  }
  
  /**
   * 獲取緩存項目
   */
  get(key) {
    if (!this.cache.has(key)) {
      this.missCount++
      return null
    }
    
    const expiry = this.ttl.get(key)
    if (Date.now() > expiry) {
      // 過期的項目
      this.cache.delete(key)
      this.ttl.delete(key)
      this.accessTimes.delete(key)
      this.missCount++
      return null
    }
    
    // 更新訪問時間
    this.accessTimes.set(key, Date.now())
    this.hitCount++
    return this.cache.get(key)
  }
  
  /**
   * 檢查緩存項目是否存在且未過期
   */
  has(key) {
    return this.get(key) !== null
  }
  
  /**
   * 刪除緩存項目
   */
  delete(key) {
    const deleted = this.cache.delete(key)
    this.ttl.delete(key)
    this.accessTimes.delete(key)
    return deleted
  }
  
  /**
   * 清空所有緩存
   */
  clear() {
    this.cache.clear()
    this.ttl.clear()
    this.accessTimes.clear()
    this.hitCount = 0
    this.missCount = 0
  }
  
  /**
   * 清理過期的緩存項目
   */
  cleanup() {
    const now = Date.now()
    const expiredKeys = []
    
    for (const [key, expiry] of this.ttl) {
      if (now > expiry) {
        expiredKeys.push(key)
      }
    }
    
    expiredKeys.forEach(key => {
      this.cache.delete(key)
      this.ttl.delete(key)
      this.accessTimes.delete(key)
    })
    
    return expiredKeys.length
  }
  
  /**
   * 驅逐最舊的緩存項目 (LRU)
   */
  _evictOldest() {
    let oldestKey = null
    let oldestTime = Infinity
    
    for (const [key, accessTime] of this.accessTimes) {
      if (accessTime < oldestTime) {
        oldestTime = accessTime
        oldestKey = key
      }
    }
    
    if (oldestKey) {
      this.delete(oldestKey)
    }
  }
  
  /**
   * 獲取緩存統計信息
   */
  getStats() {
    const totalRequests = this.hitCount + this.missCount
    const hitRate = totalRequests > 0 ? (this.hitCount / totalRequests * 100).toFixed(2) : 0
    
    return {
      size: this.cache.size,
      maxSize: this.maxSize,
      hitCount: this.hitCount,
      missCount: this.missCount,
      hitRate: `${hitRate}%`,
      memoryUsage: this._calculateMemoryUsage()
    }
  }
  
  /**
   * 估算內存使用量
   */
  _calculateMemoryUsage() {
    let totalSize = 0
    
    for (const [key, value] of this.cache) {
      totalSize += this._getObjectSize(key) + this._getObjectSize(value)
    }
    
    return {
      bytes: totalSize,
      kb: (totalSize / 1024).toFixed(2),
      mb: (totalSize / 1024 / 1024).toFixed(2)
    }
  }
  
  /**
   * 估算對象大小（簡化實現）
   */
  _getObjectSize(obj) {
    try {
      return JSON.stringify(obj).length * 2 // 粗略估算，假設每個字符 2 字節
    } catch {
      return 100 // 預設大小
    }
  }
  
  /**
   * 獲取所有緩存鍵
   */
  keys() {
    this.cleanup() // 清理過期項目
    return Array.from(this.cache.keys())
  }
  
  /**
   * 獲取所有緩存值
   */
  values() {
    this.cleanup() // 清理過期項目
    return Array.from(this.cache.values())
  }
  
  /**
   * 設置最大緩存大小
   */
  setMaxSize(size) {
    this.maxSize = size
    
    // 如果當前緩存超過新的最大大小，清理舊項目
    while (this.cache.size > this.maxSize) {
      this._evictOldest()
    }
  }
  
  /**
   * 批量設置緩存項目
   */
  setMultiple(items, ttl = this.defaultTTL) {
    for (const [key, value] of Object.entries(items)) {
      this.set(key, value, ttl)
    }
  }
  
  /**
   * 批量獲取緩存項目
   */
  getMultiple(keys) {
    const result = {}
    
    for (const key of keys) {
      const value = this.get(key)
      if (value !== null) {
        result[key] = value
      }
    }
    
    return result
  }
  
  /**
   * 更新緩存項目（如果存在）
   */
  update(key, updater, ttl) {
    const currentValue = this.get(key)
    if (currentValue !== null) {
      const newValue = updater(currentValue)
      this.set(key, newValue, ttl)
      return newValue
    }
    return null
  }
  
  /**
   * 獲取或設置緩存項目
   */
  getOrSet(key, valueProvider, ttl = this.defaultTTL) {
    let value = this.get(key)
    
    if (value === null) {
      value = typeof valueProvider === 'function' ? valueProvider() : valueProvider
      this.set(key, value, ttl)
    }
    
    return value
  }
}