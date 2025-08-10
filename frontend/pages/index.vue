<template>
  <div class="space-y-6">
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <div
        v-for="stat in stats"
        :key="stat.name"
        class="bg-white dark:bg-gray-800 rounded-lg-custom shadow-sm p-6"
      >
        <div class="flex items-center">
          <div class="p-3 rounded-lg bg-primary-100 dark:bg-primary-900">
            <component v-if="getIcon(stat.icon)" :is="getIcon(stat.icon)" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
            <ClockIcon v-else class="w-6 h-6 text-primary-600 dark:text-primary-400" />
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ stat.name }}</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stat.value }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- WP Sites Today Cases -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div
        v-for="site in wpSites"
        :key="site.name"
        class="bg-white dark:bg-gray-800 rounded-lg-custom shadow-sm p-6"
      >
        <div class="flex items-center justify-between mb-4">
          <h4 class="text-lg font-semibold text-gray-900 dark:text-white">{{ site.name }}</h4>
          <span class="text-sm text-gray-500 dark:text-gray-400">今日案件</span>
        </div>
        <div class="text-3xl font-bold text-primary-600 dark:text-primary-400">
          {{ site.todayCases }}
        </div>
        <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
          較昨日 {{ site.change > 0 ? '+' : '' }}{{ site.change }}
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Chart Section -->
      <div class="bg-white dark:bg-gray-800 rounded-lg-custom shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          數據趨勢
        </h3>
        <div class="h-64 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
          <p class="text-gray-500 dark:text-gray-400">圖表區域 (可整合圖表庫)</p>
        </div>
      </div>

      <!-- Activity Feed -->
      <div class="bg-white dark:bg-gray-800 rounded-lg-custom shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          最新活動
        </h3>
        <ClientOnly>
          <div class="space-y-4">
            <div
              v-for="activity in activities"
              :key="activity.id"
              class="flex items-start space-x-3"
            >
              <div class="w-2 h-2 mt-2 bg-primary-500 rounded-full"></div>
              <div class="flex-1">
                <p class="text-sm text-gray-900 dark:text-white">{{ activity.description }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ activity.time }}</p>
              </div>
            </div>
          </div>
          <template #fallback>
            <div class="flex items-center justify-center py-8">
              <div class="text-gray-500 dark:text-gray-400">載入中...</div>
            </div>
          </template>
        </ClientOnly>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  ClipboardDocumentListIcon,
  DocumentCheckIcon,
  UserGroupIcon,
  ClockIcon
} from '@heroicons/vue/24/outline'

definePageMeta({
  middleware: 'auth'
})

// 移除自動重定向以避免hydration mismatch

const stats = [
  { name: '待處理案件', value: '23', icon: 'ClipboardDocumentListIcon' },
  { name: '可送件案件', value: '15', icon: 'DocumentCheckIcon' },
  { name: '已洽談客戶', value: '42', icon: 'UserGroupIcon' },
  { name: '今日新增', value: '8', icon: 'ClockIcon' }
]

// WP網站今日案件數據 (可由設定中調整)
const wpSites = [
  { name: '熊好貸', todayCases: 12, change: 3 },
  { name: '和潤汽車貸款', todayCases: 8, change: -1 },
  { name: '機車貸款專家', todayCases: 15, change: 5 }
]

const activities = [
  { id: 1, description: '新用戶註冊', time: '2分鐘前' },
  { id: 2, description: '系統備份完成', time: '15分鐘前' },
  { id: 3, description: '收到新訂單', time: '1小時前' },
  { id: 4, description: '服務器維護完成', time: '2小時前' }
]

const iconComponents = {
  ClipboardDocumentListIcon,
  DocumentCheckIcon,
  UserGroupIcon,
  ClockIcon
}

const getIcon = (iconName) => {
  return iconComponents[iconName] || ClockIcon
}
</script>