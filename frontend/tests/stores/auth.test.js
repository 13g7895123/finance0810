import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '../../stores/auth.js'

// Mock useApi composable
const mockPost = vi.fn()
const mockGet = vi.fn()
vi.mock('../../composables/useApi.js', () => ({
  useApi: () => ({
    post: mockPost,
    get: mockGet
  })
}))

describe('Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    
    // Reset sessionStorage mock
    window.sessionStorage.getItem.mockReturnValue(null)
    window.sessionStorage.setItem.mockImplementation(() => {})
    window.sessionStorage.removeItem.mockImplementation(() => {})
  })

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const store = useAuthStore()
      
      expect(store.user).toBe(null)
      expect(store.isLoggedIn).toBe(false)
      expect(store.isAdmin).toBe(false)
      expect(store.isManager).toBe(false)
      expect(store.isStaff).toBe(false)
    })
  })

  describe('Login Functionality', () => {
    it('should login successfully with valid credentials', async () => {
      const store = useAuthStore()
      const mockUserData = {
        id: 1,
        name: '測試管理員',
        username: 'admin',
        email: 'admin@finance-crm.com',
        roles: ['admin'],
        permissions: ['user.view', 'user.create'],
        is_admin: true,
        is_manager: true
      }

      const mockResponse = {
        access_token: 'test-token-123',
        user: mockUserData
      }

      mockPost.mockResolvedValue({
        data: mockResponse,
        error: null
      })

      const credentials = {
        username: 'admin',
        password: 'admin123'
      }

      const result = await store.login(credentials)

      expect(mockPost).toHaveBeenCalledWith('/auth/login', {
        username: 'admin',
        password: 'admin123'
      })
      
      expect(result.success).toBe(true)
      expect(store.user).not.toBe(null)
      expect(store.user.role).toBe('admin')
      expect(store.user.token).toBe('test-token-123')
      expect(store.isLoggedIn).toBe(true)
      expect(window.sessionStorage.setItem).toHaveBeenCalled()
    })

    it('should handle login failure with invalid credentials', async () => {
      const store = useAuthStore()

      mockPost.mockResolvedValue({
        data: null,
        error: { message: '登入資訊有誤' }
      })

      const credentials = {
        username: 'invalid',
        password: 'invalid'
      }

      await expect(store.login(credentials)).rejects.toThrow('登入資訊有誤')
      
      expect(store.user).toBe(null)
      expect(store.isLoggedIn).toBe(false)
    })

    it('should handle network errors during login', async () => {
      const store = useAuthStore()

      mockPost.mockRejectedValue(new Error('Network error'))

      const credentials = {
        username: 'admin',
        password: 'admin123'
      }

      await expect(store.login(credentials)).rejects.toThrow('登入失敗，請檢查您的帳號密碼')
    })
  })

  describe('Permission Checking', () => {
    it('should grant all permissions to admin users', () => {
      const store = useAuthStore()
      
      store.user = {
        id: 1,
        role: 'admin',
        permissions: []
      }

      expect(store.hasPermission('user.view')).toBe(true)
      expect(store.hasPermission('user.create')).toBe(true)
      expect(store.hasPermission('any.permission')).toBe(true)
    })

    it('should grant all permissions to executive users', () => {
      const store = useAuthStore()
      
      store.user = {
        id: 1,
        role: 'executive',
        permissions: []
      }

      expect(store.hasPermission('user.view')).toBe(true)
      expect(store.hasPermission('customer.edit')).toBe(true)
    })

    it('should check specific permissions for non-admin users', () => {
      const store = useAuthStore()
      
      store.user = {
        id: 1,
        role: 'staff',
        permissions: ['customer.view', 'customer.edit']
      }

      expect(store.hasPermission('customer.view')).toBe(true)
      expect(store.hasPermission('customer.edit')).toBe(true)
      expect(store.hasPermission('user.create')).toBe(false)
    })

    it('should deny permissions for non-authenticated users', () => {
      const store = useAuthStore()
      
      expect(store.hasPermission('customer.view')).toBe(false)
    })

    it('should grant all permissions for users with all_access permission', () => {
      const store = useAuthStore()
      
      store.user = {
        id: 1,
        role: 'staff',
        permissions: ['all_access']
      }

      expect(store.hasPermission('user.create')).toBe(true)
      expect(store.hasPermission('system.settings')).toBe(true)
    })
  })

  describe('Role Checking', () => {
    it('should correctly identify admin users', () => {
      const store = useAuthStore()
      
      store.user = {
        id: 1,
        role: 'admin'
      }

      expect(store.isAdmin).toBe(true)
      expect(store.isManager).toBe(false)
      expect(store.isStaff).toBe(false)
    })

    it('should correctly identify manager users', () => {
      const store = useAuthStore()
      
      store.user = {
        id: 1,
        role: 'manager'
      }

      expect(store.isAdmin).toBe(false)
      expect(store.isManager).toBe(true)
      expect(store.isStaff).toBe(false)
    })

    it('should correctly identify staff users', () => {
      const store = useAuthStore()
      
      store.user = {
        id: 1,
        role: 'staff'
      }

      expect(store.isAdmin).toBe(false)
      expect(store.isManager).toBe(false)
      expect(store.isStaff).toBe(true)
    })
  })

  describe('Logout Functionality', () => {
    it('should logout user and clear session data', async () => {
      const store = useAuthStore()
      
      // Set up logged in user
      store.user = {
        id: 1,
        username: 'admin',
        role: 'admin'
      }

      mockPost.mockResolvedValue({ data: {}, error: null })

      await store.logout()

      expect(mockPost).toHaveBeenCalledWith('/auth/logout')
      expect(store.user).toBe(null)
      expect(store.isLoggedIn).toBe(false)
      expect(window.sessionStorage.removeItem).toHaveBeenCalledWith('user-profile')
      expect(navigateTo).toHaveBeenCalledWith('/auth/login')
    })

    it('should handle logout API errors gracefully', async () => {
      const store = useAuthStore()
      
      store.user = {
        id: 1,
        username: 'admin',
        role: 'admin'
      }

      mockPost.mockRejectedValue(new Error('Network error'))

      await store.logout()

      // Should still clear local data even if API call fails
      expect(store.user).toBe(null)
      expect(window.sessionStorage.removeItem).toHaveBeenCalledWith('user-profile')
    })
  })

  describe('Authentication Initialization', () => {
    it('should restore user session from sessionStorage', async () => {
      const store = useAuthStore()
      
      const mockStoredProfile = JSON.stringify({
        id: 1,
        username: 'admin',
        email: 'admin@finance-crm.com'
      })
      
      window.sessionStorage.getItem.mockReturnValue(mockStoredProfile)
      
      const mockUserData = {
        user: {
          id: 1,
          username: 'admin',
          roles: ['admin'],
          permissions: ['user.view']
        }
      }
      
      mockGet.mockResolvedValue({
        data: mockUserData,
        error: null
      })

      await store.initializeAuth()

      expect(mockGet).toHaveBeenCalledWith('/auth/me')
      expect(store.user).not.toBe(null)
      expect(store.user.role).toBe('admin')
      expect(store.isLoggedIn).toBe(true)
    })

    it('should clear invalid session data', async () => {
      const store = useAuthStore()
      
      const mockStoredProfile = JSON.stringify({
        id: 1,
        username: 'admin'
      })
      
      window.sessionStorage.getItem.mockReturnValue(mockStoredProfile)
      
      mockGet.mockResolvedValue({
        data: null,
        error: { message: 'Unauthenticated' }
      })

      await store.initializeAuth()

      expect(window.sessionStorage.removeItem).toHaveBeenCalledWith('user-profile')
      expect(store.user).toBe(null)
      expect(store.isLoggedIn).toBe(false)
    })

    it('should handle corrupted sessionStorage data', async () => {
      const store = useAuthStore()
      
      window.sessionStorage.getItem.mockReturnValue('invalid-json')

      await store.initializeAuth()

      expect(window.sessionStorage.removeItem).toHaveBeenCalledWith('user-profile')
      expect(store.user).toBe(null)
    })
  })

  describe('Role Array Handling', () => {
    it('should handle roles as array', async () => {
      const store = useAuthStore()
      
      const mockResponse = {
        access_token: 'test-token',
        user: {
          id: 1,
          username: 'admin',
          roles: ['admin', 'manager']
        }
      }

      mockPost.mockResolvedValue({
        data: mockResponse,
        error: null
      })

      await store.login({ username: 'admin', password: 'admin123' })

      expect(store.user.role).toBe('admin')
    })

    it('should handle roles as collection object', async () => {
      const store = useAuthStore()
      
      const mockResponse = {
        access_token: 'test-token',
        user: {
          id: 1,
          username: 'manager',
          roles: { 0: 'manager', 1: 'staff' } // Simulating Laravel collection
        }
      }

      mockPost.mockResolvedValue({
        data: mockResponse,
        error: null
      })

      await store.login({ username: 'manager', password: 'password123' })

      expect(store.user.role).toBe('manager')
    })
  })

  describe('Register Functionality', () => {
    it('should register new user successfully', async () => {
      const store = useAuthStore()
      
      const userData = {
        name: '新用戶',
        username: 'newuser',
        email: 'newuser@example.com',
        password: 'password123',
        password_confirmation: 'password123'
      }

      mockPost.mockResolvedValue({
        data: { message: '註冊成功' },
        error: null
      })

      const result = await store.register(userData)

      expect(mockPost).toHaveBeenCalledWith('/auth/register', userData)
      expect(result.success).toBe(true)
      expect(result.message).toBe('註冊成功')
    })

    it('should handle registration errors', async () => {
      const store = useAuthStore()
      
      const userData = {
        name: '新用戶',
        username: 'existing',
        email: 'existing@example.com',
        password: 'password123'
      }

      mockPost.mockResolvedValue({
        data: null,
        error: { message: '使用者名稱已被使用' }
      })

      await expect(store.register(userData)).rejects.toThrow('使用者名稱已被使用')
    })
  })
})