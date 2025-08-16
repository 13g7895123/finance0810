<template>
  <div class="p-8">
    <div class="max-w-2xl mx-auto">
      <h1 class="text-2xl font-bold mb-6">API URL 測試頁面</h1>
      
      <div class="bg-white rounded-lg shadow-sm p-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <h3 class="font-semibold text-gray-700 mb-2">環境資訊</h3>
            <div class="bg-gray-50 p-3 rounded text-sm">
              <p><strong>NODE_ENV:</strong> {{ nodeEnv }}</p>
              <p><strong>開發模式:</strong> {{ isDev ? '是' : '否' }}</p>
              <p><strong>客戶端:</strong> {{ isClient ? '是' : '否' }}</p>
              <p><strong>當前域名:</strong> {{ currentHostname }}</p>
            </div>
          </div>
          
          <div>
            <h3 class="font-semibold text-gray-700 mb-2">API 設定</h3>
            <div class="bg-blue-50 p-3 rounded text-sm">
              <p><strong>檢測到的 API URL:</strong></p>
              <code class="block mt-1 text-blue-800 break-all">{{ detectedApiUrl }}</code>
            </div>
          </div>
        </div>
        
        <div>
          <h3 class="font-semibold text-gray-700 mb-2">API 連線測試</h3>
          <div class="flex items-center space-x-4">
            <UButton @click="testApiConnection" :loading="testing">
              測試 API 連線
            </UButton>
            <span v-if="testResult" :class="testResult.success ? 'text-green-600' : 'text-red-600'">
              {{ testResult.message }}
            </span>
          </div>
        </div>
        
        <div v-if="testDetails" class="bg-gray-50 p-4 rounded">
          <h4 class="font-semibold mb-2">詳細資訊:</h4>
          <pre class="text-xs overflow-x-auto">{{ JSON.stringify(testDetails, null, 2) }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  layout: 'default'
})

const { apiRequest } = useApi()

// 環境資訊
const config = useRuntimeConfig()
const nodeEnv = config.public.nodeEnv || 'unknown'
const isDev = import.meta.dev
const isClient = import.meta.client
const currentHostname = import.meta.client ? window.location.hostname : 'server-side'

// 取得檢測到的 API URL
const detectedApiUrl = config.public.apiBaseUrl || '未設定'

// 測試狀態
const testing = ref(false)
const testResult = ref(null)
const testDetails = ref(null)

// 測試 API 連線
const testApiConnection = async () => {
  testing.value = true
  testResult.value = null
  testDetails.value = null
  
  try {
    const result = await apiRequest('GET', '/health')
    
    if (result.data) {
      testResult.value = {
        success: true,
        message: '連線成功！'
      }
      testDetails.value = result.data
    } else {
      testResult.value = {
        success: false,
        message: '連線失敗: ' + (result.error?.message || '未知錯誤')
      }
      testDetails.value = result.error
    }
  } catch (error) {
    testResult.value = {
      success: false,
      message: '連線錯誤: ' + error.message
    }
    testDetails.value = { error: error.message }
  } finally {
    testing.value = false
  }
}
</script>