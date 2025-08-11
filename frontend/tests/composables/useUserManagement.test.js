import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useUserManagement } from '../../composables/useUserManagement.js'

// Mock auth store
const mockAuthStore = {
  user: { id: 1, role: 'admin' },
  isLoggedIn: true
}

vi.mock('../../stores/auth.js', () => ({
  useAuthStore: () => mockAuthStore
}))

// Mock API composable
const mockGet = vi.fn()
const mockPost = vi.fn()
const mockPut = vi.fn()
const mockDel = vi.fn()

vi.mock('../../composables/useApi.js', () => ({
  useApi: () => ({
    get: mockGet,
    post: mockPost,
    put: mockPut,
    del: mockDel
  })
}))

describe('useUserManagement Composable', () => {
  let userManagement

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    userManagement = useUserManagement()
  })

  describe('Authentication Check', () => {
    it('should throw error when user is not authenticated', async () => {
      mockAuthStore.user = null
      mockAuthStore.isLoggedIn = false

      const userManagement = useUserManagement()

      await expect(userManagement.getUsers()).rejects.toThrow('Authentication required. Please login first.')
    })

    it('should allow operations when user is authenticated', async () => {
      mockAuthStore.user = { id: 1, role: 'admin' }
      mockAuthStore.isLoggedIn = true

      mockGet.mockResolvedValue({ data: [], error: null })

      await expect(userManagement.getUsers()).resolves.toEqual([])
    })
  })

  describe('User Management Operations', () => {
    beforeEach(() => {
      mockAuthStore.user = { id: 1, role: 'admin' }
      mockAuthStore.isLoggedIn = true
    })

    describe('getUsers', () => {
      it('should fetch users successfully', async () => {
        const mockUsers = [
          { id: 1, name: 'Admin', email: 'admin@example.com' },
          { id: 2, name: 'User', email: 'user@example.com' }
        ]

        mockGet.mockResolvedValue({ data: mockUsers, error: null })

        const result = await userManagement.getUsers()

        expect(mockGet).toHaveBeenCalledWith('/users', {})
        expect(result).toEqual(mockUsers)
      })

      it('should fetch users with search parameters', async () => {
        const searchParams = { search: 'admin', status: 'active' }
        const mockUsers = [{ id: 1, name: 'Admin', email: 'admin@example.com' }]

        mockGet.mockResolvedValue({ data: mockUsers, error: null })

        const result = await userManagement.getUsers(searchParams)

        expect(mockGet).toHaveBeenCalledWith('/users', searchParams)
        expect(result).toEqual(mockUsers)
      })

      it('should handle API errors', async () => {
        const mockError = new Error('API Error')
        mockGet.mockResolvedValue({ data: null, error: mockError })

        await expect(userManagement.getUsers()).rejects.toThrow('API Error')
      })
    })

    describe('createUser', () => {
      it('should create user successfully', async () => {
        const userData = {
          name: '新用戶',
          email: 'newuser@example.com',
          password: 'password123',
          role: 'staff'
        }

        const mockResponse = { id: 3, ...userData }
        mockPost.mockResolvedValue({ data: mockResponse, error: null })

        const result = await userManagement.createUser(userData)

        expect(mockPost).toHaveBeenCalledWith('/users', userData)
        expect(result).toEqual(mockResponse)
      })

      it('should handle creation errors', async () => {
        const userData = {
          name: '新用戶',
          email: 'existing@example.com',
          password: 'password123',
          role: 'staff'
        }

        const mockError = new Error('Email already exists')
        mockPost.mockResolvedValue({ data: null, error: mockError })

        await expect(userManagement.createUser(userData)).rejects.toThrow('Email already exists')
      })
    })

    describe('updateUser', () => {
      it('should update user successfully', async () => {
        const userId = 2
        const updateData = {
          name: '更新的名稱',
          email: 'updated@example.com'
        }

        const mockResponse = { id: userId, ...updateData }
        mockPut.mockResolvedValue({ data: mockResponse, error: null })

        const result = await userManagement.updateUser(userId, updateData)

        expect(mockPut).toHaveBeenCalledWith('/users/2', updateData)
        expect(result).toEqual(mockResponse)
      })

      it('should handle update errors', async () => {
        const userId = 2
        const updateData = { name: '更新的名稱' }

        const mockError = new Error('User not found')
        mockPut.mockResolvedValue({ data: null, error: mockError })

        await expect(userManagement.updateUser(userId, updateData)).rejects.toThrow('User not found')
      })
    })

    describe('deleteUser', () => {
      it('should delete user successfully', async () => {
        const userId = 3
        const mockResponse = { message: 'User deleted successfully' }

        mockDel.mockResolvedValue({ data: mockResponse, error: null })

        const result = await userManagement.deleteUser(userId)

        expect(mockDel).toHaveBeenCalledWith('/users/3')
        expect(result).toEqual(mockResponse)
      })

      it('should handle deletion errors', async () => {
        const userId = 999

        const mockError = new Error('User not found')
        mockDel.mockResolvedValue({ data: null, error: mockError })

        await expect(userManagement.deleteUser(userId)).rejects.toThrow('User not found')
      })
    })

    describe('assignRole', () => {
      it('should assign role successfully', async () => {
        const userId = 2
        const roleName = 'manager'
        const mockResponse = { message: 'Role assigned successfully' }

        mockPost.mockResolvedValue({ data: mockResponse, error: null })

        const result = await userManagement.assignRole(userId, roleName)

        expect(mockPost).toHaveBeenCalledWith('/users/2/roles', { role: roleName })
        expect(result).toEqual(mockResponse)
      })

      it('should handle role assignment errors', async () => {
        const userId = 2
        const roleName = 'nonexistent'

        const mockError = new Error('Role not found')
        mockPost.mockResolvedValue({ data: null, error: mockError })

        await expect(userManagement.assignRole(userId, roleName)).rejects.toThrow('Role not found')
      })
    })

    describe('getRoles', () => {
      it('should fetch roles successfully', async () => {
        const mockRoles = [
          { id: 1, name: 'admin', display_name: '系統管理員' },
          { id: 2, name: 'manager', display_name: '業務主管' },
          { id: 3, name: 'staff', display_name: '業務人員' }
        ]

        mockGet.mockResolvedValue({ data: mockRoles, error: null })

        const result = await userManagement.getRoles()

        expect(mockGet).toHaveBeenCalledWith('/roles')
        expect(result).toEqual(mockRoles)
      })

      it('should handle roles fetching errors', async () => {
        const mockError = new Error('Failed to fetch roles')
        mockGet.mockResolvedValue({ data: null, error: mockError })

        await expect(userManagement.getRoles()).rejects.toThrow('Failed to fetch roles')
      })
    })

    describe('getUserStats', () => {
      it('should fetch user statistics successfully', async () => {
        const mockStats = {
          total_users: 10,
          active_users: 8,
          inactive_users: 2,
          roles: {
            admin: 1,
            manager: 3,
            staff: 6
          }
        }

        mockGet.mockResolvedValue({ data: mockStats, error: null })

        const result = await userManagement.getUserStats()

        expect(mockGet).toHaveBeenCalledWith('/users/stats/overview')
        expect(result).toEqual(mockStats)
      })

      it('should handle stats fetching errors', async () => {
        const mockError = new Error('Failed to fetch stats')
        mockGet.mockResolvedValue({ data: null, error: mockError })

        await expect(userManagement.getUserStats()).rejects.toThrow('Failed to fetch stats')
      })
    })

    describe('getUser', () => {
      it('should fetch specific user successfully', async () => {
        const userId = 2
        const mockUser = {
          id: userId,
          name: 'Test User',
          email: 'test@example.com',
          roles: ['staff'],
          permissions: ['customer.view']
        }

        mockGet.mockResolvedValue({ data: mockUser, error: null })

        const result = await userManagement.getUser(userId)

        expect(mockGet).toHaveBeenCalledWith('/users/2')
        expect(result).toEqual(mockUser)
      })

      it('should handle user fetching errors', async () => {
        const userId = 999

        const mockError = new Error('User not found')
        mockGet.mockResolvedValue({ data: null, error: mockError })

        await expect(userManagement.getUser(userId)).rejects.toThrow('User not found')
      })
    })
  })

  describe('Error Handling', () => {
    beforeEach(() => {
      mockAuthStore.user = { id: 1, role: 'admin' }
      mockAuthStore.isLoggedIn = true
    })

    it('should handle network errors', async () => {
      mockGet.mockRejectedValue(new Error('Network error'))

      await expect(userManagement.getUsers()).rejects.toThrow('Network error')
    })

    it('should handle timeout errors', async () => {
      mockGet.mockRejectedValue(new Error('Request timeout'))

      await expect(userManagement.getUsers()).rejects.toThrow('Request timeout')
    })

    it('should handle server errors', async () => {
      const serverError = new Error('Internal server error')
      mockGet.mockResolvedValue({ data: null, error: serverError })

      await expect(userManagement.getUsers()).rejects.toThrow('Internal server error')
    })
  })

  describe('Edge Cases', () => {
    beforeEach(() => {
      mockAuthStore.user = { id: 1, role: 'admin' }
      mockAuthStore.isLoggedIn = true
    })

    it('should handle empty user lists', async () => {
      mockGet.mockResolvedValue({ data: [], error: null })

      const result = await userManagement.getUsers()

      expect(result).toEqual([])
    })

    it('should handle null responses', async () => {
      mockGet.mockResolvedValue({ data: null, error: null })

      const result = await userManagement.getUsers()

      expect(result).toBe(null)
    })

    it('should handle undefined parameters', async () => {
      mockGet.mockResolvedValue({ data: [], error: null })

      const result = await userManagement.getUsers(undefined)

      expect(mockGet).toHaveBeenCalledWith('/users', {})
      expect(result).toEqual([])
    })
  })

  describe('Parameter Validation', () => {
    beforeEach(() => {
      mockAuthStore.user = { id: 1, role: 'admin' }
      mockAuthStore.isLoggedIn = true
    })

    it('should pass correct parameters to API calls', async () => {
      mockPost.mockResolvedValue({ data: {}, error: null })
      mockPut.mockResolvedValue({ data: {}, error: null })
      mockDel.mockResolvedValue({ data: {}, error: null })

      const userData = { name: 'Test', email: 'test@example.com' }
      const updateData = { name: 'Updated' }

      await userManagement.createUser(userData)
      await userManagement.updateUser(1, updateData)
      await userManagement.deleteUser(1)
      await userManagement.assignRole(1, 'admin')

      expect(mockPost).toHaveBeenNthCalledWith(1, '/users', userData)
      expect(mockPut).toHaveBeenCalledWith('/users/1', updateData)
      expect(mockDel).toHaveBeenCalledWith('/users/1')
      expect(mockPost).toHaveBeenNthCalledWith(2, '/users/1/roles', { role: 'admin' })
    })

    it('should handle numeric and string user IDs', async () => {
      mockGet.mockResolvedValue({ data: {}, error: null })

      await userManagement.getUser(1)
      await userManagement.getUser('2')

      expect(mockGet).toHaveBeenNthCalledWith(1, '/users/1')
      expect(mockGet).toHaveBeenNthCalledWith(2, '/users/2')
    })
  })
})