<template>
  <div class="p-6 space-y-6">
    <div class="mb-6">
      <h1 class="text-3xl font-bold text-gray-900">SweetAlert 測試頁面</h1>
      <p class="text-gray-600 mt-2">測試所有類型的通知功能</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <!-- 成功通知 -->
      <button
        @click="testSuccess"
        class="p-4 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors"
      >
        測試成功通知
      </button>

      <!-- 錯誤通知 -->
      <button
        @click="testError"
        class="p-4 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors"
      >
        測試錯誤通知
      </button>

      <!-- 警告通知 -->
      <button
        @click="testWarning"
        class="p-4 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors"
      >
        測試警告通知
      </button>

      <!-- 資訊通知 -->
      <button
        @click="testInfo"
        class="p-4 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors"
      >
        測試資訊通知
      </button>

      <!-- 確認對話框 -->
      <button
        @click="testConfirm"
        class="p-4 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors"
      >
        測試確認對話框
      </button>

      <!-- 輸入對話框 -->
      <button
        @click="testPrompt"
        class="p-4 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-colors"
      >
        測試輸入對話框
      </button>

      <!-- 載入中提示 -->
      <button
        @click="testLoading"
        class="p-4 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
      >
        測試載入中提示
      </button>

      <!-- 一般 Alert -->
      <button
        @click="testAlert"
        class="p-4 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors"
      >
        測試一般 Alert
      </button>
    </div>

    <!-- 測試結果顯示 -->
    <div v-if="testResults.length > 0" class="mt-8">
      <h2 class="text-xl font-bold text-gray-900 mb-4">測試結果</h2>
      <div class="space-y-2">
        <div
          v-for="(result, index) in testResults"
          :key="index"
          class="p-3 bg-gray-100 rounded-lg border-l-4"
          :class="{
            'border-green-500': result.type === 'success',
            'border-red-500': result.type === 'error',
            'border-yellow-500': result.type === 'warning',
            'border-blue-500': result.type === 'info'
          }"
        >
          <span class="font-medium">{{ result.action }}:</span>
          <span class="ml-2">{{ result.message }}</span>
          <span class="text-xs text-gray-500 ml-2">({{ result.timestamp }})</span>
        </div>
      </div>
      <button
        @click="clearResults"
        class="mt-4 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors"
      >
        清除結果
      </button>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth'
})

const { success, error, warning, info, confirm, prompt, loading, closeLoading, alert } = useNotification()

const testResults = ref([])

const addResult = (action, message, type = 'info') => {
  testResults.value.push({
    action,
    message,
    type,
    timestamp: new Date().toLocaleTimeString()
  })
}

const testSuccess = async () => {
  await success('這是一個成功通知的測試！')
  addResult('成功通知', '成功顯示', 'success')
}

const testError = async () => {
  await error('這是一個錯誤通知的測試！')
  addResult('錯誤通知', '成功顯示', 'error')
}

const testWarning = async () => {
  await warning('這是一個警告通知的測試！')
  addResult('警告通知', '成功顯示', 'warning')
}

const testInfo = async () => {
  await info('這是一個資訊通知的測試！')
  addResult('資訊通知', '成功顯示', 'info')
}

const testConfirm = async () => {
  const result = await confirm('您確定要執行這個操作嗎？')
  addResult('確認對話框', result ? '用戶點擊確定' : '用戶點擊取消', result ? 'success' : 'warning')
}

const testPrompt = async () => {
  const result = await prompt('請輸入您的姓名：', '系統提示', '請輸入姓名')
  addResult('輸入對話框', result ? `用戶輸入：${result}` : '用戶取消輸入', result ? 'success' : 'warning')
}

const testLoading = async () => {
  loading('正在處理，請稍候...')
  addResult('載入中提示', '開始顯示載入', 'info')
  
  // 模擬 3 秒的處理時間
  setTimeout(() => {
    closeLoading()
    addResult('載入中提示', '載入提示已關閉', 'success')
  }, 3000)
}

const testAlert = async () => {
  await alert('這是一個一般 Alert 的測試！')
  addResult('一般 Alert', '成功顯示', 'info')
}

const clearResults = () => {
  testResults.value = []
}

// 頁面標題
useHead({
  title: 'SweetAlert 測試 - 融資貸款公司 CRM 系統'
})
</script>