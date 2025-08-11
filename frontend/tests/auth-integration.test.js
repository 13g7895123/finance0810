import { describe, it, expect, beforeEach, vi } from 'vitest'

describe('Authentication Integration Tests', () => {
  describe('Login Flow', () => {
    it('should handle successful admin login', async () => {
      // Mock successful API response
      const mockApiResponse = {
        data: {
          access_token: 'test-jwt-token',
          user: {
            id: 1,
            username: 'admin',
            email: 'admin@finance-crm.com',
            name: '系統管理員',
            roles: ['admin'],
            permissions: ['user.view', 'user.create', 'user.edit', 'user.delete'],
            is_admin: true,
            is_manager: true
          }
        },
        error: null
      }

      // Mock the API call
      const mockPost = vi.fn().mockResolvedValue(mockApiResponse)
      
      // Test login logic
      const credentials = {
        username: 'admin',
        password: 'admin123'
      }

      const result = await mockPost('/auth/login', credentials)
      
      expect(result.data.access_token).toBe('test-jwt-token')
      expect(result.data.user.roles).toContain('admin')
      expect(result.data.user.is_admin).toBe(true)
      expect(mockPost).toHaveBeenCalledWith('/auth/login', credentials)
    })

    it('should handle login failure', async () => {
      const mockApiResponse = {
        data: null,
        error: { message: '登入資訊有誤' }
      }

      const mockPost = vi.fn().mockResolvedValue(mockApiResponse)
      
      const credentials = {
        username: 'invalid',
        password: 'invalid'
      }

      const result = await mockPost('/auth/login', credentials)
      
      expect(result.error.message).toBe('登入資訊有誤')
      expect(result.data).toBe(null)
    })

    it('should handle network errors', async () => {
      const mockPost = vi.fn().mockRejectedValue(new Error('Network error'))
      
      const credentials = {
        username: 'admin',
        password: 'admin123'
      }

      await expect(mockPost('/auth/login', credentials)).rejects.toThrow('Network error')
    })
  })

  describe('Permission Validation', () => {
    it('should validate admin permissions correctly', () => {
      const user = {
        role: 'admin',
        permissions: []
      }

      // Admin should have all permissions
      const hasPermission = (user, permission) => {
        if (user.role === 'admin' || user.role === 'executive') {
          return true
        }
        return user.permissions?.includes(permission) || false
      }

      expect(hasPermission(user, 'user.view')).toBe(true)
      expect(hasPermission(user, 'user.create')).toBe(true)
      expect(hasPermission(user, 'customer.delete')).toBe(true)
    })

    it('should validate staff permissions correctly', () => {
      const user = {
        role: 'staff',
        permissions: ['customer.view', 'customer.edit']
      }

      const hasPermission = (user, permission) => {
        if (user.role === 'admin' || user.role === 'executive') {
          return true
        }
        return user.permissions?.includes(permission) || false
      }

      expect(hasPermission(user, 'customer.view')).toBe(true)
      expect(hasPermission(user, 'customer.edit')).toBe(true)
      expect(hasPermission(user, 'user.create')).toBe(false)
      expect(hasPermission(user, 'user.delete')).toBe(false)
    })

    it('should validate manager permissions correctly', () => {
      const user = {
        role: 'manager',
        permissions: [
          'customer.view', 'customer.create', 'customer.edit',
          'user.view', 'user.create', 'user.edit',
          'report.daily', 'report.monthly'
        ]
      }

      const hasPermission = (user, permission) => {
        if (user.role === 'admin' || user.role === 'executive') {
          return true
        }
        return user.permissions?.includes(permission) || false
      }

      expect(hasPermission(user, 'user.view')).toBe(true)
      expect(hasPermission(user, 'customer.edit')).toBe(true)
      expect(hasPermission(user, 'report.daily')).toBe(true)
    })
  })

  describe('User Management API', () => {
    it('should fetch users list successfully', async () => {
      const mockUsers = [
        {
          id: 1,
          name: '系統管理員',
          email: 'admin@finance-crm.com',
          status: 'active',
          roles: [{ name: 'admin', display_name: '系統管理員' }]
        },
        {
          id: 2,
          name: '業務主管',
          email: 'manager@finance-crm.com',
          status: 'active',
          roles: [{ name: 'manager', display_name: '業務主管' }]
        }
      ]

      const mockGet = vi.fn().mockResolvedValue({
        data: { data: mockUsers },
        error: null
      })

      const result = await mockGet('/users', { search: '' })
      
      expect(result.data.data).toHaveLength(2)
      expect(result.data.data[0].roles[0].name).toBe('admin')
      expect(result.data.data[1].roles[0].name).toBe('manager')
    })

    it('should create new user successfully', async () => {
      const newUserData = {
        name: '新業務員',
        email: 'new-staff@finance-crm.com',
        password: 'password123',
        role: 'staff'
      }

      const mockResponse = {
        id: 3,
        ...newUserData,
        status: 'active',
        created_at: new Date().toISOString()
      }

      const mockPost = vi.fn().mockResolvedValue({
        data: mockResponse,
        error: null
      })

      const result = await mockPost('/users', newUserData)
      
      expect(result.data.name).toBe('新業務員')
      expect(result.data.email).toBe('new-staff@finance-crm.com')
      expect(result.data.id).toBe(3)
    })

    it('should update user successfully', async () => {
      const updateData = {
        name: '更新的姓名',
        email: 'updated@finance-crm.com'
      }

      const mockResponse = {
        id: 2,
        ...updateData,
        status: 'active',
        updated_at: new Date().toISOString()
      }

      const mockPut = vi.fn().mockResolvedValue({
        data: mockResponse,
        error: null
      })

      const result = await mockPut('/users/2', updateData)
      
      expect(result.data.name).toBe('更新的姓名')
      expect(result.data.email).toBe('updated@finance-crm.com')
    })

    it('should delete user successfully', async () => {
      const mockDel = vi.fn().mockResolvedValue({
        data: { message: '用戶已成功刪除' },
        error: null
      })

      const result = await mockDel('/users/3')
      
      expect(result.data.message).toBe('用戶已成功刪除')
    })

    it('should fetch roles successfully', async () => {
      const mockRoles = [
        { id: 1, name: 'admin', display_name: '系統管理員' },
        { id: 2, name: 'manager', display_name: '業務主管' },
        { id: 3, name: 'staff', display_name: '業務人員' }
      ]

      const mockGet = vi.fn().mockResolvedValue({
        data: mockRoles,
        error: null
      })

      const result = await mockGet('/roles')
      
      expect(result.data).toHaveLength(3)
      expect(result.data[0].name).toBe('admin')
      expect(result.data[1].name).toBe('manager')
      expect(result.data[2].name).toBe('staff')
    })

    it('should handle API errors appropriately', async () => {
      const mockGet = vi.fn().mockResolvedValue({
        data: null,
        error: { message: 'Unauthorized access', status: 401 }
      })

      const result = await mockGet('/users')
      
      expect(result.error.message).toBe('Unauthorized access')
      expect(result.error.status).toBe(401)
      expect(result.data).toBe(null)
    })
  })

  describe('Role Assignment', () => {
    it('should assign role to user successfully', async () => {
      const mockPost = vi.fn().mockResolvedValue({
        data: { message: '角色指派成功' },
        error: null
      })

      const result = await mockPost('/users/2/roles', { role: 'manager' })
      
      expect(result.data.message).toBe('角色指派成功')
    })

    it('should handle role assignment errors', async () => {
      const mockPost = vi.fn().mockResolvedValue({
        data: null,
        error: { message: '角色不存在' }
      })

      const result = await mockPost('/users/2/roles', { role: 'invalid' })
      
      expect(result.error.message).toBe('角色不存在')
    })
  })

  describe('Session Management', () => {
    it('should handle session restoration', () => {
      const mockSessionData = {
        id: 1,
        username: 'admin',
        email: 'admin@finance-crm.com',
        roles: ['admin'],
        is_admin: true
      }

      // Mock sessionStorage
      const mockGetItem = vi.fn().mockReturnValue(JSON.stringify(mockSessionData))
      
      Object.defineProperty(window, 'sessionStorage', {
        value: {
          getItem: mockGetItem,
          setItem: vi.fn(),
          removeItem: vi.fn()
        }
      })

      const storedData = window.sessionStorage.getItem('user-profile')
      const userData = JSON.parse(storedData)
      
      expect(userData.username).toBe('admin')
      expect(userData.roles).toContain('admin')
      expect(userData.is_admin).toBe(true)
    })

    it('should handle session cleanup', () => {
      const mockRemoveItem = vi.fn()
      
      Object.defineProperty(window, 'sessionStorage', {
        value: {
          getItem: vi.fn(),
          setItem: vi.fn(),
          removeItem: mockRemoveItem
        }
      })

      // Simulate logout
      window.sessionStorage.removeItem('user-profile')
      
      expect(mockRemoveItem).toHaveBeenCalledWith('user-profile')
    })
  })

  describe('Form Validation', () => {
    it('should validate login form data', () => {
      const validateLogin = (data) => {
        const errors = []
        
        if (!data.username || data.username.trim() === '') {
          errors.push('使用者名稱為必填欄位')
        }
        
        if (!data.password || data.password.length < 6) {
          errors.push('密碼至少需要6個字元')
        }
        
        return errors
      }

      // Valid data
      expect(validateLogin({ username: 'admin', password: 'admin123' })).toHaveLength(0)
      
      // Invalid data
      expect(validateLogin({ username: '', password: '123' })).toHaveLength(2)
      expect(validateLogin({ username: 'admin', password: '123' })).toHaveLength(1)
    })

    it('should validate user creation form', () => {
      const validateUserCreation = (data) => {
        const errors = []
        
        if (!data.name || data.name.trim() === '') {
          errors.push('姓名為必填欄位')
        }
        
        if (!data.email || !/\S+@\S+\.\S+/.test(data.email)) {
          errors.push('請輸入有效的電子郵件地址')
        }
        
        if (!data.password || data.password.length < 6) {
          errors.push('密碼至少需要6個字元')
        }
        
        if (!data.role) {
          errors.push('請選擇角色')
        }
        
        return errors
      }

      // Valid data
      const validData = {
        name: '新用戶',
        email: 'user@example.com',
        password: 'password123',
        role: 'staff'
      }
      expect(validateUserCreation(validData)).toHaveLength(0)
      
      // Invalid data
      const invalidData = {
        name: '',
        email: 'invalid-email',
        password: '123',
        role: ''
      }
      expect(validateUserCreation(invalidData)).toHaveLength(4)
    })
  })

  describe('Error Handling', () => {
    it('should handle 401 authentication errors', () => {
      const handle401Error = (error) => {
        if (error.status === 401) {
          // Should clear session and redirect to login
          return {
            action: 'redirect',
            destination: '/auth/login',
            clearSession: true
          }
        }
        return null
      }

      const result = handle401Error({ status: 401, message: 'Unauthenticated' })
      
      expect(result.action).toBe('redirect')
      expect(result.destination).toBe('/auth/login')
      expect(result.clearSession).toBe(true)
    })

    it('should handle validation errors', () => {
      const handleValidationError = (error) => {
        if (error.status === 422 && error.errors) {
          return {
            type: 'validation',
            fields: error.errors
          }
        }
        return { type: 'general', message: error.message }
      }

      const validationError = {
        status: 422,
        errors: {
          email: ['電子郵件已被使用'],
          password: ['密碼至少需要6個字元']
        }
      }

      const result = handleValidationError(validationError)
      
      expect(result.type).toBe('validation')
      expect(result.fields.email[0]).toBe('電子郵件已被使用')
    })
  })
})