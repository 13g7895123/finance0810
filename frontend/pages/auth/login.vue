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

        <!-- Error Message - Removed since we're using SweetAlert -->

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
const { $swal } = useNuxtApp()
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

const handleLogin = async () => {
  try {
    loading.value = true
    console.log('開始登入流程...')
    console.log('表單資料:', form.value)
    
    // 確保表單資料格式正確
    if (!form.value.username || !form.value.password) {
      throw new Error('請填寫完整的登入資訊')
    }
    
    const result = await authStore.login(form.value)
    console.log('Auth store login 結果:', result)
    
    // 檢查登入結果
    if (result && result.success && result.user) {
      console.log('登入成功，用戶資料:', result.user)
      console.log('用戶角色:', result.user.role)
      
      // 等待 auth store 狀態更新
      await nextTick()
      
      // 檢查 auth store 狀態
      console.log('Auth store isLoggedIn:', authStore.isLoggedIn)
      console.log('Auth store user:', authStore.user)
      
      if (!authStore.isLoggedIn) {
        console.log('等待認證狀態更新...')
        await new Promise(resolve => setTimeout(resolve, 100))
        
        if (!authStore.isLoggedIn) {
          console.error('認證狀態更新失敗')
          throw new Error('認證狀態更新失敗，請重新嘗試')
        }
      }
      
      // 確保狀態完全更新後再重定向
      await new Promise(resolve => setTimeout(resolve, 100))
      
      // 再次確認登入狀態
      if (!authStore.isLoggedIn) {
        console.error('登入狀態確認失敗，等待狀態更新')
        await new Promise(resolve => setTimeout(resolve, 200))
      }
      
      // 檢查是否有指定的重定向路徑
      const route = useRoute()
      let redirectPath = route.query.redirect || '/dashboard/analytics'
      
      // 如果沒有指定重定向路徑，根據用戶角色決定
      if (!route.query.redirect) {
        if (result.user.role === 'staff') {
          redirectPath = '/sales/customers'
        }
      }
      
      console.log('準備重定向到:', redirectPath)
      console.log('當前登入狀態:', authStore.isLoggedIn)
      console.log('用戶資料:', authStore.user)
      
      // 使用 window.location.href 進行強制重定向，避免中間件競爭
      try {
        console.log('使用 window.location.href 進行重定向')
        // 使用 replace 避免產生歷史記錄
        if (process.client) {
          window.location.replace(redirectPath)
        } else {
          await navigateTo(redirectPath)
        }
      } catch (error) {
        console.warn('重定向失敗，嘗試使用 navigateTo:', error)
        await navigateTo(redirectPath)
      }
      
    } else {
      console.error('登入回應異常:', result)
      throw new Error('登入失敗，請重試')
    }
  } catch (err) {
    console.error('登入錯誤:', err)
    
    // 使用 SweetAlert 顯示錯誤消息
    const errorMessage = err && err.message ? err.message : '登入過程發生錯誤，請重試'
    
    $swal.fire({
      icon: 'error',
      title: '系統提示',
      text: errorMessage,
      confirmButtonText: '確定',
      confirmButtonColor: '#3B82F6'
    })
  } finally {
    loading.value = false
  }
}

// SweetAlert 插件檢查（開發模式）
onMounted(() => {
  if (import.meta.dev && !$swal) {
    console.error('SweetAlert plugin not available')
  }
})
</script>