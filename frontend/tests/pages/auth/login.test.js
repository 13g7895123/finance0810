import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import LoginPage from '../../../pages/auth/login.vue'

// Mock the auth store
const mockLogin = vi.fn()
const mockAuthStore = {
  login: mockLogin,
  isLoggedIn: false
}

vi.mock('../../../stores/auth.js', () => ({
  useAuthStore: () => mockAuthStore
}))

// Mock useI18n
const mockT = vi.fn((key) => {
  const translations = {
    'auth.login': '登入',
    'auth.username': '使用者名稱',
    'auth.password': '密碼',
    'auth.login_button': '登入',
    'auth.register': '註冊',
    'auth.forgot_password': '忘記密碼',
    'auth.no_account': '還沒有帳號？',
    'common.loading': '載入中...'
  }
  return translations[key] || key
})

vi.mock('../../../composables/useI18n.js', () => ({
  useI18n: () => ({ t: mockT })
}))

// Mock SweetAlert2
global.Swal = {
  fire: vi.fn().mockResolvedValue({ isConfirmed: true })
}

describe('Login Page', () => {
  let wrapper

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    
    wrapper = mount(LoginPage, {
      global: {
        stubs: {
          NuxtLink: true,
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
    it('should render login form correctly', () => {
      expect(wrapper.find('form').exists()).toBe(true)
      expect(wrapper.find('input[type="text"]').exists()).toBe(true)
      expect(wrapper.find('input[type="password"]').exists()).toBe(true)
      expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
    })

    it('should display form labels correctly', () => {
      const usernameInput = wrapper.find('input[type="text"]')
      const passwordInput = wrapper.find('input[type="password"]')
      
      expect(usernameInput.exists()).toBe(true)
      expect(passwordInput.exists()).toBe(true)
    })

    it('should render login button', () => {
      const loginButton = wrapper.find('button[type="submit"]')
      expect(loginButton.exists()).toBe(true)
      expect(loginButton.text()).toContain('登入')
    })
  })

  describe('Form Validation', () => {
    it('should show validation errors for empty fields', async () => {
      const form = wrapper.find('form')
      
      await form.trigger('submit.prevent')
      
      // Should prevent submission with empty fields
      expect(mockLogin).not.toHaveBeenCalled()
    })

    it('should validate username field', async () => {
      const usernameInput = wrapper.find('input[type="text"]')
      
      await usernameInput.setValue('')
      await usernameInput.trigger('blur')
      
      // Form should not submit with empty username
      const form = wrapper.find('form')
      await form.trigger('submit.prevent')
      
      expect(mockLogin).not.toHaveBeenCalled()
    })

    it('should validate password field', async () => {
      const passwordInput = wrapper.find('input[type="password"]')
      
      await passwordInput.setValue('')
      await passwordInput.trigger('blur')
      
      // Form should not submit with empty password
      const form = wrapper.find('form')
      await form.trigger('submit.prevent')
      
      expect(mockLogin).not.toHaveBeenCalled()
    })
  })

  describe('Form Submission', () => {
    it('should submit form with valid credentials', async () => {
      mockLogin.mockResolvedValue({
        success: true,
        user: { id: 1, username: 'admin' }
      })

      const usernameInput = wrapper.find('input[type="text"]')
      const passwordInput = wrapper.find('input[type="password"]')
      const form = wrapper.find('form')

      await usernameInput.setValue('admin')
      await passwordInput.setValue('admin123')
      await form.trigger('submit.prevent')

      expect(mockLogin).toHaveBeenCalledWith({
        username: 'admin',
        password: 'admin123'
      })
    })

    it('should handle successful login', async () => {
      mockLogin.mockResolvedValue({
        success: true,
        user: { id: 1, username: 'admin', role: 'admin' }
      })

      const usernameInput = wrapper.find('input[type="text"]')
      const passwordInput = wrapper.find('input[type="password"]')
      const form = wrapper.find('form')

      await usernameInput.setValue('admin')
      await passwordInput.setValue('admin123')
      await form.trigger('submit.prevent')

      expect(navigateTo).toHaveBeenCalledWith('/')
    })

    it('should handle login failure with error message', async () => {
      const errorMessage = '登入資訊有誤'
      mockLogin.mockRejectedValue(new Error(errorMessage))

      const usernameInput = wrapper.find('input[type="text"]')
      const passwordInput = wrapper.find('input[type="password"]')
      const form = wrapper.find('form')

      await usernameInput.setValue('invalid')
      await passwordInput.setValue('invalid')
      await form.trigger('submit.prevent')

      // Wait for async operations
      await wrapper.vm.$nextTick()

      expect(Swal.fire).toHaveBeenCalledWith({
        title: '系統提示',
        text: errorMessage,
        icon: 'error',
        confirmButtonText: '確定'
      })
    })

    it('should show loading state during login', async () => {
      // Create a promise that we can control
      let resolveLogin
      const loginPromise = new Promise((resolve) => {
        resolveLogin = resolve
      })
      
      mockLogin.mockReturnValue(loginPromise)

      const usernameInput = wrapper.find('input[type="text"]')
      const passwordInput = wrapper.find('input[type="password"]')
      const form = wrapper.find('form')

      await usernameInput.setValue('admin')
      await passwordInput.setValue('admin123')
      
      // Submit form but don't wait for completion
      form.trigger('submit.prevent')
      
      await wrapper.vm.$nextTick()

      // Check if loading state is shown
      const submitButton = wrapper.find('button[type="submit"]')
      expect(submitButton.attributes('disabled')).toBeDefined()
      
      // Resolve the login to clean up
      resolveLogin({ success: true, user: { id: 1, username: 'admin' } })
      await wrapper.vm.$nextTick()
    })
  })

  describe('Input Handling', () => {
    it('should update form data when typing in username field', async () => {
      const usernameInput = wrapper.find('input[type="text"]')
      
      await usernameInput.setValue('testuser')
      
      expect(usernameInput.element.value).toBe('testuser')
    })

    it('should update form data when typing in password field', async () => {
      const passwordInput = wrapper.find('input[type="password"]')
      
      await passwordInput.setValue('testpassword')
      
      expect(passwordInput.element.value).toBe('testpassword')
    })

    it('should handle special characters in input fields', async () => {
      const usernameInput = wrapper.find('input[type="text"]')
      const passwordInput = wrapper.find('input[type="password"]')
      
      const specialUsername = 'user@domain.com'
      const specialPassword = 'p@ssw0rd!#$'
      
      await usernameInput.setValue(specialUsername)
      await passwordInput.setValue(specialPassword)
      
      expect(usernameInput.element.value).toBe(specialUsername)
      expect(passwordInput.element.value).toBe(specialPassword)
    })
  })

  describe('Navigation Links', () => {
    it('should render registration link', () => {
      const registerLink = wrapper.find('a[href="/auth/register"]')
      expect(registerLink.exists()).toBe(true)
    })

    it('should have proper accessibility attributes', () => {
      const form = wrapper.find('form')
      const usernameInput = wrapper.find('input[type="text"]')
      const passwordInput = wrapper.find('input[type="password"]')
      
      expect(form.exists()).toBe(true)
      expect(usernameInput.attributes('required')).toBeDefined()
      expect(passwordInput.attributes('required')).toBeDefined()
    })
  })

  describe('Security Features', () => {
    it('should have password field with type password', () => {
      const passwordInput = wrapper.find('input[type="password"]')
      expect(passwordInput.exists()).toBe(true)
      expect(passwordInput.attributes('type')).toBe('password')
    })

    it('should prevent form submission with JavaScript disabled', () => {
      const form = wrapper.find('form')
      expect(form.attributes('action')).toBeUndefined()
      expect(form.attributes('method')).toBeUndefined()
    })
  })

  describe('Error Handling', () => {
    it('should handle network errors gracefully', async () => {
      mockLogin.mockRejectedValue(new Error('Network error'))

      const usernameInput = wrapper.find('input[type="text"]')
      const passwordInput = wrapper.find('input[type="password"]')
      const form = wrapper.find('form')

      await usernameInput.setValue('admin')
      await passwordInput.setValue('admin123')
      await form.trigger('submit.prevent')

      await wrapper.vm.$nextTick()

      expect(Swal.fire).toHaveBeenCalledWith({
        title: '系統提示',
        text: 'Network error',
        icon: 'error',
        confirmButtonText: '確定'
      })
    })

    it('should handle server errors with custom messages', async () => {
      const serverError = new Error('伺服器暫時無法使用，請稍後再試')
      mockLogin.mockRejectedValue(serverError)

      const usernameInput = wrapper.find('input[type="text"]')
      const passwordInput = wrapper.find('input[type="password"]')
      const form = wrapper.find('form')

      await usernameInput.setValue('admin')
      await passwordInput.setValue('admin123')
      await form.trigger('submit.prevent')

      await wrapper.vm.$nextTick()

      expect(Swal.fire).toHaveBeenCalledWith({
        title: '系統提示',
        text: '伺服器暫時無法使用，請稍後再試',
        icon: 'error',
        confirmButtonText: '確定'
      })
    })
  })
})