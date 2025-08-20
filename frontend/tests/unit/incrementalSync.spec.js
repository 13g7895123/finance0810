import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { useIncrementalSync } from '~/composables/useIncrementalSync'

// Mock fetch function
global.$fetch = vi.fn()

describe('useIncrementalSync', () => {
  let sync
  
  beforeEach(() => {
    sync = useIncrementalSync()
    vi.clearAllMocks()
  })
  
  afterEach(() => {
    sync.clearSyncErrors()
  })
  
  it('should initialize with correct default state', () => {
    expect(sync.isSyncing.value).toBe(false)
    expect(sync.syncErrors.value).toEqual([])
    expect(sync.syncState.value.conversations.version).toBe(0)
    expect(sync.syncState.value.customers.version).toBe(0)
    expect(sync.syncState.value.conversations.data.size).toBe(0)
    expect(sync.syncState.value.customers.data.size).toBe(0)
  })
  
  it('should perform full sync correctly', async () => {
    const mockResponse = {
      success: true,
      data: {
        sync_type: 'full',
        client_version: 0,
        current_version: 5,
        data: [
          { id: 1, name: 'Customer 1', version: 3 },
          { id: 2, name: 'Customer 2', version: 5 }
        ]
      }
    }
    
    global.$fetch.mockResolvedValue(mockResponse)
    
    const result = await sync.performSync('customers')
    
    expect(global.$fetch).toHaveBeenCalledWith('/api/sync/customers', {
      params: {
        client_version: 0,
        limit: 100
      }
    })
    
    expect(result.success).toBe(true)
    expect(sync.syncState.value.customers.version).toBe(5)
    expect(sync.syncState.value.customers.data.size).toBe(2)
    expect(sync.getEntityById('customers', 1)).toEqual({ id: 1, name: 'Customer 1', version: 3 })
  })
  
  it('should perform incremental sync correctly', async () => {
    // 設置初始狀態
    sync.syncState.value.customers.version = 3
    sync.syncState.value.customers.data.set(1, { id: 1, name: 'Customer 1', version: 3 })
    
    const mockResponse = {
      success: true,
      data: {
        sync_type: 'incremental',
        client_version: 3,
        current_version: 7,
        changes: [
          {
            operation: 'upsert',
            data: { id: 2, name: 'Customer 2', version: 7 }
          }
        ],
        deletes: []
      }
    }
    
    global.$fetch.mockResolvedValue(mockResponse)
    
    await sync.performSync('customers')
    
    expect(global.$fetch).toHaveBeenCalledWith('/api/sync/customers', {
      params: {
        client_version: 3,
        limit: 100
      }
    })
    
    expect(sync.syncState.value.customers.version).toBe(7)
    expect(sync.syncState.value.customers.data.size).toBe(2)
    expect(sync.getEntityById('customers', 2)).toEqual({ id: 2, name: 'Customer 2', version: 7 })
  })
  
  it('should handle deletes in incremental sync', async () => {
    // 設置初始狀態
    sync.syncState.value.customers.version = 3
    sync.syncState.value.customers.data.set(1, { id: 1, name: 'Customer 1', version: 3 })
    sync.syncState.value.customers.data.set(2, { id: 2, name: 'Customer 2', version: 3 })
    
    const mockResponse = {
      success: true,
      data: {
        sync_type: 'incremental',
        client_version: 3,
        current_version: 5,
        changes: [],
        deletes: [
          { operation: 'delete', id: 2, version: 5 }
        ]
      }
    }
    
    global.$fetch.mockResolvedValue(mockResponse)
    
    await sync.performSync('customers')
    
    expect(sync.syncState.value.customers.data.size).toBe(1)
    expect(sync.getEntityById('customers', 2)).toBeUndefined()
    expect(sync.getEntityById('customers', 1)).toBeDefined()
  })
  
  it('should validate data integrity', async () => {
    // 設置測試數據
    sync.syncState.value.customers.data.set(1, { id: 1, version: 3 })
    sync.syncState.value.customers.data.set(2, { id: 2, version: 5 })
    
    const mockResponse = {
      success: true,
      is_valid: false,
      errors: [
        {
          type: 'version_mismatch',
          id: 1,
          client_version: 3,
          server_version: 4
        }
      ]
    }
    
    global.$fetch.mockResolvedValue(mockResponse)
    
    const result = await sync.validateIntegrity('customers')
    
    expect(global.$fetch).toHaveBeenCalledWith('/api/sync/customers/validate', {
      method: 'POST',
      body: {
        data: [
          { id: 1, version: 3 },
          { id: 2, version: 5 }
        ]
      }
    })
    
    expect(result.valid).toBe(false)
    expect(result.errors).toHaveLength(1)
    expect(result.errors[0].type).toBe('version_mismatch')
  })
  
  it('should force full sync', async () => {
    // 設置非零版本
    sync.syncState.value.customers.version = 5
    
    const mockResponse = {
      success: true,
      data: {
        sync_type: 'full',
        client_version: 0,
        current_version: 10,
        data: [{ id: 1, name: 'Customer 1', version: 10 }]
      }
    }
    
    global.$fetch.mockResolvedValue(mockResponse)
    
    await sync.forceFullSync('customers')
    
    expect(global.$fetch).toHaveBeenCalledWith('/api/sync/customers', {
      params: {
        client_version: 0,
        limit: 100
      }
    })
    
    expect(sync.syncState.value.customers.version).toBe(10)
  })
  
  it('should handle sync errors', async () => {
    const error = new Error('Network error')
    global.$fetch.mockRejectedValue(error)
    
    await expect(sync.performSync('customers')).rejects.toThrow('Network error')
    
    expect(sync.syncErrors.value).toContain('Network error')
    expect(sync.isSyncing.value).toBe(false)
  })
  
  it('should prevent concurrent syncs', async () => {
    sync.isSyncing.value = true
    
    const result = await sync.performSync('customers')
    
    expect(result).toBeUndefined()
    expect(global.$fetch).not.toHaveBeenCalled()
  })
  
  it('should get entity data correctly', () => {
    sync.syncState.value.customers.data.set(1, { id: 1, name: 'Customer 1' })
    sync.syncState.value.customers.data.set(2, { id: 2, name: 'Customer 2' })
    
    const data = sync.getEntityData('customers')
    
    expect(data).toHaveLength(2)
    expect(data.find(item => item.id === 1)).toBeDefined()
    expect(data.find(item => item.id === 2)).toBeDefined()
  })
  
  it('should update local entity', () => {
    const eventSpy = vi.spyOn(window, 'dispatchEvent')
    
    sync.updateLocalEntity('customers', 1, { id: 1, name: 'Updated Customer' })
    
    expect(sync.getEntityById('customers', 1)).toEqual({ id: 1, name: 'Updated Customer' })
    expect(eventSpy).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'sync:customers:updated'
      })
    )
  })
  
  it('should remove local entity', () => {
    sync.syncState.value.customers.data.set(1, { id: 1, name: 'Customer 1' })
    
    sync.removeLocalEntity('customers', 1)
    
    expect(sync.getEntityById('customers', 1)).toBeUndefined()
    expect(sync.syncState.value.customers.data.size).toBe(0)
  })
  
  it('should get sync status correctly', () => {
    sync.syncState.value.customers.version = 5
    sync.syncState.value.customers.data.set(1, { id: 1 })
    sync.syncState.value.customers.data.set(2, { id: 2 })
    sync.lastSyncTime.value = 1234567890
    sync.syncErrors.value = ['Error 1']
    
    const status = sync.getSyncStatus.value
    
    expect(status.isSyncing).toBe(false)
    expect(status.lastSync).toBe(1234567890)
    expect(status.errors).toEqual(['Error 1'])
    expect(status.versions.customers).toBe(5)
    expect(status.entityCounts.customers).toBe(2)
  })
  
  it('should get sync stats', async () => {
    const mockStats = {
      success: true,
      data: {
        entity_type: 'customers',
        current_version: 10,
        total_entities: 50,
        recent_changes: 5
      }
    }
    
    global.$fetch.mockResolvedValue(mockStats)
    
    const stats = await sync.getSyncStats('customers')
    
    expect(global.$fetch).toHaveBeenCalledWith('/api/sync/customers/stats')
    expect(stats).toEqual(mockStats.data)
  })
  
  it('should clear sync errors', () => {
    sync.syncErrors.value = ['Error 1', 'Error 2']
    
    sync.clearSyncErrors()
    
    expect(sync.syncErrors.value).toEqual([])
  })
  
  it('should trigger data change events', async () => {
    const eventSpy = vi.spyOn(window, 'dispatchEvent')
    
    sync.syncState.value.customers.data.set(1, { id: 1, name: 'Customer 1' })
    sync.syncState.value.customers.version = 5
    
    await sync.triggerDataChange('customers')
    
    expect(eventSpy).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'sync:customers:updated',
        detail: expect.objectContaining({
          entityType: 'customers',
          version: 5,
          data: expect.arrayContaining([
            expect.objectContaining({ id: 1, name: 'Customer 1' })
          ])
        })
      })
    )
  })
})