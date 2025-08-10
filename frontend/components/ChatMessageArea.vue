<template>
  <div class="flex flex-col h-full">
    <!-- 聊天標題列 -->
    <div class="p-4 border-b border-gray-200  bg-white ">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <div class="relative">
            <img 
              :src="user.avatar" 
              :alt="user.name"
              class="w-10 h-10 rounded-full"
            />
            <div
              v-if="user.online"
              class="absolute -bottom-1 -right-1 w-3 h-3 bg-green-500 border-2 border-white dark:border-gray-800 rounded-full"
            ></div>
          </div>
          <div class="ml-3">
            <h3 class="text-lg font-medium text-gray-900 ">{{ user.name }}</h3>
            <p class="text-sm text-gray-500 ">
              {{ user.online ? '線上' : '離線' }}
              <span v-if="user.isBot" class="ml-2 text-green-600">• LINE BOT</span>
            </p>
          </div>
        </div>
        
        <div class="flex items-center space-x-2">
          <button class="p-2 text-gray-500 hover:bg-gray-100  rounded-lg">
            <PhoneIcon class="w-5 h-5" />
          </button>
          <button class="p-2 text-gray-500 hover:bg-gray-100  rounded-lg">
            <VideoCameraIcon class="w-5 h-5" />
          </button>
          <button class="p-2 text-gray-500 hover:bg-gray-100  rounded-lg">
            <InformationCircleIcon class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>

    <!-- 訊息列表 -->
    <div 
      ref="messagesContainer"
      class="flex-1 overflow-y-auto bg-gray-50 custom-scrollbar-right relative"
    >
      <div class="p-4 space-y-4">
      <div
        v-for="message in messages"
        :key="message.id"
        class="flex"
        :class="{ 'justify-end': message.senderId === currentUserId }"
      >
        <div
          class="max-w-xs lg:max-w-md"
          :class="{ 'order-2': message.senderId === currentUserId }"
        >
          <!-- 訊息氣泡 -->
          <div
            class="px-4 py-2 rounded-2xl"
            :class="getMessageBubbleClass(message)"
          >
            <p class="text-sm whitespace-pre-wrap break-words">{{ message.content }}</p>
          </div>
          
          <!-- 時間戳記 -->
          <div
            class="mt-1 text-xs text-gray-500 "
            :class="{ 'text-right': message.senderId === currentUserId }"
          >
            {{ formatMessageTime(message.timestamp) }}
          </div>
        </div>

        <!-- 發送者頭像 (非當前用戶) -->
        <div
          v-if="message.senderId !== currentUserId"
          class="flex-shrink-0 ml-2 order-1"
        >
          <img 
            :src="getSenderAvatar(message.senderId)"
            :alt="getSenderName(message.senderId)"
            class="w-8 h-8 rounded-full"
          />
        </div>
      </div>

      <!-- LINE BOT 特殊訊息顯示 -->
      <div v-if="user.isBot && user.role === 'line_customer' && messages.length > 0" class="mt-6 p-4 bg-green-50  rounded-lg border border-green-200 ">
        <div class="flex items-start space-x-3">
          <div class="flex-shrink-0">
            <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
              <span class="text-white text-sm font-bold">📱</span>
            </div>
          </div>
          <div class="flex-1">
            <h4 class="text-sm font-medium text-green-800  mb-1">
              LINE 官方帳號對話記錄
            </h4>
            <p class="text-sm text-green-700  mb-2">
              此對話來自 LINE 官方帳號，系統已自動整合顯示
            </p>
            <!-- 客戶資訊 -->
            <div v-if="user.customerInfo" class="text-xs text-green-600  space-y-1">
              <div><span class="font-medium">LINE ID:</span> {{ user.lineUserId }}</div>
              <div><span class="font-medium">聯絡電話:</span> {{ user.customerInfo.phone }}</div>
              <div><span class="font-medium">地區:</span> {{ user.customerInfo.region }}</div>
              <div><span class="font-medium">來源網站:</span> {{ user.customerInfo.source }}</div>
              <div><span class="font-medium">案件狀態:</span> {{ user.customerInfo.status }}</div>
            </div>
          </div>
          <!-- 客戶操作按鈕 -->
          <div v-if="user.customerInfo" class="flex-shrink-0">
            <button class="px-3 py-1 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
              建立案件
            </button>
          </div>
        </div>
      </div>
      </div>
    </div>

    <!-- 訊息輸入區域 -->
    <div class="p-4 bg-white  border-t border-gray-200 ">
      <form @submit.prevent="sendMessage" class="flex items-end space-x-3">
        <div class="flex-1">
          <div class="relative">
            <textarea
              ref="messageInput"
              v-model="newMessage"
              @keydown.enter.exact.prevent="sendMessage"
              @keydown.enter.shift.exact="handleShiftEnter"
              placeholder="輸入訊息... (Enter 發送，Shift+Enter 換行)"
              rows="1"
              class="w-full px-4 py-3 border border-gray-300  rounded-2xl bg-white  text-gray-900  placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none max-h-32"
              style="min-height: 44px;"
            ></textarea>
          </div>
        </div>
        
        <div class="flex items-center space-x-2">
          <button
            type="button"
            class="p-2 text-gray-500 hover:bg-gray-100  rounded-lg"
          >
            <PaperClipIcon class="w-5 h-5" />
          </button>
          
          <button
            type="submit"
            :disabled="!newMessage.trim()"
            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-2xl transition-colors duration-200 flex items-center space-x-2"
          >
            <PaperAirplaneIcon class="w-4 h-4" />
            <span class="hidden sm:inline">發送</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { 
  PhoneIcon,
  VideoCameraIcon,
  InformationCircleIcon,
  PaperClipIcon,
  PaperAirplaneIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  user: {
    type: Object,
    required: true
  },
  messages: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['sendMessage'])

const authStore = useAuthStore()
const currentUserId = computed(() => authStore.user?.id)

const newMessage = ref('')
const messageInput = ref(null)
const messagesContainer = ref(null)

// 發送訊息
const sendMessage = () => {
  if (!newMessage.value.trim()) return
  
  emit('sendMessage', newMessage.value)
  newMessage.value = ''
  
  // 發送後聚焦輸入框
  nextTick(() => {
    messageInput.value?.focus()
    scrollToBottom()
  })
}

// 處理 Shift+Enter
const handleShiftEnter = () => {
  // 允許 Shift+Enter 換行，不做任何操作
}

// 滾動到底部
const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

// 監聽訊息變化，自動滾動到底部
watch(() => props.messages, () => {
  scrollToBottom()
}, { deep: true })

// 組件掛載時滾動到底部
onMounted(() => {
  scrollToBottom()
})

// 獲取訊息氣泡樣式
const getMessageBubbleClass = (message) => {
  const isCurrentUser = message.senderId === currentUserId.value
  const isBot = message.isBot
  const isCustomer = message.isCustomer
  const isAutoReply = message.isAutoReply
  
  if (isBot && isAutoReply) {
    // 系統自動回覆
    return 'bg-green-500 text-white'
  } else if (isBot && isCustomer) {
    // LINE 客戶訊息
    return 'bg-gray-200  text-gray-900  border border-gray-300 '
  } else if (isCurrentUser) {
    // 當前用戶（後台人員）訊息
    return 'bg-blue-500 text-white'
  } else {
    // 其他內部用戶訊息
    return 'bg-white  text-gray-900  border border-gray-200 '
  }
}

// 獲取發送者頭像
const getSenderAvatar = (senderId) => {
  if (senderId === props.user.id) {
    return props.user.avatar
  }
  // 可以擴展為從用戶列表或API獲取
  return 'https://ui-avatars.com/api/?name=User&background=6366f1&color=fff'
}

// 獲取發送者名稱
const getSenderName = (senderId) => {
  if (senderId === props.user.id) {
    return props.user.name
  }
  return 'Unknown User'
}

// 格式化訊息時間
const formatMessageTime = (timestamp) => {
  const time = new Date(timestamp)
  const now = new Date()
  
  // 今天的訊息只顯示時間
  if (time.toDateString() === now.toDateString()) {
    return time.toLocaleTimeString('zh-TW', { 
      hour: '2-digit', 
      minute: '2-digit' 
    })
  }
  
  // 昨天的訊息
  const yesterday = new Date(now)
  yesterday.setDate(yesterday.getDate() - 1)
  if (time.toDateString() === yesterday.toDateString()) {
    return `昨天 ${time.toLocaleTimeString('zh-TW', { 
      hour: '2-digit', 
      minute: '2-digit' 
    })}`
  }
  
  // 更早的訊息顯示日期和時間
  return time.toLocaleDateString('zh-TW', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// 自動調整文本框高度
const adjustTextareaHeight = () => {
  nextTick(() => {
    if (messageInput.value) {
      messageInput.value.style.height = 'auto'
      messageInput.value.style.height = Math.min(messageInput.value.scrollHeight, 128) + 'px'
    }
  })
}

// 監聽輸入內容變化，自動調整高度
watch(() => newMessage.value, adjustTextareaHeight)
</script>

<style scoped>
/* 自定義滾動條樣式 - 位於聊天室視窗右側 */
.custom-scrollbar-right {
  scrollbar-width: thin;
  scrollbar-color: #9ca3af #f3f4f6;
}

.custom-scrollbar-right::-webkit-scrollbar {
  width: 14px;
  position: absolute;
  right: 0;
}

.custom-scrollbar-right::-webkit-scrollbar-track {
  background: #f3f4f6;
  border-radius: 0;
  margin: 0;
  border-left: 1px solid #e5e7eb;
}

.custom-scrollbar-right::-webkit-scrollbar-thumb {
  background: #9ca3af;
  border-radius: 0;
  border: none;
  min-height: 30px;
  background-clip: padding-box;
}

.custom-scrollbar-right::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
}

.custom-scrollbar-right::-webkit-scrollbar-thumb:active {
  background: #4b5563;
}

.custom-scrollbar-right::-webkit-scrollbar-corner {
  background: #f3f4f6;
}

/* 確保滾動條始終可見並位於最右側 */
.custom-scrollbar-right::-webkit-scrollbar {
  background: #f3f4f6;
  border-left: 1px solid #e5e7eb;
}

/* 為 Firefox 提供滾動條樣式 */
@supports (scrollbar-width: thin) {
  .custom-scrollbar-right {
    scrollbar-width: auto;
    scrollbar-color: #9ca3af #f3f4f6;
  }
}
</style>