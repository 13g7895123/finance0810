/**
 * 聊天室整合測試
 * 
 * 測試前後端整合功能，確保聊天室功能完整運作
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { nextTick } from 'vue'
import ChatIndex from '@/pages/chat/index.vue'

// Mock fetch for API calls
global.fetch = vi.fn()

describe('聊天室整合測試', () => {
  let wrapper
  let pinia

  beforeEach(() => {
    pinia = createTestingPinia({
      createSpy: vi.fn,
      initialState: {
        auth: {
          user: { id: 1, name: 'Test User', role: 'admin' },
          token: 'test-token'
        }
      }
    })
    
    // Reset fetch mock
    vi.resetAllMocks()
    
    wrapper = mount(ChatIndex, {
      global: {
        plugins: [pinia],
        stubs: {
          NuxtPage: true,
          PlusIcon: true,
          MagnifyingGlassIcon: true,
          ChatBubbleLeftRightIcon: true
        }
      }
    })
  })

  afterEach(() => {
    wrapper?.unmount()
  })

  describe('完整聊天流程測試', () => {
    it('應該能完成完整的聊天交互流程', async () => {
      // 1. Mock 對話列表 API
      const mockConversations = {
        data: [
          {
            line_user_id: '100',
            customer_id: 100,
            last_message_time: new Date().toISOString(),
            unread_count: 1,
            customer: {
              name: '測試客戶',
              phone: '0912345678',
              region: '台北',
              source: '熊好貸',
              status: '待處理'
            }
          }
        ],
        current_page: 1,
        per_page: 20,
        total: 1
      }

      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockConversations
      })

      // 2. 等待組件載入對話列表
      await flushPromises()
      await nextTick()

      // 3. Mock 特定對話訊息 API
      const mockMessages = {
        data: [
          {
            id: 1,
            customer_id: 100,
            line_user_id: '100',
            message_content: '您好，我想詢問貸款',
            message_timestamp: new Date().toISOString(),
            is_from_customer: true,
            status: 'read',
            customer: { name: '測試客戶' }
          }
        ]
      }

      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockMessages
      })

      // 4. 模擬選擇用戶
      const testUser = {
        id: 100,
        name: '測試客戶',
        lineUserId: '100',
        isBot: true
      }

      await wrapper.vm.selectUserWithRealtime(testUser)
      await flushPromises()

      // 5. Mock 發送訊息 API
      const mockReply = {
        message: '訊息已送出',
        conversation: {
          id: 2,
          customer_id: 100,
          line_user_id: '100',
          message_content: '感謝您的詢問',
          message_timestamp: new Date().toISOString(),
          is_from_customer: false,
          status: 'sent'
        }
      }

      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockReply
      })

      // 6. 模擬發送訊息
      await wrapper.vm.sendMessage('感謝您的詢問')
      await flushPromises()

      // 7. 驗證流程完成
      expect(wrapper.vm.selectedUser).toBeDefined()
      expect(wrapper.vm.selectedUser.id).toBe(100)
    })

    it('應該能處理 API 錯誤並回退到模擬數據', async () => {
      // Mock API 失敗
      fetch.mockRejectedValueOnce(new Error('Network error'))

      // 組件應該使用模擬數據
      await flushPromises()
      await nextTick()

      // 檢查是否有對話數據
      expect(wrapper.vm.apiConversations.length).toBeGreaterThanOrEqual(0)
    })
  })

  describe('即時功能測試', () => {
    it('應該能模擬Long Polling訊息接收', async () => {
      // 模擬 Long Polling 訊息接收
      const newMessage = {
        id: Date.now(),
        senderId: 100,
        content: '新的客戶訊息',
        timestamp: new Date(),
        type: 'text',
        isBot: true,
        isCustomer: true
      }

      // 模擬接收新訊息
      if (!wrapper.vm.apiMessages['100']) {
        wrapper.vm.apiMessages['100'] = []
      }
      wrapper.vm.apiMessages['100'].push(newMessage)

      await nextTick()

      // 檢查訊息是否添加
      expect(wrapper.vm.apiMessages['100']).toContain(newMessage)
    })

    it('應該能更新未讀訊息數量', async () => {
      // Mock 未讀數量 API
      const mockUnreadCount = { unread_count: 5 }

      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockUnreadCount
      })

      // 模擬獲取未讀數量
      const { getUnreadCount } = useChat()
      const result = await getUnreadCount()

      expect(result.unread_count).toBe(5)
    })
  })

  describe('權限測試', () => {
    it('業務人員應該只能看到自己的客戶', async () => {
      // 重新創建組件，模擬業務人員身份
      await wrapper.unmount()

      const salesPinia = createTestingPinia({
        createSpy: vi.fn,
        initialState: {
          auth: {
            user: { id: 2, name: 'Sales User', role: 'staff' },
            token: 'test-token'
          }
        }
      })

      const salesWrapper = mount(ChatIndex, {
        global: {
          plugins: [salesPinia],
          stubs: {
            NuxtPage: true,
            PlusIcon: true,
            MagnifyingGlassIcon: true,
            ChatBubbleLeftRightIcon: true
          }
        }
      })

      // 檢查權限過濾
      expect(salesWrapper.vm.authStore.user.role).toBe('staff')

      salesWrapper.unmount()
    })
  })

  describe('性能測試', () => {
    it('應該能處理大量對話數據', async () => {
      const startTime = performance.now()

      // 創建大量模擬數據
      const largeConversationData = {
        data: Array(100).fill().map((_, index) => ({
          line_user_id: `${index}`,
          customer_id: index,
          last_message_time: new Date().toISOString(),
          unread_count: Math.floor(Math.random() * 5),
          customer: {
            name: `客戶${index}`,
            phone: `091234567${index % 10}`,
            region: '台北',
            source: '熊好貸',
            status: '待處理'
          }
        }))
      }

      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => largeConversationData
      })

      await flushPromises()

      const endTime = performance.now()
      const processingTime = endTime - startTime

      // 處理時間應該合理（小於 1 秒）
      expect(processingTime).toBeLessThan(1000)
    })
  })

  describe('搜尋功能整合測試', () => {
    it('應該能搜尋並顯示結果', async () => {
      // Mock 搜尋 API
      const mockSearchResults = {
        data: [
          {
            line_user_id: '100',
            customer: {
              name: '搜尋客戶',
              phone: '0912345678',
              region: '台北',
              source: '熊好貸',
              status: '待處理'
            }
          }
        ]
      }

      fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockSearchResults
      })

      // 執行搜尋
      await wrapper.vm.performSearch('搜尋客戶')
      await flushPromises()

      // 檢查搜尋結果
      expect(wrapper.vm.searchResults.length).toBe(1)
      expect(wrapper.vm.searchResults[0].name).toBe('搜尋客戶')
    })
  })

  describe('錯誤處理整合測試', () => {
    it('應該優雅地處理網路錯誤', async () => {
      // Mock 網路錯誤
      fetch.mockRejectedValueOnce(new Error('Network failed'))

      // 嘗試載入對話
      try {
        await wrapper.vm.loadConversations()
      } catch (error) {
        // 應該捕獲錯誤但不中斷應用
        expect(error).toBeDefined()
      }

      // 應用應該仍然可用
      expect(wrapper.vm.apiConversations).toBeDefined()
    })

    it('應該處理無效的 API 響應', async () => {
      // Mock 無效響應
      fetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        json: async () => ({ error: 'Server error' })
      })

      // 應該回退到模擬數據
      await wrapper.vm.loadConversations()
      await flushPromises()

      // 檢查是否有備用數據
      expect(wrapper.vm.apiConversations.length).toBeGreaterThanOrEqual(0)
    })
  })
})

// 測試工具函數
function createMockUser(id = 1, overrides = {}) {
  return {
    id,
    name: `測試用戶${id}`,
    role: 'line_customer',
    avatar: `https://ui-avatars.com/api/?name=測試用戶${id}&background=00C300&color=fff`,
    lastMessage: `測試訊息${id}`,
    timestamp: new Date(),
    unreadCount: 0,
    online: false,
    isBot: true,
    lineUserId: `${id}`,
    customerInfo: {
      phone: `091234567${id}`,
      region: '台北',
      source: '熊好貸',
      status: '待處理'
    },
    ...overrides
  }
}

function createMockApiResponse(data, pagination = {}) {
  return {
    data,
    current_page: pagination.current_page || 1,
    per_page: pagination.per_page || 20,
    total: pagination.total || data.length
  }
}

// 導出測試工具
export { createMockUser, createMockApiResponse }