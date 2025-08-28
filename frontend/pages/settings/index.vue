<template>
  <div class="space-y-6">
    <div class="bg-white rounded-lg-custom shadow-sm p-6">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">
        設定
      </h2>
      <p class="text-gray-600 mb-6">
        管理您的應用程式設定和偏好。
      </p>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <NuxtLink
          to="/settings/theme"
          class="block p-6 border border-gray-200 rounded-lg hover:border-primary-500 transition-colors duration-200"
        >
          <h3 class="text-lg font-semibold text-gray-900 mb-2">
            主題設定
          </h3>
          <p class="text-gray-600 text-sm">
            自定義顏色主題和顯示模式
          </p>
        </NuxtLink>

        <NuxtLink
          to="/settings/users"
          class="block p-6 border border-gray-200 rounded-lg hover:border-primary-500 transition-colors duration-200"
        >
          <h3 class="text-lg font-semibold text-gray-900 mb-2">
            用戶管理
          </h3>
          <p class="text-gray-600 text-sm">
            管理用戶帳戶和權限
          </p>
        </NuxtLink>
        
        <NuxtLink
          to="/settings/permissions"
          class="block p-6 border border-gray-200 rounded-lg hover:border-primary-500 transition-colors duration-200"
        >
          <h3 class="text-lg font-semibold text-gray-900 mb-2">
            權限設定
          </h3>
          <p class="text-gray-600 text-sm">
            管理角色和權限配置
          </p>
        </NuxtLink>

        <NuxtLink
          to="/settings/line"
          class="block p-6 border border-gray-200 rounded-lg hover:border-primary-500 transition-colors duration-200"
        >
          <h3 class="text-lg font-semibold text-gray-900 mb-2">
            LINE 整合
          </h3>
          <p class="text-gray-600 text-sm">
            LINE Bot 設定與整合管理
          </p>
        </NuxtLink>

        <NuxtLink
          to="/settings/custom-fields"
          class="block p-6 border border-gray-200 rounded-lg hover:border-primary-500 transition-colors duration-200"
        >
          <h3 class="text-lg font-semibold text-gray-900 mb-2">
            自定義欄位
          </h3>
          <p class="text-gray-600 text-sm">
            管理客戶資料的自定義欄位
          </p>
        </NuxtLink>

        <!-- Debug Settings - Always show for debugging -->
        <NuxtLink
          to="/settings/debug"
          class="block p-6 border-2 border-red-200 rounded-lg hover:border-red-500 transition-colors duration-200 bg-red-50 "
        >
          <div class="flex items-center mb-2">
            <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <h3 class="text-lg font-semibold text-red-900 ">
              系統除錯
            </h3>
          </div>
          <p class="text-red-700 text-sm">
            Firebase診斷、資料同步與系統監控
          </p>
          <!-- Debug info -->
          <div v-if="showDebugInfo" class="mt-2 p-2 bg-red-100 rounded text-xs">
            <div>用戶: {{ user?.name || '未載入' }}</div>
            <div>角色: {{ getUserRoleNames() }}</div>
            <div>權限: {{ canAccessDebug ? '有權限' : '無權限' }}</div>
            <div>Debug Mode: {{ isDebugMode ? '開啟' : '關閉' }}</div>
          </div>
        </NuxtLink>
        
        <div class="block p-6 border border-gray-200 rounded-lg">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">
            一般設定
          </h3>
          <p class="text-gray-600 text-sm">
            基本應用程式設定
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth'
})

// Composables  
const { user, isAdmin, isManager, hasRole } = useAuth()

// State
const showDebugInfo = ref(process.dev || false)
const isDebugMode = ref(false)

// Methods
const getUserRoleNames = () => {
  if (!user.value || !user.value.roles) return '無角色'
  return Array.isArray(user.value.roles) ? user.value.roles.join(', ') : '角色格式錯誤'
}

// Computed
const canAccessDebug = computed(() => {
  // 直接使用useAuth提供的方法，這些方法已經正確處理了角色檢查
  return isAdmin() || isManager() || hasRole('executive')
})

// 開發模式下顯示更多調試信息
onMounted(() => {
  if (process.dev) {
    console.log('Settings page - User object:', user.value)
    console.log('Settings page - Can access debug:', canAccessDebug.value)
    showDebugInfo.value = true
    
    // 檢查 localStorage 中的 debug 設定
    isDebugMode.value = localStorage.getItem('firebase_debug_mode') === 'true' || 
                       localStorage.getItem('debug_panel_enabled') === 'true'
  }
})
</script>