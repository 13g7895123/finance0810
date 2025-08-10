<script setup>
import { 
  DocumentTextIcon,
  ClockIcon,
  CheckCircleIcon,
  UserGroupIcon,
  CurrencyDollarIcon,
  ChartBarIcon,
  ChatBubbleLeftRightIcon,
  ExclamationTriangleIcon,
  PlusIcon,
  PaperAirplaneIcon,
  UsersIcon
} from '@heroicons/vue/24/outline'

// 明確匯入 StatsCard 組件
import StatsCard from '~/components/StatsCard.vue'

definePageMeta({
  middleware: 'auth'
})

const authStore = useAuthStore()

// 模擬統計數據 - 根據參考網站結構
const statsData = ref({
  pendingCases: 24,
  submittableCases: 12,
  activeCases: 38,
  completedCases: 156,
  negotiatedCustomers: 8,
  // 每日網站績效數據
  websitePerformance: {
    bearLoan: {
      name: '熊好貸',
      todayApplications: 15,
      monthlyApplications: 342,
      conversionRate: 68.5
    },
    websiteA: {
      name: '網站A',
      todayApplications: 8,
      monthlyApplications: 198,
      conversionRate: 72.1
    },
    websiteB: {
      name: '網站B',
      todayApplications: 6,
      monthlyApplications: 145,
      conversionRate: 65.3
    }
  },
  // 統計數據
  satisfaction: 92,
  monthlyRevenue: 2450000,
  approvalRate: 78.5,
  disbursementAmount: 18750000,
  todayMessages: 47,
  botInteractions: 128,
  // 新增缺少的數據
  newClients: 42,
  conversionRate: 68.3
})

// 最近活動數據
const recentActivities = ref([
  {
    id: 1,
    description: '新客戶「台北資訊公司」已完成合約簽署',
    timestamp: new Date(Date.now() - 1000 * 60 * 30), // 30分鐘前
    icon: CheckCircleIcon,
    colorClass: 'bg-green-100 text-green-600  '
  },
  {
    id: 2,
    description: '業務員李小姐更新了客戶「ABC企業」的案件狀態',
    timestamp: new Date(Date.now() - 1000 * 60 * 60 * 2), // 2小時前
    icon: DocumentTextIcon,
    colorClass: 'bg-blue-100 text-blue-600  '
  },
  {
    id: 3,
    description: 'LINE BOT 自動處理了15個客戶諮詢',
    timestamp: new Date(Date.now() - 1000 * 60 * 60 * 4), // 4小時前
    icon: ChatBubbleLeftRightIcon,
    colorClass: 'bg-green-100 text-green-600  '
  },
  {
    id: 4,
    description: '系統檢測到異常登入嘗試，已自動封鎖',
    timestamp: new Date(Date.now() - 1000 * 60 * 60 * 6), // 6小時前
    icon: ExclamationTriangleIcon,
    colorClass: 'bg-red-100 text-red-600  '
  },
  {
    id: 5,
    description: '新增了3個待處理案件到系統中',
    timestamp: new Date(Date.now() - 1000 * 60 * 60 * 8), // 8小時前
    icon: PlusIcon,
    colorClass: 'bg-yellow-100 text-yellow-600  '
  }
])

// 格式化時間
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

// 設定頁面標題
useHead({
  title: '儀表板 - 金融管理系統'
})
</script>

<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">儀表板</h1>
    </div>

    <!-- 主要統計卡片網格 - 對應參考網站的4個主要指標 -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <!-- 待處理案件 -->
      <StatsCard
        title="待處理案件"
        subtitle="等待處理"
        :value="statsData.pendingCases"
        description="等待處理的新案件"
        icon="DocumentTextIcon"
        iconColor="yellow"
        :trend="12.5"
        link="/cases/pending"
      />

      <!-- 可送件案件 -->
      <StatsCard
        title="可送件案件"
        subtitle="準備送件"
        :value="statsData.submittableCases"
        description="可以送件的案件"
        icon="PaperAirplaneIcon"
        iconColor="blue"
        :trend="8.3"
        link="/cases/submittable"
      />

      <!-- 協商客戶 -->
      <StatsCard
        title="協商客戶"
        subtitle="銀行協商中"
        :value="statsData.negotiatedCustomers"
        description="銀行協商中的客戶"
        icon="UsersIcon"
        iconColor="purple"
        :trend="-2.1"
        link="/cases/negotiated"
      />

      <!-- 核准率 -->
      <StatsCard
        title="核准率"
        subtitle="本月平均"
        :value="statsData.approvalRate"
        format="percentage"
        description="案件核准率"
        icon="CheckCircleIcon"
        iconColor="green"
        :trend="4.2"
        :progress="78"
      />
    </div>

    <!-- 每日網站績效 -->
    <div class="mb-8">
      <h3 class="text-xl font-semibold text-gray-900  mb-4">每日網站績效</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- 熊好貸 -->
        <div class="card-modern p-6 fade-in">
          <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-medium text-gray-900 ">{{ statsData.websitePerformance.bearLoan.name }}</h4>
            <div class="px-3 py-1 bg-green-100  text-green-800 dark:text-green-300 text-sm font-medium rounded-full">
              主力網站
            </div>
          </div>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">今日進件</span>
              <span class="font-semibold text-gray-900 ">{{ statsData.websitePerformance.bearLoan.todayApplications }} 件</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">本月進件</span>
              <span class="font-semibold text-gray-900 ">{{ statsData.websitePerformance.bearLoan.monthlyApplications }} 件</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">轉換率</span>
              <span class="font-semibold text-green-600 ">{{ statsData.websitePerformance.bearLoan.conversionRate }}%</span>
            </div>
          </div>
        </div>

        <!-- 網站A -->
        <div class="card-modern p-6 fade-in">
          <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-medium text-gray-900 ">{{ statsData.websitePerformance.websiteA.name }}</h4>
            <div class="px-3 py-1 bg-blue-100  text-blue-800 dark:text-blue-300 text-sm font-medium rounded-full">
              次要網站
            </div>
          </div>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">今日進件</span>
              <span class="font-semibold text-gray-900 ">{{ statsData.websitePerformance.websiteA.todayApplications }} 件</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">本月進件</span>
              <span class="font-semibold text-gray-900 ">{{ statsData.websitePerformance.websiteA.monthlyApplications }} 件</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">轉換率</span>
              <span class="font-semibold text-blue-600 ">{{ statsData.websitePerformance.websiteA.conversionRate }}%</span>
            </div>
          </div>
        </div>

        <!-- 網站B -->
        <div class="card-modern p-6 fade-in">
          <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-medium text-gray-900 ">{{ statsData.websitePerformance.websiteB.name }}</h4>
            <div class="px-3 py-1 bg-gray-100  text-gray-800  text-sm font-medium rounded-full">
              次要網站
            </div>
          </div>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">今日進件</span>
              <span class="font-semibold text-gray-900 ">{{ statsData.websitePerformance.websiteB.todayApplications }} 件</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">本月進件</span>
              <span class="font-semibold text-gray-900 ">{{ statsData.websitePerformance.websiteB.monthlyApplications }} 件</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">轉換率</span>
              <span class="font-semibold text-gray-600 ">{{ statsData.websitePerformance.websiteB.conversionRate }}%</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 業績統計 (僅非業務人員可見) -->
    <div v-if="!authStore.isSales" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <!-- 月度營收 -->
      <StatsCard
        title="月度營收"
        subtitle="本月總額"
        :value="statsData.monthlyRevenue"
        format="currency"
        description="比上月成長 15.3%"
        icon="CurrencyDollarIcon"
        iconColor="green"
        :trend="15.3"
        link="/reports/sales"
      />

      <!-- 新客戶數量 -->
      <StatsCard
        title="新客戶"
        subtitle="本月新增"
        :value="statsData.newClients"
        description="新開發客戶數量"
        icon="UserGroupIcon"
        iconColor="blue"
        :trend="22.7"
        link="/sales/customers"
      />

      <!-- 轉換率 -->
      <StatsCard
        title="轉換率"
        subtitle="諮詢到成交"
        :value="statsData.conversionRate"
        format="percentage"
        description="諮詢客戶成交比例"
        icon="ChartBarIcon"
        iconColor="indigo"
        :trend="-1.8"
        :progress="68"
      />
    </div>

    <!-- 聊天室活躍度 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
      <StatsCard
        title="今日訊息"
        subtitle="聊天室活躍度"
        :value="statsData.todayMessages"
        description="今天收發的訊息數量"
        icon="ChatBubbleLeftRightIcon"
        iconColor="pink"
        :trend="45.6"
        link="/chat"
      />

      <StatsCard
        title="LINE BOT 互動"
        subtitle="自動回覆"
        :value="statsData.botInteractions"
        description="BOT 自動處理的對話"
        icon="ChatBubbleLeftRightIcon"
        iconColor="green"
        :trend="12.3"
        :progress="85"
      />
    </div>

    <!-- 快速行動區域 -->
    <div class="card-modern p-6 fade-in">
      <h3 class="text-xl font-semibold text-gray-900  mb-4">快速行動</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <NuxtLink 
          v-if="authStore.hasPermission('customer_management')"
          to="/cases/pending"
          class="flex items-center p-4 bg-gray-50  rounded-lg btn-interactive"
        >
          <div class="p-2 bg-yellow-100  rounded-lg mr-3">
            <DocumentTextIcon class="w-6 h-6 text-yellow-600 " />
          </div>
          <div>
            <div class="font-medium text-gray-900 ">處理案件</div>
            <div class="text-sm text-gray-500 ">查看待處理案件</div>
          </div>
        </NuxtLink>

        <NuxtLink 
          to="/chat"
          class="flex items-center p-4 bg-gray-50  rounded-lg btn-interactive"
        >
          <div class="p-2 bg-blue-100  rounded-lg mr-3">
            <ChatBubbleLeftRightIcon class="w-6 h-6 text-blue-600 " />
          </div>
          <div>
            <div class="font-medium text-gray-900 ">聊天室</div>
            <div class="text-sm text-gray-500 ">查看最新訊息</div>
          </div>
        </NuxtLink>

        <NuxtLink 
          v-if="authStore.hasPermission('reports')"
          to="/reports/sales"
          class="flex items-center p-4 bg-gray-50  rounded-lg btn-interactive"
        >
          <div class="p-2 bg-green-100  rounded-lg mr-3">
            <ChartBarIcon class="w-6 h-6 text-green-600 " />
          </div>
          <div>
            <div class="font-medium text-gray-900 ">查看報表</div>
            <div class="text-sm text-gray-500 ">業績統計分析</div>
          </div>
        </NuxtLink>
      </div>
    </div>

    <!-- 最近活動 (僅經銷商和管理員可見) -->
    <div v-if="authStore.hasPermission('all_access') || authStore.hasPermission('customer_management')" class="card-modern p-6 fade-in">
      <h3 class="text-xl font-semibold text-gray-900  mb-4">最近活動</h3>
      <div class="space-y-4">
        <div v-for="activity in recentActivities" :key="activity.id" class="flex items-start space-x-3">
          <div class="flex-shrink-0">
            <div :class="`p-2 rounded-lg ${activity.colorClass}`">
              <component v-if="activity.icon && typeof activity.icon === 'function'" :is="activity.icon" class="w-5 h-5" />
              <DocumentTextIcon v-else class="w-5 h-5" />
            </div>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm text-gray-900 ">{{ activity.description }}</p>
            <p class="text-xs text-gray-500  mt-1">{{ formatTime(activity.timestamp) }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>