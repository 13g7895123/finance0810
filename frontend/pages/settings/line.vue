<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">LINE 整合</h1>
        <p class="text-gray-600 mt-2">設定 LINE BOT 整合功能，管理聊天室中的 LINE 客戶對話</p>
      </div>
      <div class="flex items-center space-x-3">
        <UBadge 
          :color="integrationStatusColor" 
          :variant="integrationStatus === 'configured' ? 'solid' : 'soft'"
        >
          {{ integrationStatusText }}
        </UBadge>
        <UButton 
          @click="handleTestConnection" 
          :loading="testing"
          :disabled="!settings?.channel_access_token"
          variant="outline"
        >
          測試連線
        </UButton>
      </div>
    </div>

    <!-- 載入狀態 -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <USpinner size="lg" />
    </div>

    <!-- 主要內容 -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- 基本設定 -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow-sm p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-gray-900">基本設定</h2>
            <UButton 
              @click="saveSettings" 
              :loading="saving"
              :disabled="!hasChanges"
            >
              儲存設定
            </UButton>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Channel Access Token -->
            <UFormGroup 
              label="Channel Access Token" 
              description="從 LINE Developers Console 取得的 Channel Access Token"
              required
            >
              <UInput 
                v-model="form.channel_access_token"
                type="password"
                placeholder="輸入 Channel Access Token"
                class="line-input"
              />
            </UFormGroup>

            <!-- Channel Secret -->
            <UFormGroup 
              label="Channel Secret" 
              description="從 LINE Developers Console 取得的 Channel Secret"
              required
            >
              <UInput 
                v-model="form.channel_secret"
                type="password"
                placeholder="輸入 Channel Secret"
                class="line-input"
              />
            </UFormGroup>

            <!-- Bot Basic ID -->
            <UFormGroup 
              label="Bot Basic ID" 
              description="LINE Bot 的 Basic ID（選填）"
            >
              <UInput 
                v-model="form.bot_basic_id"
                placeholder="@xxxxxxxx"
                class="line-input"
              />
            </UFormGroup>

            <!-- Webhook URL -->
            <UFormGroup 
              label="Webhook URL" 
              description="設定到 LINE Developers Console"
            >
              <UInput 
                :model-value="webhookUrl"
                readonly
                class="font-mono text-sm line-input"
              />
              <template #help>
                <UButton 
                  @click="copyWebhookUrl" 
                  variant="ghost" 
                  size="2xs"
                  class="mt-1"
                >
                  複製網址
                </UButton>
              </template>
            </UFormGroup>
          </div>
        </div>
      </div>

      <!-- 統計資訊 -->
      <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">LINE 整合統計</h3>

        <div class="space-y-4">
          <div v-if="statsLoading" class="flex justify-center py-8">
            <USpinner />
          </div>
          
          <div v-else-if="stats" class="space-y-3">
            <div class="flex justify-between items-center py-2 border-b border-gray-100">
              <span class="text-gray-600">總對話數</span>
              <span class="font-semibold text-gray-900">{{ stats.total_conversations }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-100">
              <span class="text-gray-600">總訊息數</span>
              <span class="font-semibold text-gray-900">{{ stats.total_messages }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-100">
              <span class="text-gray-600">未讀訊息</span>
              <span class="font-semibold text-red-600">{{ stats.unread_messages }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-100">
              <span class="text-gray-600">今日訊息</span>
              <span class="font-semibold text-blue-600">{{ stats.today_messages }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-100">
              <span class="text-gray-600">本週訊息</span>
              <span class="font-semibold text-green-600">{{ stats.this_week_messages }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-100">
              <span class="text-gray-600">7日活躍客戶</span>
              <span class="font-semibold text-purple-600">{{ stats.active_customers_7_days }}</span>
            </div>
            <div class="flex justify-between items-center py-2">
              <span class="text-gray-600">回覆率</span>
              <span class="font-semibold text-indigo-600">{{ stats.response_rate }}%</span>
            </div>
          </div>
        </div>

        <div class="mt-4">
          <UButton 
            @click="loadStats" 
            variant="ghost" 
            size="sm"
            block
            :loading="statsLoading"
          >
            重新整理統計
          </UButton>
        </div>
      </div>

      <!-- LINE Bot 資訊 -->
      <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">LINE Bot 資訊</h3>

        <div class="space-y-4">
          <div v-if="botInfoLoading" class="flex justify-center py-8">
            <USpinner />
          </div>
          
          <div v-else-if="botInfo" class="space-y-3">
            <div class="flex justify-between items-center py-2 border-b border-gray-100">
              <span class="text-gray-600">Bot 名稱</span>
              <span class="font-semibold text-gray-900">{{ botInfo.displayName || '未設定' }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-100">
              <span class="text-gray-600">User ID</span>
              <span class="font-mono text-sm text-gray-900">{{ botInfo.userId || '未知' }}</span>
            </div>
            <div class="flex justify-between items-center py-2">
              <span class="text-gray-600">狀態</span>
              <UBadge color="green" variant="soft">正常運作</UBadge>
            </div>
          </div>

          <div v-else-if="!settings?.channel_access_token" class="text-center py-8">
            <p class="text-gray-500">請先設定 Channel Access Token</p>
          </div>
        </div>

        <div class="mt-4">
          <UButton 
            @click="loadBotInfo" 
            variant="ghost" 
            size="sm"
            block
            :loading="botInfoLoading"
            :disabled="!settings?.channel_access_token"
          >
            重新載入 Bot 資訊
          </UButton>
        </div>
      </div>
    </div>

    <!-- 最近對話 -->
    <div class="bg-white rounded-lg shadow-sm p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">最近 LINE 對話</h3>

      <div v-if="conversationsLoading" class="flex justify-center py-8">
        <USpinner />
      </div>
      
      <div v-else-if="recentConversations && recentConversations.length > 0" class="space-y-3">
        <div 
          v-for="conversation in recentConversations" 
          :key="conversation.line_user_id"
          class="flex items-center justify-between p-4 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors"
        >
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
              <UIcon name="i-heroicons-user" class="w-5 h-5 text-green-600" />
            </div>
            <div>
              <p class="font-medium text-gray-900">
                {{ conversation.customer?.name || '未知客戶' }}
              </p>
              <p class="text-sm text-gray-500 truncate max-w-xs">
                {{ conversation.last_message || '無訊息' }}
              </p>
            </div>
          </div>
          <div class="text-right">
            <p class="text-sm text-gray-500">
              {{ formatTime(conversation.last_message_time) }}
            </p>
            <UBadge 
              v-if="conversation.unread_count > 0"
              color="red" 
              variant="soft"
              class="mt-1"
            >
              {{ conversation.unread_count }} 未讀
            </UBadge>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-8">
        <UIcon name="i-heroicons-chat-bubble-left-right" class="w-12 h-12 text-gray-400 mx-auto mb-2" />
        <p class="text-gray-500">尚無 LINE 對話記錄</p>
      </div>

      <div class="mt-4">
        <UButton 
          @click="loadRecentConversations" 
          variant="ghost" 
          size="sm"
          block
          :loading="conversationsLoading"
        >
          重新整理對話列表
        </UButton>
      </div>
    </div>
  </div>

  <!-- Toast 通知 -->
  <UNotifications />
</template>

<script setup>
import { computed, onMounted } from 'vue'

definePageMeta({
  middleware: 'auth'
})

// Composables
const { getSettings, updateSettings, testConnection, getBotInfo, getStats, getRecentConversations } = useLineIntegration()
const toast = useToast()

// 狀態管理
const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const statsLoading = ref(false)
const botInfoLoading = ref(false)
const conversationsLoading = ref(false)

// 資料狀態
const settings = ref(null)
const originalSettings = ref(null)
const stats = ref(null)
const botInfo = ref(null)
const recentConversations = ref([])

// 表單資料
const form = ref({
  channel_access_token: '',
  channel_secret: '',
  bot_basic_id: ''
})

// 計算屬性
const webhookUrl = computed(() => {
  const config = useRuntimeConfig()
  return `${config.public.apiBaseUrl || ''}/line/webhook`
})

const integrationStatus = computed(() => {
  if (!settings.value) return 'not_configured'
  return settings.value.integration_status || 'not_configured'
})

const integrationStatusText = computed(() => {
  const statusMap = {
    'configured': '已設定',
    'partial': '部分設定',
    'not_configured': '未設定'
  }
  return statusMap[integrationStatus.value] || '未知'
})

const integrationStatusColor = computed(() => {
  const colorMap = {
    'configured': 'green',
    'partial': 'yellow',
    'not_configured': 'red'
  }
  return colorMap[integrationStatus.value] || 'gray'
})

const hasChanges = computed(() => {
  if (!originalSettings.value) return false
  return JSON.stringify(form.value) !== JSON.stringify(originalSettings.value)
})

// 方法
const loadSettings = async () => {
  loading.value = true
  try {
    const response = await getSettings()
    if (response.data) {
      settings.value = response.data.settings
      form.value = {
        channel_access_token: response.data.settings.channel_access_token || '',
        channel_secret: response.data.settings.channel_secret || '',
        bot_basic_id: response.data.settings.bot_basic_id || ''
      }
      originalSettings.value = JSON.parse(JSON.stringify(form.value))
    }
  } catch (error) {
    toast.add({
      title: '載入設定失敗',
      description: error.message,
      color: 'red'
    })
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  saving.value = true
  try {
    const response = await updateSettings(form.value)
    if (response.data) {
      toast.add({
        title: '設定已儲存',
        description: response.data.message,
        color: 'green'
      })
      originalSettings.value = JSON.parse(JSON.stringify(form.value))
      await loadSettings() // 重新載入設定
    }
  } catch (error) {
    toast.add({
      title: '儲存設定失敗',
      description: error.message,
      color: 'red'
    })
  } finally {
    saving.value = false
  }
}

const handleTestConnection = async () => {
  testing.value = true
  try {
    const response = await testConnection()
    if (response.data) {
      const { status, message } = response.data
      toast.add({
        title: status === 'success' ? '連線測試成功' : '連線測試失敗',
        description: message,
        color: status === 'success' ? 'green' : 'red'
      })
    }
  } catch (error) {
    toast.add({
      title: '連線測試失敗',
      description: error.message,
      color: 'red'
    })
  } finally {
    testing.value = false
  }
}

const loadStats = async () => {
  statsLoading.value = true
  try {
    const response = await getStats()
    if (response.data) {
      stats.value = response.data.stats
    }
  } catch (error) {
    console.error('Failed to load stats:', error)
  } finally {
    statsLoading.value = false
  }
}

const loadBotInfo = async () => {
  botInfoLoading.value = true
  try {
    const response = await getBotInfo()
    if (response.data && response.data.status === 'success') {
      botInfo.value = response.data.bot_info
    }
  } catch (error) {
    console.error('Failed to load bot info:', error)
  } finally {
    botInfoLoading.value = false
  }
}

const loadRecentConversations = async () => {
  conversationsLoading.value = true
  try {
    const response = await getRecentConversations(5)
    if (response.data) {
      recentConversations.value = response.data.conversations
    }
  } catch (error) {
    console.error('Failed to load recent conversations:', error)
  } finally {
    conversationsLoading.value = false
  }
}

const copyWebhookUrl = async () => {
  try {
    await navigator.clipboard.writeText(webhookUrl.value)
    toast.add({
      title: '已複製網址',
      description: 'Webhook URL 已複製到剪貼簿',
      color: 'green'
    })
  } catch (error) {
    toast.add({
      title: '複製失敗',
      description: '無法複製網址到剪貼簿',
      color: 'red'
    })
  }
}

const formatTime = (timestamp) => {
  if (!timestamp) return '未知'
  const date = new Date(timestamp)
  return date.toLocaleString('zh-TW')
}

// 生命週期
onMounted(async () => {
  await loadSettings()
  loadStats()
  loadBotInfo()
  loadRecentConversations()
})

useHead({
  title: 'LINE 整合 - 貸款案件管理系統'
})
</script>

<style scoped>
/* LINE 整合頁面自定義樣式 */
:deep(.line-input input) {
  border: 1px solid #d1d5db !important; /* 淡灰色邊框 */
  box-shadow: none !important;
}

:deep(.line-input input:focus) {
  border-color: #d1d5db !important; /* focus 時保持淡灰色邊框 */
  box-shadow: none !important;
  ring: 0 !important;
  outline: none !important;
}

:deep(.line-input input:hover) {
  border-color: #9ca3af !important; /* hover 時稍微深一點的灰色 */
}

/* 移除預設的 focus ring */
:deep(.line-input input:focus-visible) {
  outline: none !important;
  box-shadow: none !important;
}
</style>