<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 ">銀行交涉紀錄</h1>
        <p class="text-gray-600  mt-2">
          僅限經銷商/公司高層查看的敏感金融交涉記錄
        </p>
      </div>
      
      <div class="flex space-x-3">
        <button
          class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200 flex items-center space-x-2"
        >
          <PlusIcon class="w-5 h-5" />
          <span>新增記錄</span>
        </button>
      </div>
    </div>

    <!-- 警告提示 -->
    <div class="bg-yellow-50 rounded-lg p-4">
      <div class="flex items-start space-x-3">
        <ExclamationTriangleIcon class="w-6 h-6 text-yellow-600 flex-shrink-0 mt-0.5" />
        <div>
          <h3 class="text-sm font-medium text-yellow-800">機密資料</h3>
          <p class="text-sm text-yellow-700 mt-1">
            此頁面包含敏感的銀行交涉資訊，請妥善保管，不得外洩或與非授權人員分享。
          </p>
        </div>
      </div>
    </div>

    <!-- 統計卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <StatsCard
        title="總交涉次數"
        :value="bankStats.totalNegotiations"
        description="累計交涉記錄"
        icon="BuildingLibraryIcon"
        iconColor="red"
        :trend="8.2"
      />
      
      <StatsCard
        title="成功率"
        :value="bankStats.successRate"
        format="percentage"
        description="交涉成功比例"
        icon="CheckCircleIcon"
        iconColor="green"
        :trend="12.5"
        :progress="78"
      />
      
      <StatsCard
        title="本月交涉"
        :value="bankStats.thisMonth"
        description="本月進行次數"
        icon="ClockIcon"
        iconColor="blue"
        :trend="5.3"
      />
      
      <StatsCard
        title="待處理案件"
        :value="bankStats.pending"
        description="等待回覆的案件"
        icon="ExclamationTriangleIcon"
        iconColor="yellow"
        :trend="-15.2"
      />
    </div>

    <!-- 交涉記錄列表 -->
    <div class="bg-white rounded-xl shadow-sm">
      <div class="p-6">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-gray-900">交涉記錄</h2>
          
          <div class="flex items-center space-x-4">
            <!-- 搜尋框 -->
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="搜尋銀行或案件..."
                class="pl-10 pr-4 py-2 border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-red-500"
              />
              <MagnifyingGlassIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
            
            <!-- 狀態篩選 -->
            <select
              v-model="statusFilter"
              class="px-4 py-2 border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500"
            >
              <option value="">所有狀態</option>
              <option value="pending">處理中</option>
              <option value="success">成功</option>
              <option value="failed">失敗</option>
            </select>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                銀行資訊
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">
                案件編號
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">
                交涉內容
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">
                狀態
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">
                交涉時間
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">
                負責人
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500  uppercase tracking-wider">
                操作
              </th>
            </tr>
          </thead>
          <tbody class="bg-white  divide-y divide-gray-200 ">
            <tr 
              v-for="record in filteredRecords" 
              :key="record.id"
              class="hover:bg-gray-50 "
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 w-10 h-10">
                    <div class="w-10 h-10 bg-red-100  rounded-full flex items-center justify-center">
                      <BuildingLibraryIcon class="w-6 h-6 text-red-600 " />
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900 ">
                      {{ record.bankName }}
                    </div>
                    <div class="text-sm text-gray-500 ">
                      {{ record.bankBranch }}
                    </div>
                  </div>
                </div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-mono text-gray-900 ">
                  {{ record.caseNumber }}
                </div>
              </td>
              
              <td class="px-6 py-4">
                <div class="text-sm text-gray-900  max-w-xs truncate">
                  {{ record.subject }}
                </div>
                <div class="text-sm text-gray-500 ">
                  金額: {{ formatCurrency(record.amount) }}
                </div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  :class="getStatusClass(record.status)"
                >
                  {{ getStatusText(record.status) }}
                </span>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 ">
                {{ formatDate(record.negotiationDate) }}
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 ">
                {{ record.responsible }}
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button class="text-red-600  hover:text-red-800 ">
                  查看詳情
                </button>
                <button class="text-gray-600  hover:text-gray-800 ">
                  編輯
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { 
  PlusIcon,
  MagnifyingGlassIcon,
  BuildingLibraryIcon,
  CheckCircleIcon,
  ClockIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'

// 明確匯入 StatsCard 組件
import StatsCard from '~/components/StatsCard.vue'

definePageMeta({
  middleware: ['auth', 'role']
})

const authStore = useAuthStore()

// 確保只有經銷商/公司高層可以存取
if (!authStore.hasPermission('all_access')) {
  throw createError({ statusCode: 403, statusMessage: '權限不足' })
}

// 搜尋和篩選
const searchQuery = ref('')
const statusFilter = ref('')

// 銀行交涉統計
const bankStats = ref({
  totalNegotiations: 47,
  successRate: 78,
  thisMonth: 12,
  pending: 5
})

// 模擬銀行交涉記錄
const records = ref([
  {
    id: 1,
    bankName: '台灣銀行',
    bankBranch: '台北分行',
    caseNumber: 'BK-2024-001',
    subject: '企業融資利率調整',
    amount: 50000000,
    status: 'success',
    negotiationDate: new Date('2024-08-07'),
    responsible: '王總'
  },
  {
    id: 2,
    bankName: '第一銀行',
    bankBranch: '高雄分行',
    caseNumber: 'BK-2024-002',
    subject: '信用額度提升申請',
    amount: 30000000,
    status: 'pending',
    negotiationDate: new Date('2024-08-06'),
    responsible: '王總'
  },
  {
    id: 3,
    bankName: '華南銀行',
    bankBranch: '新竹分行',
    caseNumber: 'BK-2024-003',
    subject: '擔保品重新評估',
    amount: 80000000,
    status: 'failed',
    negotiationDate: new Date('2024-08-05'),
    responsible: '王總'
  },
  {
    id: 4,
    bankName: '中國信託',
    bankBranch: '台中分行',
    caseNumber: 'BK-2024-004',
    subject: '貸款展延申請',
    amount: 25000000,
    status: 'success',
    negotiationDate: new Date('2024-08-04'),
    responsible: '王總'
  }
])

// 過濾記錄
const filteredRecords = computed(() => {
  let result = records.value

  // 搜尋過濾
  if (searchQuery.value.trim()) {
    result = result.filter(record =>
      record.bankName.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      record.caseNumber.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      record.subject.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
  }

  // 狀態過濾
  if (statusFilter.value) {
    result = result.filter(record => record.status === statusFilter.value)
  }

  return result
})

// 狀態樣式
const getStatusClass = (status) => {
  const classes = {
    'success': 'bg-green-100 text-green-800',
    'failed': 'bg-red-100 text-red-800',
    'pending': 'bg-yellow-100 text-yellow-800'
  }
  return classes[status] || classes.pending
}

// 狀態文字
const getStatusText = (status) => {
  const texts = {
    'success': '成功',
    'failed': '失敗',
    'pending': '處理中'
  }
  return texts[status] || '未知'
}

// 金額格式化
const formatCurrency = (amount) => {
  return new Intl.NumberFormat('zh-TW', {
    style: 'currency',
    currency: 'TWD',
    minimumFractionDigits: 0
  }).format(amount)
}

// 日期格式化
const formatDate = (date) => {
  return new Date(date).toLocaleDateString('zh-TW', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

// 設定頁面標題
useHead({
  title: '銀行交涉紀錄 - 金融管理系統'
})
</script>