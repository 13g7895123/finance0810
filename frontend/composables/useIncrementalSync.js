import { ref, computed } from 'vue'

export const useIncrementalSync = () => {
  const syncState = ref({
    conversations: { version: 0, data: new Map() },
    customers: { version: 0, data: new Map() }
  })
  
  const lastSyncTime = ref(null)
  const isSyncing = ref(false)
  const syncErrors = ref([])
  
  /**
   * 執行增量同步
   */
  const performSync = async (entityType, options = {}) => {
    if (isSyncing.value) return
    
    isSyncing.value = true
    syncErrors.value = []
    
    try {
      const clientVersion = syncState.value[entityType].version
      const response = await $fetch(`/api/sync/${entityType}`, {
        params: {
          client_version: clientVersion,
          limit: options.limit || 100
        }
      })
      
      if (response.success) {
        await processSyncResponse(entityType, response.data)
        lastSyncTime.value = Date.now()
      }
      
      return response
      
    } catch (error) {
      console.error('Sync failed:', error)
      syncErrors.value.push(error.message || 'Sync failed')
      throw error
    } finally {
      isSyncing.value = false
    }
  }
  
  /**
   * 處理同步響應
   */
  const processSyncResponse = async (entityType, syncData) => {
    const entityState = syncState.value[entityType]
    
    if (syncData.sync_type === 'full') {
      // 全量同步 - 清空並重新填充
      entityState.data.clear()
      syncData.data.forEach(item => {
        entityState.data.set(item.id, item)
      })
    } else {
      // 增量同步 - 應用變更
      syncData.changes.forEach(change => {
        if (change.operation === 'upsert') {
          entityState.data.set(change.data.id, change.data)
        }
      })
      
      // 處理刪除
      syncData.deletes.forEach(deleteOp => {
        entityState.data.delete(deleteOp.id)
      })
    }
    
    // 更新版本號
    entityState.version = syncData.current_version
    
    // 觸發數據變更事件
    await triggerDataChange(entityType)
  }
  
  /**
   * 觸發數據變更事件
   */
  const triggerDataChange = async (entityType) => {
    const event = new CustomEvent(`sync:${entityType}:updated`, {
      detail: {
        entityType,
        data: Array.from(syncState.value[entityType].data.values()),
        version: syncState.value[entityType].version
      }
    })
    
    window.dispatchEvent(event)
  }
  
  /**
   * 校驗數據完整性
   */
  const validateIntegrity = async (entityType) => {
    const entityData = Array.from(syncState.value[entityType].data.values())
    
    if (entityData.length === 0) return { valid: true, errors: [] }
    
    try {
      const response = await $fetch(`/api/sync/${entityType}/validate`, {
        method: 'POST',
        body: {
          data: entityData.map(item => ({
            id: item.id,
            version: item.version
          }))
        }
      })
      
      return {
        valid: response.is_valid,
        errors: response.errors || []
      }
      
    } catch (error) {
      console.error('Integrity validation failed:', error)
      return { valid: false, errors: [error.message || 'Validation failed'] }
    }
  }
  
  /**
   * 強制全量同步
   */
  const forceFullSync = async (entityType) => {
    syncState.value[entityType].version = 0
    return await performSync(entityType)
  }
  
  /**
   * 獲取實體數據
   */
  const getEntityData = (entityType) => {
    return Array.from(syncState.value[entityType].data.values())
  }
  
  /**
   * 按 ID 獲取實體
   */
  const getEntityById = (entityType, id) => {
    return syncState.value[entityType].data.get(id)
  }
  
  /**
   * 更新本地實體數據
   */
  const updateLocalEntity = (entityType, id, data) => {
    syncState.value[entityType].data.set(id, data)
    triggerDataChange(entityType)
  }
  
  /**
   * 刪除本地實體數據
   */
  const removeLocalEntity = (entityType, id) => {
    syncState.value[entityType].data.delete(id)
    triggerDataChange(entityType)
  }
  
  /**
   * 獲取同步狀態
   */
  const getSyncStatus = computed(() => ({
    isSyncing: isSyncing.value,
    lastSync: lastSyncTime.value,
    errors: syncErrors.value,
    versions: Object.keys(syncState.value).reduce((acc, type) => {
      acc[type] = syncState.value[type].version
      return acc
    }, {}),
    entityCounts: Object.keys(syncState.value).reduce((acc, type) => {
      acc[type] = syncState.value[type].data.size
      return acc
    }, {})
  }))
  
  /**
   * 清除同步錯誤
   */
  const clearSyncErrors = () => {
    syncErrors.value = []
  }
  
  /**
   * 獲取同步統計信息
   */
  const getSyncStats = async (entityType) => {
    try {
      const response = await $fetch(`/api/sync/${entityType}/stats`)
      return response.data
    } catch (error) {
      console.error('Failed to get sync stats:', error)
      throw error
    }
  }
  
  return {
    // 狀態
    syncState,
    isSyncing,
    syncErrors,
    getSyncStatus,
    
    // 方法
    performSync,
    validateIntegrity,
    forceFullSync,
    getEntityData,
    getEntityById,
    updateLocalEntity,
    removeLocalEntity,
    clearSyncErrors,
    getSyncStats
  }
}