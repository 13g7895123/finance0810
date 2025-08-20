import { IndexedDBService } from '~/services/IndexedDBService'
import { CacheService } from '~/services/CacheService'

export const useCacheSync = () => {
  const indexedDB = new IndexedDBService()
  const memoryCache = new CacheService()
  
  /**
   * 同步到 IndexedDB
   */
  const syncToIndexedDB = async (key, data) => {
    try {
      if (key.startsWith('conversations_')) {
        await indexedDB.saveConversations(Array.isArray(data) ? data : [data])
      } else if (key.startsWith('messages_')) {
        await indexedDB.saveMessages(Array.isArray(data) ? data : [data])
      } else {
        // 使用通用緩存存儲
        await indexedDB.setCache(key, data)
      }
      
      console.log(`Successfully synced ${key} to IndexedDB`)
    } catch (error) {
      console.error('Failed to sync to IndexedDB:', error)
    }
  }
  
  /**
   * 從 IndexedDB 載入
   */
  const loadFromIndexedDB = async (key) => {
    try {
      if (key.startsWith('conversations_')) {
        return await indexedDB.getConversations()
      } else if (key.startsWith('messages_')) {
        const userId = key.replace('messages_', '')
        return await indexedDB.getMessagesByUser(userId)
      } else {
        // 使用通用緩存讀取
        return await indexedDB.getCache(key)
      }
    } catch (error) {
      console.error('Failed to load from IndexedDB:', error)
      return null
    }
  }
  
  /**
   * 混合緩存策略 - 優先內存，備用 IndexedDB
   */
  const hybridGet = async (key) => {
    // 先嘗試內存緩存
    let data = memoryCache.get(key)
    
    if (data !== null) {
      console.log(`Cache hit from memory: ${key}`)
      return data
    }
    
    // 內存緩存未命中，嘗試 IndexedDB
    data = await loadFromIndexedDB(key)
    
    if (data !== null) {
      console.log(`Cache hit from IndexedDB: ${key}`)
      // 回填到內存緩存
      memoryCache.set(key, data)
      return data
    }
    
    console.log(`Cache miss: ${key}`)
    return null
  }
  
  /**
   * 混合緩存設置
   */
  const hybridSet = async (key, data, ttl) => {
    // 設置內存緩存
    memoryCache.set(key, data, ttl)
    
    // 異步同步到 IndexedDB
    syncToIndexedDB(key, data)
    
    console.log(`Cached data with key: ${key}`)
  }
  
  /**
   * 智能預載入
   */
  const preloadData = async (keys) => {
    const promises = keys.map(async (key) => {
      const data = await loadFromIndexedDB(key)
      if (data !== null) {
        memoryCache.set(key, data)
      }
      return { key, success: data !== null }
    })
    
    const results = await Promise.all(promises)
    const successCount = results.filter(r => r.success).length
    
    console.log(`Preloaded ${successCount}/${keys.length} cache entries`)
    return results
  }
  
  /**
   * 緩存驗證和修復
   */
  const validateAndRepair = async () => {
    const memoryKeys = memoryCache.keys()
    const repairResults = []
    
    for (const key of memoryKeys) {
      try {
        const memoryData = memoryCache.get(key)
        const persistedData = await loadFromIndexedDB(key)
        
        if (memoryData && !persistedData) {
          // 內存有數據但持久化存儲沒有，進行同步
          await syncToIndexedDB(key, memoryData)
          repairResults.push({ key, action: 'sync_to_persistent', success: true })
        } else if (!memoryData && persistedData) {
          // 持久化存儲有數據但內存沒有，載入到內存
          memoryCache.set(key, persistedData)
          repairResults.push({ key, action: 'load_to_memory', success: true })
        } else if (memoryData && persistedData) {
          // 都有數據，檢查版本或時間戳
          if (JSON.stringify(memoryData) !== JSON.stringify(persistedData)) {
            // 數據不一致，以內存為準更新持久化存儲
            await syncToIndexedDB(key, memoryData)
            repairResults.push({ key, action: 'resolve_conflict', success: true })
          }
        }
      } catch (error) {
        repairResults.push({ key, action: 'error', success: false, error: error.message })
      }
    }
    
    return repairResults
  }
  
  /**
   * 清理過期緩存
   */
  const cleanupExpired = async () => {
    // 清理內存緩存
    const memoryCleanup = memoryCache.cleanup()
    
    // 清理 IndexedDB 緩存
    const dbCleanup = await indexedDB.cleanupExpiredCache()
    
    console.log(`Cleaned up ${memoryCleanup} memory cache entries and ${dbCleanup} IndexedDB entries`)
    
    return {
      memory: memoryCleanup,
      indexedDB: dbCleanup,
      total: memoryCleanup + dbCleanup
    }
  }
  
  /**
   * 獲取緩存統計
   */
  const getCacheStats = async () => {
    const memoryStats = memoryCache.getStats()
    const dbStats = await indexedDB.getStats()
    
    return {
      memory: memoryStats,
      indexedDB: dbStats,
      totalSize: memoryStats.size + (dbStats.cache || 0)
    }
  }
  
  /**
   * 批量操作
   */
  const batchSet = async (entries, ttl) => {
    const memoryOperations = []
    const dbOperations = []
    
    for (const [key, value] of Object.entries(entries)) {
      memoryOperations.push(() => memoryCache.set(key, value, ttl))
      dbOperations.push(() => syncToIndexedDB(key, value))
    }
    
    // 並行執行內存操作
    memoryOperations.forEach(op => op())
    
    // 並行執行數據庫操作
    await Promise.all(dbOperations.map(op => op()))
    
    console.log(`Batch set ${Object.keys(entries).length} cache entries`)
  }
  
  const batchGet = async (keys) => {
    const results = {}
    
    for (const key of keys) {
      results[key] = await hybridGet(key)
    }
    
    return results
  }
  
  /**
   * 緩存同步狀態監控
   */
  const getSyncStatus = async () => {
    const memoryKeys = new Set(memoryCache.keys())
    const dbStats = await indexedDB.getStats()
    
    return {
      memoryEntries: memoryKeys.size,
      persistentEntries: dbStats.cache || 0,
      syncRatio: memoryKeys.size > 0 ? (dbStats.cache || 0) / memoryKeys.size : 0,
      lastSync: Date.now()
    }
  }
  
  /**
   * 強制同步所有內存緩存到持久化存儲
   */
  const forceSyncAll = async () => {
    const memoryKeys = memoryCache.keys()
    const syncResults = []
    
    for (const key of memoryKeys) {
      try {
        const data = memoryCache.get(key)
        if (data !== null) {
          await syncToIndexedDB(key, data)
          syncResults.push({ key, success: true })
        }
      } catch (error) {
        syncResults.push({ key, success: false, error: error.message })
      }
    }
    
    const successCount = syncResults.filter(r => r.success).length
    console.log(`Force synced ${successCount}/${memoryKeys.length} entries`)
    
    return syncResults
  }
  
  /**
   * 緩存預熱
   */
  const warmupCache = async (dataLoader) => {
    try {
      console.log('Starting cache warmup...')
      
      // 載入關鍵數據
      const data = await dataLoader()
      
      if (data.conversations) {
        await hybridSet('conversations_all', data.conversations)
      }
      
      if (data.messages) {
        for (const [userId, messages] of Object.entries(data.messages)) {
          await hybridSet(`messages_${userId}`, messages)
        }
      }
      
      console.log('Cache warmup completed')
      return true
    } catch (error) {
      console.error('Cache warmup failed:', error)
      return false
    }
  }
  
  return {
    // 基本操作
    hybridGet,
    hybridSet,
    syncToIndexedDB,
    loadFromIndexedDB,
    
    // 批量操作
    batchGet,
    batchSet,
    
    // 高級功能
    preloadData,
    validateAndRepair,
    cleanupExpired,
    forceSyncAll,
    warmupCache,
    
    // 統計和監控
    getCacheStats,
    getSyncStatus,
    
    // 直接訪問底層服務
    memoryCache,
    indexedDB
  }
}