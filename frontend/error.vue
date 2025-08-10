<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 flex items-center justify-center p-6 error-container">
    <div class="text-center max-w-2xl mx-auto">
      <!-- Error Code Display -->
      <div class="mb-8">
        <!-- Large Error Code Background -->
        <div class="relative mb-6">
          <h1 class="text-8xl md:text-9xl font-bold text-gray-200 select-none mb-4">
            {{ error.statusCode }}
          </h1>
        </div>
        
        <!-- Main Error Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8 border border-gray-200 mx-auto error-card">
          <ExclamationTriangleIcon class="w-16 h-16 text-red-500 mx-auto mb-4" />
          <h2 class="text-2xl font-bold text-gray-900 mb-4">
            {{ getErrorTitle() }}
          </h2>
          <p class="text-gray-600 mb-8 max-w-md mx-auto leading-relaxed">
            {{ getErrorMessage() }}
          </p>
          
          <!-- Action Buttons -->
          <div class="space-y-3 max-w-sm mx-auto">
            <button
              @click="handleGoHome"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200 flex items-center justify-center space-x-2 shadow-lg hover:shadow-xl"
            >
              <HomeIcon class="w-5 h-5" />
              <span>返回首頁</span>
            </button>
            
            <button
              @click="handleGoBack"
              class="w-full bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200 flex items-center justify-center space-x-2 shadow-lg hover:shadow-xl"
            >
              <ArrowLeftIcon class="w-5 h-5" />
              <span>返回上一頁</span>
            </button>
            
            <button
              @click="handleRefresh"
              class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200 flex items-center justify-center space-x-2 shadow-lg hover:shadow-xl"
            >
              <ArrowPathIcon class="w-5 h-5" />
              <span>重新載入</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Additional Info -->
      <div class="bg-white rounded-xl shadow-lg p-6 max-w-md mx-auto border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">需要協助？</h3>
        <p class="text-gray-600 text-sm mb-4">
          如果問題持續發生，請聯繫系統管理員或檢查以下事項：
        </p>
        <ul class="text-left text-sm text-gray-600 space-y-2">
          <li class="flex items-start space-x-2">
            <CheckIcon class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" />
            <span>確認網址拼寫是否正確</span>
          </li>
          <li class="flex items-start space-x-2">
            <CheckIcon class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" />
            <span>檢查網路連接狀況</span>
          </li>
          <li class="flex items-start space-x-2">
            <CheckIcon class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" />
            <span>清除瀏覽器快取後重試</span>
          </li>
        </ul>
      </div>

      <!-- Footer Info -->
      <div class="mt-8 text-gray-500 text-sm">
        <p>金融管理系統 © {{ new Date().getFullYear() }}</p>
        <p class="mt-1">錯誤代碼: {{ error.statusCode }} | 時間: {{ new Date().toLocaleString('zh-TW') }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { 
  ExclamationTriangleIcon,
  HomeIcon,
  ArrowLeftIcon,
  ArrowPathIcon,
  CheckIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  error: {
    type: Object,
    required: true
  }
})

// Get appropriate error title based on status code
const getErrorTitle = () => {
  switch (props.error.statusCode) {
    case 404:
      return '頁面找不到'
    case 403:
      return '存取被拒'
    case 500:
      return '伺服器錯誤'
    case 503:
      return '服務暫時無法使用'
    default:
      return '發生錯誤'
  }
}

// Get appropriate error message based on status code
const getErrorMessage = () => {
  switch (props.error.statusCode) {
    case 404:
      return '抱歉，您要找的頁面不存在或已被移除。請檢查網址是否正確，或使用下方按鈕導航到其他頁面。'
    case 403:
      return '您沒有權限存取此頁面。請確認您已登入且具有適當的存取權限。'
    case 500:
      return '伺服器發生內部錯誤。我們已收到通知並正在處理此問題，請稍後再試。'
    case 503:
      return '服務暫時無法使用。系統可能正在維護中，請稍後再試。'
    default:
      return '發生未預期的錯誤。請嘗試重新載入頁面或聯繫技術支援。'
  }
}

// Navigation handlers
const handleGoHome = async () => {
  try {
    if (process.client) {
      // 使用 window.location 代替 navigateTo 避免權限問題
      window.location.href = '/'
    }
  } catch (error) {
    console.error('Navigation error:', error)
    // 降級處理：直接使用 window.location
    if (process.client) {
      window.location.href = '/'
    }
  }
}

const handleGoBack = () => {
  try {
    if (process.client && window.history.length > 1) {
      window.history.back()
    } else {
      handleGoHome()
    }
  } catch (error) {
    console.error('Go back error:', error)
    handleGoHome()
  }
}

const handleRefresh = () => {
  try {
    if (process.client) {
      window.location.reload()
    }
  } catch (error) {
    console.error('Refresh error:', error)
    // 降級處理：嘗試重新導向到首頁
    handleGoHome()
  }
}
</script>

<style scoped>
/* Custom animations */
.animate-bounce-slow {
  animation: bounce 2s infinite;
}

@keyframes bounce {
  0%, 20%, 53%, 80%, 100% {
    transform: translate3d(0, 0, 0);
  }
  40%, 43% {
    transform: translate3d(0, -10px, 0);
  }
  70% {
    transform: translate3d(0, -5px, 0);
  }
  90% {
    transform: translate3d(0, -2px, 0);
  }
}

/* Responsive design fixes */
@media (max-width: 640px) {
  h1 {
    font-size: 4rem !important;
  }
  
  .error-container {
    padding: 1rem;
  }
  
  .error-card {
    padding: 1.5rem;
    margin: 0 1rem;
  }
}

@media (max-width: 480px) {
  h1 {
    font-size: 3rem !important;
  }
  
  .error-card {
    padding: 1rem;
  }
}

/* Fix button focus states */
button:focus {
  outline: 2px solid #3B82F6;
  outline-offset: 2px;
}

/* Ensure proper spacing */
.space-y-3 > * + * {
  margin-top: 0.75rem !important;
}
</style>