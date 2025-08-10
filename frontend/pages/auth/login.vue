<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-indigo-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white rounded-2xl shadow-xl border border-gray-100 p-8">
      <!-- Logo & Title -->
      <div class="text-center">
        <div class="mx-auto w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center mb-6 shadow-lg">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <h2 class="text-3xl font-bold text-gray-900">
          {{ safeT('auth.login_title') }}
        </h2>
        <p class="mt-2 text-sm text-gray-600">
          {{ safeT('auth.login_subtitle') }}
        </p>
      </div>

      <!-- Login Form -->
      <form class="mt-8 space-y-6" @submit.prevent="handleLogin">
        <div class="space-y-4">
          <!-- Username/Email -->
          <div>
            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
              {{ safeT('auth.username_email') }}
            </label>
            <input
              id="username"
              v-model="form.username"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-gray-50 text-gray-900"
              :placeholder="safeT('auth.username_email_placeholder')"
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
        </div>

        <!-- Error Message -->
        <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
          <p class="text-sm text-red-700">{{ error }}</p>
        </div>

        <!-- Demo Accounts Info -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h4 class="text-sm font-medium text-blue-800 mb-3">測試帳號</h4>
          <div class="space-y-2 text-xs text-blue-700">
            <div class="flex justify-between items-center p-2 bg-white rounded border border-gray-100">
              <div>
                <div class="font-medium">經銷商/公司高層</div>
                <div class="text-gray-500">dealer01 / dealer123</div>
              </div>
              <button 
                @click="fillCredentials('dealer01', 'dealer123')"
                class="px-2 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors"
              >
                使用
              </button>
            </div>
            <div class="flex justify-between items-center p-2 bg-white rounded border border-gray-100">
              <div>
                <div class="font-medium">行政人員/主管</div>
                <div class="text-gray-500">admin01 / admin123</div>
              </div>
              <button 
                @click="fillCredentials('admin01', 'admin123')"
                class="px-2 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors"
              >
                使用
              </button>
            </div>
            <div class="flex justify-between items-center p-2 bg-white rounded border border-gray-100">
              <div>
                <div class="font-medium">業務人員</div>
                <div class="text-gray-500">sales01 / sales123</div>
              </div>
              <button 
                @click="fillCredentials('sales01', 'sales123')"
                class="px-2 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors"
              >
                使用
              </button>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          :disabled="loading"
          class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
        >
          <span v-if="!loading">{{ safeT('auth.login') }}</span>
          <span v-else class="flex items-center">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ safeT('auth.logging_in') }}
          </span>
        </button>

        <!-- Register Link -->
        <div class="text-center">
          <p class="text-sm text-gray-600">
            {{ safeT('auth.no_account') }}
            <NuxtLink to="/auth/register" class="font-medium text-blue-600 hover:text-blue-700 transition-colors duration-200">
              {{ safeT('auth.register') }}
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

// Removed i18n usage to prevent warnings
// const { t } = useI18n()
const authStore = useAuthStore()

// Static translations instead of i18n
const safeT = (key) => {
  const translations = {
    'auth.login_title': '登入帳戶',
    'auth.login_subtitle': '請輸入您的帳戶資訊',
    'auth.username_email': '用戶名或郵箱',
    'auth.username_email_placeholder': '請輸入用戶名或郵箱',
    'auth.password': '密碼',
    'auth.password_placeholder': '請輸入密碼',
    'auth.login': '登入',
    'auth.logging_in': '登入中...',
    'auth.no_account': '還沒有帳戶？',
    'auth.register': '立即註冊'
  }
  return translations[key] || key
}

const form = ref({
  username: '',
  password: ''
})

const loading = ref(false)
const error = ref('')

const handleLogin = async () => {
  try {
    loading.value = true
    error.value = ''
    
    const result = await authStore.login(form.value)
    
    // 檢查登入結果
    if (result && result.success && result.user) {
      // 根據用戶角色重定向到適當頁面
      if (result.user.role === authStore.roles.SALES_STAFF) {
        await navigateTo('/sales/customers')
      } else {
        await navigateTo('/dashboard/analytics')
      }
    } else {
      throw new Error('登入失敗，請重試')
    }
  } catch (err) {
    // 安全地處理錯誤消息
    error.value = err && err.message ? err.message : '登入過程發生錯誤，請重試'
    console.error('Login error:', err)
  } finally {
    loading.value = false
  }
}

// 填入測試帳號
const fillCredentials = (username, password) => {
  form.value.username = username
  form.value.password = password
}

// 如果已經登入，重定向到首頁
onMounted(() => {
  if (authStore.isLoggedIn) {
    navigateTo('/')
  }
})
</script>