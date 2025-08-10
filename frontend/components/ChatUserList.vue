<template>
  <div class="space-y-1 p-2">
    <div
      v-for="user in users"
      :key="user.id"
      @click="$emit('userSelect', user)"
      class="flex items-center p-3 rounded-lg cursor-pointer transition-colors duration-200 hover:bg-gray-50 "
      :class="{ 
        'bg-blue-50  border border-blue-200 ': activeUserId === user.id 
      }"
    >
      <!-- 用戶頭像 -->
      <div class="relative flex-shrink-0">
        <img 
          :src="user.avatar" 
          :alt="user.name"
          class="w-12 h-12 rounded-full"
        />
        <!-- 在線狀態指示器 -->
        <div
          v-if="user.online"
          class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 border-2 border-white  rounded-full"
        ></div>
        <!-- BOT標誌 -->
        <div
          v-if="user.isBot"
          class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 border-2 border-white  rounded-full flex items-center justify-center"
        >
          <span class="text-white text-xs font-bold">B</span>
        </div>
      </div>

      <!-- 用戶資訊 -->
      <div class="ml-3 flex-1 min-w-0">
        <div class="flex items-center justify-between mb-1">
          <h4 class="text-sm font-medium text-gray-900  truncate">
            {{ user.name }}
          </h4>
          <span class="text-xs text-gray-500  flex-shrink-0 ml-2">
            {{ formatTime(user.timestamp) }}
          </span>
        </div>
        
        <div class="flex items-center justify-between">
          <p class="text-sm text-gray-600  truncate">
            {{ user.lastMessage }}
          </p>
          
          <!-- 未讀訊息數量 -->
          <div
            v-if="user.unreadCount > 0"
            class="ml-2 flex-shrink-0 w-5 h-5 bg-blue-500 text-white text-xs rounded-full flex items-center justify-center"
          >
            {{ user.unreadCount > 9 ? '9+' : user.unreadCount }}
          </div>
        </div>

        <!-- 角色標籤和客戶資訊 -->
        <div class="mt-1 space-y-1">
          <div class="flex items-center space-x-2">
            <span 
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
              :class="getRoleClass(user.role)"
            >
              {{ getRoleDisplayName(user.role) }}
            </span>
            <!-- LINE 客戶額外資訊 -->
            <span 
              v-if="user.role === 'line_customer' && user.customerInfo"
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
              :class="getStatusClass(user.customerInfo.status)"
            >
              {{ user.customerInfo.status }}
            </span>
          </div>
          <!-- LINE 客戶詳細資訊 -->
          <div v-if="user.role === 'line_customer' && user.customerInfo" class="text-xs text-gray-500 ">
            <div>{{ user.customerInfo.phone }} · {{ user.customerInfo.region }}</div>
            <div>來源：{{ user.customerInfo.source }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  users: {
    type: Array,
    required: true
  },
  activeUserId: {
    type: Number,
    default: null
  }
})

defineEmits(['userSelect'])

// 時間格式化
const formatTime = (timestamp) => {
  const now = new Date()
  const time = new Date(timestamp)
  const diffInHours = (now - time) / (1000 * 60 * 60)
  
  if (diffInHours < 1) {
    const diffInMinutes = Math.floor((now - time) / (1000 * 60))
    return diffInMinutes <= 0 ? '剛剛' : `${diffInMinutes}分鐘前`
  } else if (diffInHours < 24) {
    return `${Math.floor(diffInHours)}小時前`
  } else {
    const diffInDays = Math.floor(diffInHours / 24)
    return diffInDays === 1 ? '昨天' : `${diffInDays}天前`
  }
}

// 角色顯示名稱
const getRoleDisplayName = (role) => {
  const roleMap = {
    'dealer_executive': '經銷商',
    'admin_manager': '行政主管',
    'sales_staff': '業務人員',
    'line_bot': 'LINE BOT',
    'line_customer': 'LINE 客戶'
  }
  return roleMap[role] || role
}

// 角色樣式類別
const getRoleClass = (role) => {
  const roleClasses = {
    'dealer_executive': 'bg-purple-100 text-purple-800 ',
    'admin_manager': 'bg-green-100 text-green-800 ',
    'sales_staff': 'bg-blue-100 text-blue-800 ',
    'line_bot': 'bg-yellow-100 text-yellow-800 ',
    'line_customer': 'bg-green-100 text-green-800 '
  }
  return roleClasses[role] || 'bg-gray-100 text-gray-800 '
}

// 案件狀態樣式類別
const getStatusClass = (status) => {
  const statusClasses = {
    '待處理': 'bg-yellow-100 text-yellow-800 ',
    '進行中': 'bg-blue-100 text-blue-800 ',
    '已完成': 'bg-green-100 text-green-800 ',
    '已結案': 'bg-gray-100 text-gray-800 '
  }
  return statusClasses[status] || 'bg-gray-100 text-gray-800 '
}
</script>