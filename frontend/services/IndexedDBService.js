/**
 * IndexedDB 存儲服務
 * 提供持久化數據存儲功能
 */
export class IndexedDBService {
  constructor() {
    this.dbName = 'ChatAppDB'
    this.version = 1
    this.db = null
    this.isInitialized = false
    this.initPromise = null
  }
  
  /**
   * 初始化數據庫
   */
  async init() {
    if (this.isInitialized) {
      return this.db
    }
    
    if (this.initPromise) {
      return this.initPromise
    }
    
    this.initPromise = new Promise((resolve, reject) => {
      // 檢查瀏覽器支持
      if (!window.indexedDB) {
        reject(new Error('IndexedDB is not supported'))
        return
      }
      
      const request = indexedDB.open(this.dbName, this.version)
      
      request.onerror = () => {
        reject(new Error(`Failed to open database: ${request.error}`))
      }
      
      request.onsuccess = () => {
        this.db = request.result
        this.isInitialized = true
        
        // 處理數據庫意外關閉
        this.db.onclose = () => {
          this.isInitialized = false
          console.warn('IndexedDB connection was closed unexpectedly')
        }
        
        resolve(this.db)
      }
      
      request.onupgradeneeded = (event) => {
        const db = event.target.result
        
        try {
          // 創建對話存儲
          if (!db.objectStoreNames.contains('conversations')) {
            const conversationStore = db.createObjectStore('conversations', {
              keyPath: 'line_user_id'
            })
            conversationStore.createIndex('version', 'version', { unique: false })
            conversationStore.createIndex('status', 'status', { unique: false })
            conversationStore.createIndex('last_message_time', 'last_message_time', { unique: false })
          }
          
          // 創建消息存儲
          if (!db.objectStoreNames.contains('messages')) {
            const messageStore = db.createObjectStore('messages', {
              keyPath: 'id'
            })
            messageStore.createIndex('line_user_id', 'line_user_id', { unique: false })
            messageStore.createIndex('version', 'version', { unique: false })
            messageStore.createIndex('message_timestamp', 'message_timestamp', { unique: false })
            messageStore.createIndex('status', 'status', { unique: false })
          }
          
          // 創建緩存存儲
          if (!db.objectStoreNames.contains('cache')) {
            const cacheStore = db.createObjectStore('cache', {
              keyPath: 'key'
            })
            cacheStore.createIndex('expiry', 'expiry', { unique: false })
            cacheStore.createIndex('created_at', 'created_at', { unique: false })
          }
          
          // 創建用戶數據存儲
          if (!db.objectStoreNames.contains('userData')) {
            const userDataStore = db.createObjectStore('userData', {
              keyPath: 'id'
            })
            userDataStore.createIndex('type', 'type', { unique: false })
            userDataStore.createIndex('updated_at', 'updated_at', { unique: false })
          }
          
        } catch (error) {
          console.error('Error creating object stores:', error)
          reject(error)
        }
      }
      
      request.onblocked = () => {
        console.warn('Database upgrade blocked by another tab')
      }
    })
    
    return this.initPromise
  }
  
  /**
   * 確保數據庫已初始化
   */
  async _ensureInitialized() {
    if (!this.isInitialized) {
      await this.init()
    }
  }
  
  /**
   * 保存對話數據
   */
  async saveConversations(conversations) {
    await this._ensureInitialized()
    
    const transaction = this.db.transaction(['conversations'], 'readwrite')
    const store = transaction.objectStore('conversations')
    
    const promises = conversations.map(conversation => {
      return new Promise((resolve, reject) => {
        const request = store.put({
          ...conversation,
          updated_at: Date.now()
        })
        request.onsuccess = () => resolve(request.result)
        request.onerror = () => reject(request.error)
      })
    })
    
    return Promise.all(promises)
  }
  
  /**
   * 獲取所有對話
   */
  async getConversations() {
    await this._ensureInitialized()
    
    const transaction = this.db.transaction(['conversations'], 'readonly')
    const store = transaction.objectStore('conversations')
    
    return new Promise((resolve, reject) => {
      const request = store.getAll()
      request.onsuccess = () => resolve(request.result)
      request.onerror = () => reject(request.error)
    })
  }
  
  /**
   * 根據條件獲取對話
   */
  async getConversationsByIndex(indexName, value) {
    await this._ensureInitialized()
    
    const transaction = this.db.transaction(['conversations'], 'readonly')
    const store = transaction.objectStore('conversations')
    const index = store.index(indexName)
    
    return new Promise((resolve, reject) => {
      const request = index.getAll(value)
      request.onsuccess = () => resolve(request.result)
      request.onerror = () => reject(request.error)
    })
  }
  
  /**
   * 保存消息數據
   */
  async saveMessages(messages) {
    await this._ensureInitialized()
    
    const transaction = this.db.transaction(['messages'], 'readwrite')
    const store = transaction.objectStore('messages')
    
    const promises = messages.map(message => {
      return new Promise((resolve, reject) => {
        const request = store.put({
          ...message,
          stored_at: Date.now()
        })
        request.onsuccess = () => resolve(request.result)
        request.onerror = () => reject(request.error)
      })
    })
    
    return Promise.all(promises)
  }
  
  /**
   * 獲取特定用戶的消息
   */
  async getMessagesByUser(lineUserId, limit = 50) {
    await this._ensureInitialized()
    
    const transaction = this.db.transaction(['messages'], 'readonly')
    const store = transaction.objectStore('messages')
    const index = store.index('line_user_id')
    
    return new Promise((resolve, reject) => {
      const messages = []
      const request = index.openCursor(lineUserId, 'prev') // 降序
      
      request.onsuccess = (event) => {
        const cursor = event.target.result
        if (cursor && messages.length < limit) {
          messages.push(cursor.value)
          cursor.continue()
        } else {
          resolve(messages)
        }
      }
      
      request.onerror = () => reject(request.error)
    })
  }
  
  /**
   * 緩存數據
   */
  async setCache(key, value, ttl = 300000) {
    await this._ensureInitialized()
    
    const transaction = this.db.transaction(['cache'], 'readwrite')
    const store = transaction.objectStore('cache')
    
    const cacheItem = {
      key,
      value,
      expiry: Date.now() + ttl,
      created_at: Date.now()
    }
    
    return new Promise((resolve, reject) => {
      const request = store.put(cacheItem)
      request.onsuccess = () => resolve(request.result)
      request.onerror = () => reject(request.error)
    })
  }
  
  /**
   * 獲取緩存數據
   */
  async getCache(key) {
    await this._ensureInitialized()
    
    const transaction = this.db.transaction(['cache'], 'readonly')
    const store = transaction.objectStore('cache')
    
    return new Promise((resolve, reject) => {
      const request = store.get(key)
      request.onsuccess = () => {
        const result = request.result
        
        if (!result) {
          resolve(null)
          return
        }
        
        // 檢查是否過期
        if (Date.now() > result.expiry) {
          // 異步刪除過期項目
          this.deleteCache(key).catch(console.error)
          resolve(null)
          return
        }
        
        resolve(result.value)
      }
      request.onerror = () => reject(request.error)
    })
  }
  
  /**
   * 刪除緩存數據
   */
  async deleteCache(key) {
    await this._ensureInitialized()
    
    const transaction = this.db.transaction(['cache'], 'readwrite')
    const store = transaction.objectStore('cache')
    
    return new Promise((resolve, reject) => {
      const request = store.delete(key)
      request.onsuccess = () => resolve(true)
      request.onerror = () => reject(request.error)
    })
  }
  
  /**
   * 清理過期的緩存
   */
  async cleanupExpiredCache() {
    await this._ensureInitialized()
    
    const transaction = this.db.transaction(['cache'], 'readwrite')
    const store = transaction.objectStore('cache')
    const index = store.index('expiry')
    
    const now = Date.now()
    let deletedCount = 0
    
    return new Promise((resolve, reject) => {
      const range = IDBKeyRange.upperBound(now)
      const request = index.openCursor(range)
      
      request.onsuccess = (event) => {
        const cursor = event.target.result
        if (cursor) {
          cursor.delete()
          deletedCount++
          cursor.continue()
        } else {
          resolve(deletedCount)
        }
      }
      
      request.onerror = () => reject(request.error)
    })
  }
  
  /**
   * 保存用戶數據
   */
  async saveUserData(type, data) {
    await this._ensureInitialized()
    
    const transaction = this.db.transaction(['userData'], 'readwrite')
    const store = transaction.objectStore('userData')
    
    const userData = {
      id: `${type}_${Date.now()}`,
      type,
      data,
      updated_at: Date.now()
    }
    
    return new Promise((resolve, reject) => {
      const request = store.put(userData)
      request.onsuccess = () => resolve(request.result)
      request.onerror = () => reject(request.error)
    })
  }
  
  /**
   * 獲取用戶數據
   */
  async getUserData(type) {
    await this._ensureInitialized()
    
    const transaction = this.db.transaction(['userData'], 'readonly')
    const store = transaction.objectStore('userData')
    const index = store.index('type')
    
    return new Promise((resolve, reject) => {
      const request = index.getAll(type)
      request.onsuccess = () => resolve(request.result)
      request.onerror = () => reject(request.error)
    })
  }
  
  /**
   * 獲取數據庫統計信息
   */
  async getStats() {
    await this._ensureInitialized()
    
    const stats = {}
    const storeNames = ['conversations', 'messages', 'cache', 'userData']
    
    for (const storeName of storeNames) {
      const transaction = this.db.transaction([storeName], 'readonly')
      const store = transaction.objectStore(storeName)
      
      stats[storeName] = await new Promise((resolve, reject) => {
        const request = store.count()
        request.onsuccess = () => resolve(request.result)
        request.onerror = () => reject(request.error)
      })
    }
    
    return stats
  }
  
  /**
   * 清空所有數據
   */
  async clearAll() {
    await this._ensureInitialized()
    
    const storeNames = ['conversations', 'messages', 'cache', 'userData']
    const transaction = this.db.transaction(storeNames, 'readwrite')
    
    const promises = storeNames.map(storeName => {
      return new Promise((resolve, reject) => {
        const request = transaction.objectStore(storeName).clear()
        request.onsuccess = () => resolve()
        request.onerror = () => reject(request.error)
      })
    })
    
    return Promise.all(promises)
  }
  
  /**
   * 關閉數據庫連接
   */
  close() {
    if (this.db) {
      this.db.close()
      this.db = null
      this.isInitialized = false
    }
  }
}