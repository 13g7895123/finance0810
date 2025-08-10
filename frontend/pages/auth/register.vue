<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-indigo-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white rounded-2xl shadow-xl border border-gray-100 p-8">
      <!-- Logo & Title -->
      <div class="text-center">
        <div class="mx-auto w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
          </svg>
        </div>
        <h2 class="text-3xl font-bold text-gray-900">
          {{ safeT('auth.register_title') }}
        </h2>
        <p class="mt-2 text-sm text-gray-600">
          {{ safeT('auth.register_subtitle') }}
        </p>
      </div>

      <!-- Register Form -->
      <form class="mt-8 space-y-6" @submit.prevent="handleRegister">
        <div class="space-y-4">
          <!-- Name -->
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
              {{ safeT('auth.full_name') }}
            </label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-gray-50 text-gray-900"
              :placeholder="safeT('auth.full_name_placeholder')"
            />
          </div>

          <!-- Username -->
          <div>
            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
              {{ safeT('auth.username') }}
            </label>
            <input
              id="username"
              v-model="form.username"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-gray-50 text-gray-900"
              :placeholder="safeT('auth.username_placeholder')"
            />
          </div>

          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
              {{ safeT('auth.email') }}
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-gray-50 text-gray-900"
              :placeholder="safeT('auth.email_placeholder')"
            />
          </div>

          <!-- Password -->
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
              {{ safeT('auth.password') }}
            </label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-gray-50 text-gray-900"
              :placeholder="safeT('auth.password_placeholder')"
            />
          </div>

          <!-- Confirm Password -->
          <div>
            <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-2">
              {{ safeT('auth.confirm_password') }}
            </label>
            <input
              id="confirmPassword"
              v-model="form.confirmPassword"
              type="password"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-gray-50 text-gray-900"
              :placeholder="safeT('auth.confirm_password_placeholder')"
            />
          </div>
        </div>

        <!-- Error Message - Removed since we're using SweetAlert -->

        <!-- Submit Button -->
        <button
          type="submit"
          :disabled="loading"
          class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
        >
          <span v-if="!loading">{{ safeT('auth.register') }}</span>
          <span v-else class="flex items-center">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ safeT('auth.registering') }}
          </span>
        </button>

        <!-- Login Link -->
        <div class="text-center">
          <p class="text-sm text-gray-600">
            {{ safeT('auth.have_account') }}
            <NuxtLink to="/auth/login" class="font-medium text-blue-600 hover:text-blue-700 transition-colors duration-200">
              {{ safeT('auth.login') }}
            </NuxtLink>
          </p>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  layout: false,
  middleware: 'guest'
})

const { $swal } = useNuxtApp()
const authStore = useAuthStore()

// Static translations instead of i18n
const safeT = (key) => {
  const translations = {
    'auth.register_title': '註冊帳戶',
    'auth.register_subtitle': '請填寫您的註冊資訊',
    'auth.full_name': '姓名',
    'auth.full_name_placeholder': '請輸入您的姓名',
    'auth.username': '用戶名',
    'auth.username_placeholder': '請輸入用戶名',
    'auth.email': '電子郵件',
    'auth.email_placeholder': '請輸入電子郵件',
    'auth.password': '密碼',
    'auth.password_placeholder': '請輸入密碼',
    'auth.confirm_password': '確認密碼',
    'auth.confirm_password_placeholder': '請再次輸入密碼',
    'auth.register': '註冊',
    'auth.registering': '註冊中...',
    'auth.have_account': '已有帳戶？',
    'auth.login': '立即登入'
  }
  return translations[key] || key
}

const form = ref({
  name: '',
  username: '',
  email: '',
  password: '',
  confirmPassword: ''
})

const loading = ref(false)

const handleRegister = async () => {
  try {
    loading.value = true
    
    // 驗證密碼匹配
    if (form.value.password !== form.value.confirmPassword) {
      $swal.fire({
        icon: 'error',
        title: '系統提示',
        text: '密碼與確認密碼不一致',
        confirmButtonText: '確定',
        confirmButtonColor: '#3B82F6'
      })
      return
    }
    
    // 密碼長度檢查
    if (form.value.password.length < 6) {
      $swal.fire({
        icon: 'error',
        title: '系統提示',
        text: '密碼至少需要6個字元',
        confirmButtonText: '確定',
        confirmButtonColor: '#3B82F6'
      })
      return
    }
    
    const result = await authStore.register({
      name: form.value.name,
      username: form.value.username,
      email: form.value.email,
      password: form.value.password,
      password_confirmation: form.value.confirmPassword
    })
    
    // 顯示成功訊息
    await $swal.fire({
      icon: 'success',
      title: '系統提示',
      text: '您的帳戶已成功建立，請使用您的帳號密碼登入',
      confirmButtonText: '前往登入',
      confirmButtonColor: '#3B82F6'
    })
    
    // 重定向到登入頁面
    await navigateTo('/auth/login')
  } catch (err) {
    // 使用 SweetAlert 顯示錯誤訊息
    const errorMessage = err && err.message ? err.message : '註冊過程發生錯誤，請重試'
    
    $swal.fire({
      icon: 'error',
      title: '系統提示',
      text: errorMessage,
      confirmButtonText: '確定',
      confirmButtonColor: '#3B82F6'
    })
    
    console.error('Registration error:', err)
  } finally {
    loading.value = false
  }
}

// 如果已經登入，重定向到首頁
onMounted(() => {
  if (authStore.isLoggedIn) {
    navigateTo('/')
  }
})
</script>