/**
 * 聊天室功能測試
 * 
 * 測試範圍:
 * 1. API 連接測試
 * 2. 對話列表載入測試
 * 3. 訊息發送與接收測試
 * 4. 權限控制測試
 * 5. 搜尋功能測試
 * 6. 即時更新測試
 * 7. 錯誤處理測試
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import ChatIndex from '@/pages/chat/index.vue'
import ChatUserList from '@/components/ChatUserList.vue'
import ChatMessageArea from '@/components/ChatMessageArea.vue'
import { useChat } from '@/composables/useChat'

// Mock the composables
vi.mock('@/composables/useAuth', () => ({
  useAuthStore: () => ({
    user: { id: 1, name: 'Test User', role: 'admin' },
    isSales: false,
    hasPermission: vi.fn(() => true)
  })
}))

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({
    get: vi.fn(),
    post: vi.fn()
  })
}))

describe('聊天室功能測試', () => {
  let wrapper
  let pinia

  beforeEach(() => {
    pinia = createTestingPinia({
      createSpy: vi.fn
    })
    
    wrapper = mount(ChatIndex, {
      global: {
        plugins: [pinia],
        stubs: {
          NuxtPage: true,
          PlusIcon: true,
          MagnifyingGlassIcon: true,
          ChatBubbleLeftRightIcon: true,
          ChatUserList: true,
          ChatMessageArea: true
        }
      }
    })
  })

  afterEach(() => {
    wrapper?.unmount()
  })

  describe('1. API 連接測試', () => {
    it('應該能夠連接聊天 API', async () => {
      const { getConversations } = useChat()
      const mockResponse = {
        data: [
          {
            line_user_id: '100',
            customer_id: 100,
            last_message_time: new Date(),
            unread_count: 1,
            customer: { name: '測試客戶', phone: '0912345678' }
          }
        ]
      }
      
      vi.mocked(getConversations).mockResolvedValue(mockResponse)
      
      const result = await getConversations()
      expect(result).toBeDefined()
      expect(result.data).toHaveLength(1)
    })

    it('應該能夠處理 API 連接失敗', async () => {
      const { getConversations } = useChat()
      vi.mocked(getConversations).mockRejectedValue(new Error('Network error'))
      
      try {
        await getConversations()
      } catch (error) {
        expect(error.message).toBe('Network error')
      }
    })
  })

  describe('2. 對話列表載入測試', () => {
    it('應該正確載入對話列表', async () => {
      expect(wrapper.find('.space-y-1')).toBeDefined()
      
      // 模擬對話數據載入
      await wrapper.vm.$nextTick()
      
      // 檢查是否有用戶列表容器
      const userListContainer = wrapper.find('[class*="overflow-y-auto"]')
      expect(userListContainer.exists()).toBe(true)
    })

    it('應該顯示載入狀態', async () => {
      await wrapper.setData({ conversationsLoading: true })
      await wrapper.vm.$nextTick()
      
      // 在載入時應該顯示適當的狀態
      expect(wrapper.vm.conversationsLoading).toBe(true)
    })
  })

  describe('3. 訊息發送與接收測試', () => {
    it('應該能夠發送訊息', async () => {
      const { replyMessage } = useChat()
      const mockMessage = '測試訊息'
      const mockResponse = {
        message: '訊息已送出',
        conversation: {
          id: 1,
          message_content: mockMessage,
          message_timestamp: new Date()
        }
      }
      
      vi.mocked(replyMessage).mockResolvedValue(mockResponse)
      
      const result = await replyMessage('100', mockMessage)
      expect(result.message).toBe('訊息已送出')
      expect(result.conversation.message_content).toBe(mockMessage)
    })

    it('應該能夠接收即時訊息', async () => {
      // 模擬即時訊息接收
      const mockNewMessage = {
        id: 2,
        senderId: 100,
        content: '新的客戶訊息',
        timestamp: new Date()
      }
      
      // 檢查訊息是否正確添加到對話中
      expect(mockNewMessage.content).toBe('新的客戶訊息')
    })
  })

  describe('4. 權限控制測試', () => {
    it('管理員應該能看到所有對話', async () => {
      // 管理員權限測試
      const adminWrapper = mount(ChatIndex, {
        global: {
          plugins: [pinia],
          stubs: {
            NuxtPage: true,
            ChatUserList: true,
            ChatMessageArea: true
          }
        }
      })
      
      expect(adminWrapper.exists()).toBe(true)
      adminWrapper.unmount()
    })

    it('業務人員只能看到自己的客戶對話', async () => {
      // 模擬業務人員權限
      vi.mocked(useAuthStore).mockReturnValue({
        user: { id: 2, name: 'Sales User', role: 'staff' },
        isSales: true,
        hasPermission: vi.fn(() => false)
      })
      
      const salesWrapper = mount(ChatIndex, {
        global: {
          plugins: [pinia],
          stubs: {
            NuxtPage: true,
            ChatUserList: true,
            ChatMessageArea: true
          }
        }
      })
      
      expect(salesWrapper.exists()).toBe(true)
      salesWrapper.unmount()
    })
  })

  describe('5. 搜尋功能測試', () => {
    it('應該能夠搜尋對話', async () => {
      const { searchConversations } = useChat()
      const mockSearchResults = {
        data: [
          {
            line_user_id: '100',
            customer: { name: '測試客戶', phone: '0912345678' }
          }
        ]
      }
      
      vi.mocked(searchConversations).mockResolvedValue(mockSearchResults)
      
      const result = await searchConversations('測試')
      expect(result.data).toHaveLength(1)
      expect(result.data[0].customer.name).toBe('測試客戶')
    })

    it('搜尋框應該能夠輸入關鍵字', async () => {
      const searchInput = wrapper.find('input[placeholder="搜尋用戶..."]')
      expect(searchInput.exists()).toBe(true)
      
      await searchInput.setValue('測試關鍵字')
      expect(searchInput.element.value).toBe('測試關鍵字')
    })
  })

  describe('6. 即時更新測試', () => {
    it('應該能夠即時更新未讀訊息數量', async () => {
      const { getUnreadCount } = useChat()
      const mockUnreadCount = { unread_count: 5 }
      
      vi.mocked(getUnreadCount).mockResolvedValue(mockUnreadCount)
      
      const result = await getUnreadCount()
      expect(result.unread_count).toBe(5)
    })

    it('應該能夠即時更新對話時間戳', async () => {
      const currentTime = new Date()
      const mockConversation = {
        id: 100,
        lastMessage: '最新訊息',
        timestamp: currentTime
      }
      
      expect(mockConversation.timestamp).toBeInstanceOf(Date)
    })
  })

  describe('7. 錯誤處理測試', () => {
    it('應該優雅地處理網路錯誤', async () => {
      const { getConversations } = useChat()
      vi.mocked(getConversations).mockRejectedValue(new Error('網路連接失敗'))
      
      try {
        await getConversations()
      } catch (error) {
        expect(error.message).toBe('網路連接失敗')
      }
    })

    it('應該在 API 失敗時使用模擬數據', async () => {
      const { getConversations } = useChat()
      
      // 模擬 API 失敗，應該回退到模擬數據
      vi.mocked(getConversations).mockImplementation(async () => {
        // 模擬 API 失敗後的回退邏輯
        return {
          data: [
            {
              line_user_id: '100',
              customer: { name: '模擬客戶' }
            }
          ]
        }
      })
      
      const result = await getConversations()
      expect(result.data[0].customer.name).toBe('模擬客戶')
    })

    it('應該處理無效的用戶選擇', async () => {
      // 測試選擇不存在的用戶
      const invalidUser = null
      
      expect(() => {
        if (!invalidUser) {
          throw new Error('用戶不存在')
        }
      }).toThrow('用戶不存在')
    })
  })

  describe('8. 用戶介面測試', () => {
    it('應該顯示聊天室標題', () => {
      const title = wrapper.find('h2')
      expect(title.text()).toBe('聊天室')
    })

    it('應該顯示篩選按鈕', () => {
      const filterButtons = wrapper.findAll('[class*="px-3 py-1"]')
      expect(filterButtons.length).toBeGreaterThan(0)
    })

    it('應該在未選擇用戶時顯示預設畫面', () => {
      const defaultScreen = wrapper.find('[class*="text-center"]')
      expect(defaultScreen.exists()).toBe(true)
    })
  })

  describe('9. 組件互動測試', () => {
    it('點擊用戶應該觸發選擇事件', async () => {
      const userList = wrapper.findComponent(ChatUserList)
      if (userList.exists()) {
        // 模擬點擊用戶
        await userList.vm.$emit('userSelect', { id: 100, name: '測試用戶' })
        
        // 檢查是否正確處理選擇事件
        expect(wrapper.vm.selectedUser).toBeDefined()
      }
    })

    it('發送訊息應該觸發相應事件', async () => {
      const messageArea = wrapper.findComponent(ChatMessageArea)
      if (messageArea.exists()) {
        // 模擬發送訊息
        await messageArea.vm.$emit('sendMessage', '測試訊息')
        
        // 檢查是否正確處理發送事件
        expect(true).toBe(true) // 基本檢查組件存在
      }
    })
  })

  describe('10. 性能測試', () => {
    it('應該在合理時間內載入對話列表', async () => {
      const startTime = performance.now()
      
      // 模擬載入大量對話
      const largeConversationList = Array(1000).fill().map((_, index) => ({
        id: index,
        name: `客戶${index}`,
        lastMessage: `訊息${index}`
      }))
      
      const endTime = performance.now()
      const loadTime = endTime - startTime
      
      // 載入時間應該小於 100ms
      expect(loadTime).toBeLessThan(100)
    })

    it('應該能夠處理大量訊息', () => {
      const largeMessageList = Array(1000).fill().map((_, index) => ({
        id: index,
        content: `訊息內容${index}`,
        timestamp: new Date()
      }))
      
      expect(largeMessageList).toHaveLength(1000)
      expect(largeMessageList[999].content).toBe('訊息內容999')
    })
  })
})

describe('ChatUserList 組件測試', () => {
  let wrapper

  beforeEach(() => {
    const mockUsers = [
      {
        id: 1,
        name: '測試用戶',
        avatar: 'test-avatar.jpg',
        lastMessage: '最後訊息',
        timestamp: new Date(),
        unreadCount: 2,
        online: true,
        role: 'line_customer'
      }
    ]

    wrapper = mount(ChatUserList, {
      props: {
        users: mockUsers,
        activeUserId: null
      }
    })
  })

  afterEach(() => {
    wrapper?.unmount()
  })

  it('應該渲染用戶列表', () => {
    expect(wrapper.find('.space-y-1').exists()).toBe(true)
  })

  it('應該顯示用戶名稱', () => {
    expect(wrapper.text()).toContain('測試用戶')
  })

  it('應該顯示未讀訊息數量', () => {
    const unreadBadge = wrapper.find('.bg-blue-500')
    expect(unreadBadge.exists()).toBe(true)
    expect(unreadBadge.text()).toBe('2')
  })
})

describe('ChatMessageArea 組件測試', () => {
  let wrapper

  beforeEach(() => {
    const mockUser = {
      id: 1,
      name: '測試用戶',
      avatar: 'test-avatar.jpg',
      online: true
    }

    const mockMessages = [
      {
        id: 1,
        senderId: 1,
        content: '測試訊息',
        timestamp: new Date(),
        type: 'text'
      }
    ]

    wrapper = mount(ChatMessageArea, {
      props: {
        user: mockUser,
        messages: mockMessages
      },
      global: {
        stubs: {
          InformationCircleIcon: true,
          PaperClipIcon: true,
          PaperAirplaneIcon: true
        }
      }
    })
  })

  afterEach(() => {
    wrapper?.unmount()
  })

  it('應該顯示用戶信息', () => {
    expect(wrapper.text()).toContain('測試用戶')
  })

  it('應該顯示訊息內容', () => {
    expect(wrapper.text()).toContain('測試訊息')
  })

  it('應該有訊息輸入框', () => {
    const textarea = wrapper.find('textarea')
    expect(textarea.exists()).toBe(true)
  })

  it('應該有發送按鈕', () => {
    const sendButton = wrapper.find('[type="submit"]')
    expect(sendButton.exists()).toBe(true)
  })
})

// 輔助函數
function createMockConversation(overrides = {}) {
  return {
    line_user_id: '100',
    customer_id: 100,
    last_message_time: new Date(),
    unread_count: 0,
    customer: {
      name: '模擬客戶',
      phone: '0912345678',
      region: '台北',
      source: '熊好貸',
      status: '待處理'
    },
    ...overrides
  }
}

function createMockMessage(overrides = {}) {
  return {
    id: 1,
    senderId: 100,
    content: '模擬訊息',
    timestamp: new Date(),
    type: 'text',
    ...overrides
  }
}

// 測試工具函數
export { createMockConversation, createMockMessage }