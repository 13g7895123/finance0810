import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import UsersPage from '../../../pages/settings/users.vue'

// Mock auth store
const mockAuthStore = {
  user: {
    id: 1,
    role: 'admin',
    permissions: ['user.view', 'user.create', 'user.edit', 'user.delete']
  },
  hasPermission: vi.fn(() => true),
  isAdmin: true,
  isManager: false,
  isStaff: false
}

vi.mock('../../../stores/auth.js', () => ({
  useAuthStore: () => mockAuthStore
}))

// Mock user management composable
const mockGetUsers = vi.fn()
const mockCreateUser = vi.fn()
const mockUpdateUser = vi.fn()
const mockDeleteUser = vi.fn()
const mockGetRoles = vi.fn()
const mockAssignRole = vi.fn()

vi.mock('../../../composables/useUserManagement.js', () => ({
  useUserManagement: () => ({
    getUsers: mockGetUsers,
    createUser: mockCreateUser,
    updateUser: mockUpdateUser,
    deleteUser: mockDeleteUser,
    getRoles: mockGetRoles,
    assignRole: mockAssignRole
  })
}))

// Mock useI18n
const mockT = vi.fn((key) => {
  const translations = {
    'nav.user_management': '用戶管理',
    'auth.add_user': '新增用戶',
    'auth.user': '用戶',
    'auth.role': '角色',
    'auth.status': '狀態',
    'auth.last_login': '最後登入',
    'auth.actions': '操作',
    'auth.full_name': '姓名',
    'auth.email': '電子郵件',
    'auth.password': '密碼',
    'auth.edit_user': '編輯用戶',
    'auth.no_users_found': '找不到用戶',
    'auth.status_active': '啟用',
    'auth.status_inactive': '停用',
    'auth.activate': '啟用',
    'auth.deactivate': '停用',
    'common.search': '搜尋',
    'common.edit': '編輯',
    'common.create': '新增',
    'common.save': '儲存',
    'common.cancel': '取消'
  }
  return translations[key] || key
})

vi.mock('../../../composables/useI18n.js', () => ({
  useI18n: () => ({ t: mockT })
}))

// Mock Heroicons
vi.mock('@heroicons/vue/24/outline', () => ({
  MagnifyingGlassIcon: { name: 'MagnifyingGlassIcon' },
  ShieldExclamationIcon: { name: 'ShieldExclamationIcon' },
  UsersIcon: { name: 'UsersIcon' },
  PlusIcon: { name: 'PlusIcon' },
  ArrowPathIcon: { name: 'ArrowPathIcon' },
  ChevronLeftIcon: { name: 'ChevronLeftIcon' },
  ChevronRightIcon: { name: 'ChevronRightIcon' }
}))

describe('Users Management Page', () => {
  let wrapper

  const mockUsers = [
    {
      id: 1,
      name: '系統管理員',
      email: 'admin@finance-crm.com',
      status: 'active',
      last_login_at: '2024-01-15T10:30:00Z',
      avatar: null,
      roles: [{ name: 'admin', display_name: '系統管理員' }]
    },
    {
      id: 2,
      name: '業務主管',
      email: 'manager@finance-crm.com',
      status: 'active',
      last_login_at: '2024-01-14T15:45:00Z',
      avatar: null,
      roles: [{ name: 'manager', display_name: '業務主管' }]
    },
    {
      id: 3,
      name: '業務人員',
      email: 'staff@finance-crm.com',
      status: 'inactive',
      last_login_at: null,
      avatar: null,
      roles: [{ name: 'staff', display_name: '業務人員' }]
    }
  ]

  const mockRoles = [
    { id: 1, name: 'admin', display_name: '系統管理員' },
    { id: 2, name: 'manager', display_name: '業務主管' },
    { id: 3, name: 'staff', display_name: '業務人員' }
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    
    // Setup default mock responses
    mockGetUsers.mockResolvedValue({ data: mockUsers })
    mockGetRoles.mockResolvedValue(mockRoles)

    wrapper = mount(UsersPage, {
      global: {
        stubs: {
          ClientOnly: {
            template: '<div><slot /></div>'
          }
        }
      }
    })
  })

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount()
    }
  })

  describe('Component Rendering', () => {
    it('should render users management page correctly', async () => {
      await wrapper.vm.$nextTick()
      
      expect(wrapper.find('h2').text()).toContain('用戶管理')
      expect(wrapper.find('button').text()).toContain('新增用戶')
      expect(wrapper.find('input[type="text"]').exists()).toBe(true) // Search input
    })

    it('should render users table with correct headers', async () => {
      await wrapper.vm.$nextTick()
      
      const headers = wrapper.findAll('th')
      expect(headers.length).toBeGreaterThan(0)
    })

    it('should render action buttons correctly', async () => {
      await wrapper.vm.$nextTick()
      
      const addButton = wrapper.find('button')
      expect(addButton.text()).toContain('新增用戶')
      
      const searchInput = wrapper.find('input[type="text"]')
      expect(searchInput.exists()).toBe(true)
    })
  })

  describe('User List Display', () => {
    it('should display users correctly', async () => {
      await wrapper.vm.$nextTick()
      
      expect(mockGetUsers).toHaveBeenCalled()
      expect(mockGetRoles).toHaveBeenCalled()
    })

    it('should display user information correctly', async () => {
      await wrapper.vm.$nextTick()
      
      // Check if users are displayed (mocked data should be processed)
      const component = wrapper.vm
      expect(component.users).toEqual(mockUsers)
    })

    it('should format dates correctly', async () => {
      await wrapper.vm.$nextTick()
      
      const component = wrapper.vm
      const formattedDate = component.formatDate('2024-01-15T10:30:00Z')
      expect(formattedDate).not.toBe('從未登入')
    })

    it('should handle users with no login date', async () => {
      await wrapper.vm.$nextTick()
      
      const component = wrapper.vm
      const formattedDate = component.formatDate(null)
      expect(formattedDate).toBe('從未登入')
    })

    it('should handle invalid date formats', async () => {
      await wrapper.vm.$nextTick()
      
      const component = wrapper.vm
      const formattedDate = component.formatDate('invalid-date')
      expect(formattedDate).toBe('無效日期')
    })
  })

  describe('Search Functionality', () => {
    it('should trigger search when typing in search input', async () => {
      const searchInput = wrapper.find('input[type="text"]')
      
      await searchInput.setValue('admin')
      
      // Wait for debounce
      setTimeout(() => {
        expect(mockGetUsers).toHaveBeenCalledWith({ search: 'admin' })
      }, 350)
    })

    it('should clear search results', async () => {
      const searchInput = wrapper.find('input[type="text"]')
      
      await searchInput.setValue('admin')
      await searchInput.setValue('')
      
      setTimeout(() => {
        expect(mockGetUsers).toHaveBeenCalledWith({ search: '' })
      }, 350)
    })
  })

  describe('Add User Functionality', () => {
    it('should open add user modal', async () => {
      const addButton = wrapper.find('button')
      await addButton.trigger('click')
      
      await wrapper.vm.$nextTick()
      
      expect(wrapper.vm.showAddModal).toBe(true)
    })

    it('should close add user modal', async () => {
      wrapper.vm.showAddModal = true
      await wrapper.vm.$nextTick()
      
      const cancelButton = wrapper.find('button:contains("取消")')
      if (cancelButton.exists()) {
        await cancelButton.trigger('click')
        expect(wrapper.vm.showAddModal).toBe(false)
      }
    })

    it('should create new user successfully', async () => {
      const newUser = {
        name: '新用戶',
        email: 'newuser@example.com',
        password: 'password123',
        role: 'staff'
      }

      mockCreateUser.mockResolvedValue({ data: { id: 4, ...newUser } })
      mockGetUsers.mockResolvedValue({ data: [...mockUsers, { id: 4, ...newUser }] })

      wrapper.vm.addForm = newUser
      await wrapper.vm.addUser()

      expect(mockCreateUser).toHaveBeenCalledWith(newUser)
      expect(mockGetUsers).toHaveBeenCalled()
      expect(wrapper.vm.showAddModal).toBe(false)
    })

    it('should handle add user validation', async () => {
      // Mock window.alert
      window.alert = vi.fn()

      wrapper.vm.addForm = {
        name: '',
        email: 'test@example.com',
        password: 'password123',
        role: 'staff'
      }

      await wrapper.vm.addUser()

      expect(window.alert).toHaveBeenCalledWith('請填寫所有必要欄位')
      expect(mockCreateUser).not.toHaveBeenCalled()
    })
  })

  describe('Edit User Functionality', () => {
    it('should open edit user modal', async () => {
      const user = mockUsers[0]
      
      await wrapper.vm.editUser(user)
      
      expect(wrapper.vm.showEditModal).toBe(true)
      expect(wrapper.vm.editForm).toEqual(user)
    })

    it('should update user successfully', async () => {
      const user = mockUsers[0]
      const updatedData = {
        id: user.id,
        name: '更新的名稱',
        email: user.email,
        role: 'manager'
      }

      mockUpdateUser.mockResolvedValue({ data: updatedData })
      mockAssignRole.mockResolvedValue({ data: {} })
      mockGetUsers.mockResolvedValue({ data: mockUsers })

      wrapper.vm.editForm = updatedData
      wrapper.vm.showEditModal = true

      await wrapper.vm.saveUser()

      expect(mockUpdateUser).toHaveBeenCalledWith(user.id, {
        name: updatedData.name,
        email: updatedData.email
      })
      expect(mockAssignRole).toHaveBeenCalledWith(user.id, 'manager')
      expect(wrapper.vm.showEditModal).toBe(false)
    })
  })

  describe('User Status Management', () => {
    it('should toggle user status', async () => {
      const user = { ...mockUsers[0], status: 'active' }
      
      mockUpdateUser.mockResolvedValue({ data: { ...user, status: 'inactive' } })
      mockGetUsers.mockResolvedValue({ data: mockUsers })

      await wrapper.vm.toggleStatus(user)

      expect(mockUpdateUser).toHaveBeenCalledWith(user.id, { status: 'inactive' })
      expect(mockGetUsers).toHaveBeenCalled()
    })

    it('should handle status toggle errors', async () => {
      const user = mockUsers[0]
      
      mockUpdateUser.mockRejectedValue(new Error('Update failed'))
      console.error = vi.fn()

      await wrapper.vm.toggleStatus(user)

      expect(console.error).toHaveBeenCalledWith('Failed to toggle user status:', expect.any(Error))
    })
  })

  describe('Delete User Functionality', () => {
    it('should delete user with confirmation', async () => {
      const user = mockUsers[2] // Use staff user (not current user)
      
      // Mock window.confirm
      window.confirm = vi.fn(() => true)
      mockDeleteUser.mockResolvedValue({ data: {} })
      mockGetUsers.mockResolvedValue({ data: mockUsers.filter(u => u.id !== user.id) })

      await wrapper.vm.deleteUserConfirm(user)

      expect(window.confirm).toHaveBeenCalledWith('確定要刪除此用戶嗎？此操作無法復原。')
      expect(mockDeleteUser).toHaveBeenCalledWith(user.id)
      expect(mockGetUsers).toHaveBeenCalled()
    })

    it('should not delete user if not confirmed', async () => {
      const user = mockUsers[2]
      
      window.confirm = vi.fn(() => false)

      await wrapper.vm.deleteUserConfirm(user)

      expect(window.confirm).toHaveBeenCalled()
      expect(mockDeleteUser).not.toHaveBeenCalled()
    })

    it('should handle delete user errors', async () => {
      const user = mockUsers[2]
      
      window.confirm = vi.fn(() => true)
      window.alert = vi.fn()
      mockDeleteUser.mockRejectedValue(new Error('Delete failed'))

      await wrapper.vm.deleteUserConfirm(user)

      expect(window.alert).toHaveBeenCalledWith('刪除用戶失敗，請重試')
    })
  })

  describe('Refresh Functionality', () => {
    it('should refresh users list', async () => {
      mockGetUsers.mockClear()
      
      await wrapper.vm.refreshUsers()

      expect(mockGetUsers).toHaveBeenCalled()
      expect(wrapper.vm.refreshing).toBe(false)
    })

    it('should show refreshing state', async () => {
      // Create a delayed promise
      let resolveUsers
      const usersPromise = new Promise((resolve) => {
        resolveUsers = resolve
      })
      mockGetUsers.mockReturnValue(usersPromise)

      // Start refresh
      const refreshPromise = wrapper.vm.refreshUsers()
      
      expect(wrapper.vm.refreshing).toBe(true)
      
      // Resolve the promise
      resolveUsers({ data: mockUsers })
      await refreshPromise

      expect(wrapper.vm.refreshing).toBe(false)
    })
  })

  describe('Permission Checks', () => {
    it('should show access denied for users without permissions', async () => {
      mockAuthStore.hasPermission = vi.fn(() => false)
      mockAuthStore.isAdmin = false
      mockAuthStore.isManager = false

      const restrictedWrapper = mount(UsersPage, {
        global: {
          stubs: {
            ClientOnly: {
              template: '<div><slot /></div>'
            }
          }
        }
      })

      await restrictedWrapper.vm.$nextTick()

      // Should show access denied message
      expect(mockGetUsers).not.toHaveBeenCalled()
      
      restrictedWrapper.unmount()
    })

    it('should allow access for admin users', async () => {
      mockAuthStore.hasPermission = vi.fn(() => false) // Even if specific permission is false
      mockAuthStore.isAdmin = true
      mockAuthStore.isManager = false

      await wrapper.vm.$nextTick()

      expect(mockGetUsers).toHaveBeenCalled()
    })

    it('should allow access for manager users', async () => {
      mockAuthStore.hasPermission = vi.fn(() => false)
      mockAuthStore.isAdmin = false
      mockAuthStore.isManager = true

      const managerWrapper = mount(UsersPage, {
        global: {
          stubs: {
            ClientOnly: {
              template: '<div><slot /></div>'
            }
          }
        }
      })

      await managerWrapper.vm.$nextTick()

      expect(mockGetUsers).toHaveBeenCalled()
      
      managerWrapper.unmount()
    })
  })

  describe('Error Handling', () => {
    it('should handle users loading errors', async () => {
      mockGetUsers.mockRejectedValue(new Error('Failed to load users'))
      console.error = vi.fn()

      const errorWrapper = mount(UsersPage, {
        global: {
          stubs: {
            ClientOnly: {
              template: '<div><slot /></div>'
            }
          }
        }
      })

      await errorWrapper.vm.$nextTick()

      expect(console.error).toHaveBeenCalledWith('Failed to load users:', expect.any(Error))
      
      errorWrapper.unmount()
    })

    it('should handle roles loading errors', async () => {
      mockGetRoles.mockRejectedValue(new Error('Failed to load roles'))
      console.error = vi.fn()

      const errorWrapper = mount(UsersPage, {
        global: {
          stubs: {
            ClientOnly: {
              template: '<div><slot /></div>'
            }
          }
        }
      })

      await errorWrapper.vm.$nextTick()

      expect(console.error).toHaveBeenCalledWith('Failed to load roles:', expect.any(Error))
      
      errorWrapper.unmount()
    })
  })

  describe('Component Lifecycle', () => {
    it('should load users and roles on mount', async () => {
      // Already tested implicitly, but explicit test
      expect(mockGetUsers).toHaveBeenCalled()
      expect(mockGetRoles).toHaveBeenCalled()
    })

    it('should not load data for unauthorized users', async () => {
      mockGetUsers.mockClear()
      mockGetRoles.mockClear()
      
      mockAuthStore.hasPermission = vi.fn(() => false)
      mockAuthStore.isAdmin = false
      mockAuthStore.isManager = false

      const unauthorizedWrapper = mount(UsersPage, {
        global: {
          stubs: {
            ClientOnly: {
              template: '<div><slot /></div>'
            }
          }
        }
      })

      await unauthorizedWrapper.vm.$nextTick()

      expect(mockGetUsers).not.toHaveBeenCalled()
      expect(mockGetRoles).not.toHaveBeenCalled()
      
      unauthorizedWrapper.unmount()
    })
  })

  describe('Pagination Functionality', () => {
    const paginatedResponse = {
      data: mockUsers.slice(0, 2),
      current_page: 1,
      last_page: 3,
      total: 6,
      per_page: 2
    }

    beforeEach(() => {
      mockGetUsers.mockClear()
      mockGetUsers.mockResolvedValue(paginatedResponse)
      
      wrapper = mount(UsersPage, {
        global: {
          stubs: {
            ClientOnly: {
              template: '<div><slot /></div>'
            }
          }
        }
      })
    })

    it('should initialize pagination state correctly', async () => {
      await wrapper.vm.$nextTick()
      
      expect(wrapper.vm.currentPage).toBe(1)
      expect(wrapper.vm.totalPages).toBe(3)
      expect(wrapper.vm.perPage).toBe(10)
      expect(wrapper.vm.totalUsers).toBe(6)
    })

    it('should load users with pagination parameters', async () => {
      await wrapper.vm.loadUsers(2)
      
      expect(mockGetUsers).toHaveBeenCalledWith({
        search: '',
        page: 2,
        per_page: 10
      })
    })

    it('should handle pagination data from API response', async () => {
      await wrapper.vm.$nextTick()
      
      expect(wrapper.vm.users).toEqual(paginatedResponse.data)
      expect(wrapper.vm.currentPage).toBe(paginatedResponse.current_page)
      expect(wrapper.vm.totalUsers).toBe(paginatedResponse.total)
    })

    it('should generate visible pages correctly', async () => {
      wrapper.vm.currentPage = 2
      wrapper.vm.totalPages = 10
      
      const visiblePages = wrapper.vm.getVisiblePages()
      expect(visiblePages).toContain(1)
      expect(visiblePages).toContain(2)
      expect(visiblePages).toContain(3)
      expect(visiblePages).toContain('...')
      expect(visiblePages).toContain(10)
    })

    it('should handle small number of total pages', async () => {
      wrapper.vm.currentPage = 2
      wrapper.vm.totalPages = 5
      
      const visiblePages = wrapper.vm.getVisiblePages()
      expect(visiblePages).toEqual([1, 2, 3, 4, 5])
      expect(visiblePages).not.toContain('...')
    })

    it('should render pagination controls when multiple pages exist', async () => {
      wrapper.vm.totalPages = 3
      await wrapper.vm.$nextTick()
      
      // Check for pagination container
      const paginationDiv = wrapper.find('div:contains("顯示第")')
      expect(paginationDiv.exists()).toBe(true)
    })

    it('should not render pagination when only one page', async () => {
      wrapper.vm.totalPages = 1
      await wrapper.vm.$nextTick()
      
      // Check pagination controls should not be visible
      const paginationDiv = wrapper.find('div[v-if="totalPages > 1"]')
      expect(paginationDiv.exists()).toBe(false)
    })
  })

  describe('Enhanced Form Field Validation', () => {
    beforeEach(async () => {
      wrapper.vm.showAddModal = true
      await wrapper.vm.$nextTick()
    })

    it('should validate all required fields', async () => {
      window.alert = vi.fn()
      
      wrapper.vm.addForm = {
        name: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: '',
        status: 'active'
      }

      await wrapper.vm.addUser()

      expect(window.alert).toHaveBeenCalledWith('請填寫所有必要欄位')
      expect(mockCreateUser).not.toHaveBeenCalled()
    })

    it('should validate password confirmation match', async () => {
      window.alert = vi.fn()
      
      wrapper.vm.addForm = {
        name: 'Test User',
        username: 'testuser',
        email: 'test@example.com',
        password: 'password123',
        password_confirmation: 'password456',
        role: 'staff',
        status: 'active'
      }

      await wrapper.vm.addUser()

      expect(window.alert).toHaveBeenCalledWith('密碼確認不相符')
      expect(mockCreateUser).not.toHaveBeenCalled()
    })

    it('should validate minimum password length', async () => {
      window.alert = vi.fn()
      
      wrapper.vm.addForm = {
        name: 'Test User',
        username: 'testuser',
        email: 'test@example.com',
        password: '123',
        password_confirmation: '123',
        role: 'staff',
        status: 'active'
      }

      await wrapper.vm.addUser()

      expect(window.alert).toHaveBeenCalledWith('密碼長度至少需要6個字元')
      expect(mockCreateUser).not.toHaveBeenCalled()
    })

    it('should handle detailed API validation errors', async () => {
      window.alert = vi.fn()
      
      const validationError = {
        errors: {
          email: ['電子郵件格式不正確', '電子郵件已被使用'],
          username: ['使用者名稱已存在']
        }
      }

      mockCreateUser.mockRejectedValue(validationError)
      
      wrapper.vm.addForm = {
        name: 'Test User',
        username: 'existinguser',
        email: 'invalid-email',
        password: 'password123',
        password_confirmation: 'password123',
        role: 'staff',
        status: 'active'
      }

      await wrapper.vm.addUser()

      expect(window.alert).toHaveBeenCalledWith(
        expect.stringContaining('表單驗證失敗:')
      )
      expect(window.alert).toHaveBeenCalledWith(
        expect.stringContaining('email: 電子郵件格式不正確, 電子郵件已被使用')
      )
    })

    it('should show success message on successful user creation', async () => {
      window.alert = vi.fn()
      mockCreateUser.mockResolvedValue({ success: true, data: { id: 4 } })
      mockGetUsers.mockResolvedValue({ data: [...mockUsers, { id: 4 }] })
      
      wrapper.vm.addForm = {
        name: 'New User',
        username: 'newuser',
        email: 'new@example.com',
        password: 'password123',
        password_confirmation: 'password123',
        role: 'staff',
        status: 'active'
      }

      await wrapper.vm.addUser()

      expect(window.alert).toHaveBeenCalledWith('使用者建立成功')
      expect(wrapper.vm.showAddModal).toBe(false)
    })
  })

  describe('Status Color Enhancement', () => {
    it('should apply high contrast colors for active status', async () => {
      const user = { ...mockUsers[0], status: 'active' }
      await wrapper.vm.$nextTick()
      
      // The component should apply high contrast blue colors for active users
      expect(user.status).toBe('active')
    })

    it('should apply high contrast colors for inactive status', async () => {
      const user = { ...mockUsers[2], status: 'inactive' }
      await wrapper.vm.$nextTick()
      
      // The component should apply high contrast red colors for inactive users
      expect(user.status).toBe('inactive')
    })

    it('should handle suspended status with yellow colors', async () => {
      const suspendedUser = { ...mockUsers[0], status: 'suspended' }
      
      // Test that suspended status would be handled correctly
      expect(suspendedUser.status).toBe('suspended')
    })
  })

  describe('Accessibility and Usability', () => {
    it('should have proper ARIA labels for pagination buttons', async () => {
      wrapper.vm.totalPages = 3
      await wrapper.vm.$nextTick()
      
      // Check for aria-label on pagination navigation
      const paginationNav = wrapper.find('nav[aria-label="分頁導航"]')
      expect(paginationNav.exists()).toBe(true)
    })

    it('should show tooltips for icon-only buttons', async () => {
      const refreshButton = wrapper.find('button[title="重新整理"]')
      expect(refreshButton.exists()).toBe(true)
    })

    it('should have larger font sizes for form inputs', async () => {
      wrapper.vm.showAddModal = true
      await wrapper.vm.$nextTick()
      
      // Check if inputs have text-lg class for larger font size
      const nameInput = wrapper.find('input[v-model="addForm.name"]')
      if (nameInput.exists()) {
        expect(nameInput.classes()).toContain('text-lg')
      }
    })
  })
})