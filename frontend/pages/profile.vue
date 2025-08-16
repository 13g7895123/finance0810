<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">個人資料</h1>
      <p class="mt-1 text-sm text-gray-600">管理您的個人資訊和帳戶設定</p>
    </div>

    <!-- 個人資訊卡片 -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
      <div class="flex items-center space-x-6 mb-6">
        <div class="relative">
          <img 
            :src="userInfo.avatar" 
            :alt="userInfo.name"
            class="w-24 h-24 rounded-full border-4 border-white shadow-lg"
          />
          <button class="absolute bottom-0 right-0 bg-blue-500 hover:bg-blue-600 text-white rounded-full p-2 shadow-lg transition-colors duration-200">
            <CameraIcon class="w-4 h-4" />
          </button>
        </div>
        <div>
          <h2 class="text-xl font-semibold text-gray-900">{{ userInfo.name }}</h2>
          <p class="text-gray-600">{{ userInfo.role_display }}</p>
          <p class="text-sm text-gray-500">上次登入: {{ formatDateTime(userInfo.last_login) }}</p>
        </div>
      </div>

      <!-- 個人資訊表單 -->
      <form @submit.prevent="updateProfile" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">姓名</label>
            <input
              v-model="profileForm.name"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="請輸入姓名"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">電子信箱</label>
            <input
              v-model="profileForm.email"
              type="email"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="請輸入電子信箱"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">手機號碼</label>
            <input
              v-model="profileForm.phone"
              type="tel"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="請輸入手機號碼"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">職位</label>
            <input
              :value="userInfo.role_display"
              type="text"
              disabled
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
            />
          </div>
        </div>

        <!-- 密碼變更區域 -->
        <div class="border-t border-gray-200 pt-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">密碼變更</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">目前密碼</label>
              <input
                v-model="passwordForm.current_password"
                type="password"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="請輸入目前密碼"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">新密碼</label>
              <input
                v-model="passwordForm.new_password"
                type="password"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="請輸入新密碼"
              />
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">確認新密碼</label>
              <input
                v-model="passwordForm.new_password_confirmation"
                type="password"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="請再次輸入新密碼"
              />
            </div>
          </div>
        </div>

        <!-- 操作按鈕 -->
        <div class="flex items-center justify-end space-x-4 border-t border-gray-200 pt-6">
          <button
            type="button"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200"
            @click="resetForm"
          >
            重設
          </button>
          <button
            type="submit"
            :disabled="isUpdating"
            class="px-6 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-blue-300 text-white rounded-lg transition-colors duration-200 flex items-center space-x-2"
          >
            <span v-if="isUpdating">更新中...</span>
            <span v-else>儲存變更</span>
          </button>
        </div>
      </form>
    </div>

    <!-- 帳戶統計資訊 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center space-x-3">
          <div class="p-2 bg-blue-100 rounded-lg">
            <UserIcon class="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-500">帳戶類型</h3>
            <p class="text-lg font-semibold text-gray-900">{{ userInfo.role_display }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center space-x-3">
          <div class="p-2 bg-green-100 rounded-lg">
            <CalendarIcon class="w-6 h-6 text-green-600" />
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-500">註冊時間</h3>
            <p class="text-lg font-semibold text-gray-900">{{ formatDate(userInfo.created_at) }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center space-x-3">
          <div class="p-2 bg-purple-100 rounded-lg">
            <ClockIcon class="w-6 h-6 text-purple-600" />
          </div>
          <div>
            <h3 class="text-sm font-medium text-gray-500">上次活動</h3>
            <p class="text-lg font-semibold text-gray-900">{{ formatDateTime(userInfo.last_activity) }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { 
  CameraIcon,
  UserIcon,
  CalendarIcon,
  ClockIcon 
} from '@heroicons/vue/24/outline'

definePageMeta({
  middleware: 'auth'
})

const authStore = useAuthStore()
const { alert, error: showError, success } = useNotification()

// 使用者資訊
const userInfo = computed(() => ({
  id: authStore.user?.id || 1,
  name: authStore.user?.name || '管理員',
  email: authStore.user?.email || 'admin@example.com',
  phone: authStore.user?.phone || '0912345678',
  role: authStore.user?.role || 'admin',
  role_display: authStore.user?.role_display || '系統管理員',
  avatar: authStore.user?.avatar || 'https://ui-avatars.com/api/?name=Admin&background=6366f1&color=fff',
  last_login: authStore.user?.last_login || new Date(),
  created_at: authStore.user?.created_at || new Date('2024-01-01'),
  last_activity: authStore.user?.last_activity || new Date()
}))

// 表單資料
const profileForm = ref({
  name: userInfo.value.name,
  email: userInfo.value.email,
  phone: userInfo.value.phone
})

const passwordForm = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: ''
})

const isUpdating = ref(false)

// 更新個人資料
const updateProfile = async () => {
  isUpdating.value = true
  try {
    const { put } = useApi()
    
    // 準備更新資料
    const updateData = {
      name: profileForm.value.name,
      email: profileForm.value.email,
      phone: profileForm.value.phone
    }
    
    // 如果有密碼更新，加入密碼資料
    if (passwordForm.value.new_password) {
      if (passwordForm.value.new_password !== passwordForm.value.new_password_confirmation) {
        await showError('新密碼與確認密碼不符')
        return
      }
      
      updateData.current_password = passwordForm.value.current_password
      updateData.password = passwordForm.value.new_password
      updateData.password_confirmation = passwordForm.value.new_password_confirmation
    }
    
    // 使用專門的個人資料更新 API
    const { data, error } = await put('/auth/profile', updateData)
    
    if (error) {
      console.error('Update failed:', error)
      
      // 處理表單驗證錯誤
      if (error.errors) {
        let errorMessage = '表單驗證失敗：\n'
        Object.keys(error.errors).forEach(field => {
          errorMessage += `${error.errors[field].join(', ')}\n`
        })
        await showError(errorMessage)
      } else {
        await showError(error.message || '更新失敗，請重試')
      }
      return
    }
    
    // 更新成功，刷新用戶資料
    if (data && data.user) {
      // 更新本地用戶資料
      Object.assign(userInfo.value, data.user)
      
      // 更新表單資料
      profileForm.value = {
        name: data.user.name,
        email: data.user.email,
        phone: data.user.phone
      }
    }
    
    // 清除密碼欄位
    passwordForm.value = {
      current_password: '',
      new_password: '',
      new_password_confirmation: ''
    }
    
    // 顯示成功訊息
    await success('個人資料更新成功！')
    
  } catch (error) {
    console.error('Update failed:', error)
    await showError('更新失敗，請重試')
  } finally {
    isUpdating.value = false
  }
}

// 重設表單
const resetForm = () => {
  profileForm.value = {
    name: userInfo.value.name,
    email: userInfo.value.email,
    phone: userInfo.value.phone
  }
  passwordForm.value = {
    current_password: '',
    new_password: '',
    new_password_confirmation: ''
  }
}

// 格式化日期時間
const formatDateTime = (date) => {
  return new Date(date).toLocaleString('zh-TW', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('zh-TW', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  })
}

// 頁面標題
useHead({
  title: '個人資料 - 融資貸款公司 CRM 系統'
})
</script>