<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">進行中案件</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">
          總共 {{ filteredCases.length }} 筆進行中案件
        </p>
      </div>
    </div>

    <!-- 進度統計卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ statusStats.pending }}</div>
        <div class="text-sm text-gray-600 dark:text-gray-400">銀行審核中</div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ statusStats.additional }}</div>
        <div class="text-sm text-gray-600 dark:text-gray-400">補件中</div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ statusStats.approved }}</div>
        <div class="text-sm text-gray-600 dark:text-gray-400">已核准待撥款</div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ statusStats.negotiating }}</div>
        <div class="text-sm text-gray-600 dark:text-gray-400">協商中</div>
      </div>
    </div>

    <!-- 篩選和搜尋 -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <!-- 搜尋框 -->
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="搜尋客戶姓名或案件編號..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <!-- 案件狀態篩選 -->
        <select
          v-model="selectedStatus"
          class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">所有狀態</option>
          <option value="銀行審核中">銀行審核中</option>
          <option value="補件中">補件中</option>
          <option value="已核准待撥款">已核准待撥款</option>
          <option value="協商中">協商中</option>
        </select>

        <!-- 銀行篩選 -->
        <select
          v-model="selectedBank"
          class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">所有銀行</option>
          <option value="中租">中租</option>
          <option value="和潤">和潤</option>
          <option value="裕融">裕融</option>
          <option value="台新">台新</option>
        </select>

        <!-- 貸款類型篩選 -->
        <select
          v-model="selectedLoanType"
          class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">所有類型</option>
          <option value="汽車貸款">汽車貸款</option>
          <option value="機車貸款">機車貸款</option>
          <option value="手機貸款">手機貸款</option>
        </select>

        <!-- 承辦業務篩選 -->
        <select
          v-model="selectedSales"
          class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">所有業務</option>
          <option v-for="sales in salesStaff" :key="sales.id" :value="sales.name">
            {{ sales.name }}
          </option>
        </select>
      </div>
    </div>

    <!-- 案件列表 -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
      <!-- 表格標題 -->
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">進行中案件列表</h3>
      </div>

      <!-- 表格內容 -->
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                案件編號
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                客戶資訊
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                貸款資訊
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                銀行/核准金額
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                案件狀態
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                承辦業務
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                進度
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                預計完成
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                操作
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="caseItem in paginatedCases" :key="caseItem.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                {{ caseItem.id }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ caseItem.customerName }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ caseItem.phone }}</div>
                <div class="text-xs text-gray-400 dark:text-gray-500">{{ caseItem.region }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ caseItem.loanType }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">申請：{{ formatCurrency(caseItem.requestedAmount) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ caseItem.bank }}</div>
                <div v-if="caseItem.approvedAmount" class="text-sm text-green-600 dark:text-green-400">
                  核准：{{ formatCurrency(caseItem.approvedAmount) }}
                </div>
                <div v-else class="text-sm text-gray-500 dark:text-gray-400">審核中</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  class="px-2 py-1 text-xs font-medium rounded-full"
                  :class="getCaseStatusClass(caseItem.status)"
                >
                  {{ caseItem.status }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <img :src="caseItem.assignedSales.avatar" :alt="caseItem.assignedSales.name" class="w-8 h-8 rounded-full mr-2" />
                  <span class="text-sm font-medium text-gray-900 dark:text-white">{{ caseItem.assignedSales.name }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-1">
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                      <div 
                        class="h-2 rounded-full transition-all duration-500"
                        :class="getProgressClass(caseItem.progress)"
                        :style="{ width: `${caseItem.progress}%` }"
                      ></div>
                    </div>
                  </div>
                  <span class="ml-2 text-sm font-medium text-gray-900 dark:text-white">{{ caseItem.progress }}%</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                {{ formatDate(caseItem.expectedCompletion) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                <button
                  @click="updateCase(caseItem)"
                  class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                >
                  更新
                </button>
                <button
                  @click="viewCase(caseItem)"
                  class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                >
                  詳情
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 分頁 -->
      <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div class="text-sm text-gray-500 dark:text-gray-400">
          顯示 {{ startIndex + 1 }} 到 {{ endIndex }} 筆，共 {{ filteredCases.length }} 筆
        </div>
        <div class="flex space-x-2">
          <button
            @click="previousPage"
            :disabled="currentPage === 1"
            class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            上一頁
          </button>
          <button
            @click="nextPage"
            :disabled="currentPage === totalPages"
            class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            下一頁
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

definePageMeta({
  middleware: 'auth'
})

const authStore = useAuthStore()

// 響應式數據
const searchQuery = ref('')
const selectedStatus = ref('')
const selectedBank = ref('')
const selectedLoanType = ref('')
const selectedSales = ref('')
const currentPage = ref(1)
const itemsPerPage = 10

// 業務人員列表
const salesStaff = ref([
  { id: 1, name: '業務員李小姐', avatar: 'https://ui-avatars.com/api/?name=李小姐&background=f97316&color=fff' },
  { id: 2, name: '業務員陳先生', avatar: 'https://ui-avatars.com/api/?name=陳先生&background=8b5cf6&color=fff' },
  { id: 3, name: '業務員王小姐', avatar: 'https://ui-avatars.com/api/?name=王小姐&background=22c55e&color=fff' }
])

// 模擬進行中案件數據
const allCases = ref([
  {
    id: 'C201',
    customerName: '林志明',
    phone: '0912345678',
    region: '台北',
    loanType: '汽車貸款',
    requestedAmount: 600000,
    bank: '中租',
    approvedAmount: 550000,
    status: '已核准待撥款',
    assignedSales: salesStaff.value[0],
    progress: 85,
    submittedAt: new Date('2024-08-05T14:30:00'),
    expectedCompletion: new Date('2024-08-12T17:00:00')
  },
  {
    id: 'C202',
    customerName: '王美華',
    phone: '0923456789',
    region: '新北',
    loanType: '機車貸款',
    requestedAmount: 180000,
    bank: '和潤',
    approvedAmount: null,
    status: '銀行審核中',
    assignedSales: salesStaff.value[1],
    progress: 45,
    submittedAt: new Date('2024-08-06T09:15:00'),
    expectedCompletion: new Date('2024-08-15T17:00:00')
  },
  {
    id: 'C203',
    customerName: '張建國',
    phone: '0934567890',
    region: '桃園',
    loanType: '手機貸款',
    requestedAmount: 90000,
    bank: '台新',
    approvedAmount: null,
    status: '補件中',
    assignedSales: salesStaff.value[0],
    progress: 60,
    submittedAt: new Date('2024-08-06T16:20:00'),
    expectedCompletion: new Date('2024-08-14T17:00:00')
  },
  {
    id: 'C204',
    customerName: '陳淑惠',
    phone: '0945678901',
    region: '台中',
    loanType: '汽車貸款',
    requestedAmount: 750000,
    bank: '裕融',
    approvedAmount: 680000,
    status: '協商中',
    assignedSales: salesStaff.value[2],
    progress: 70,
    submittedAt: new Date('2024-08-07T11:45:00'),
    expectedCompletion: new Date('2024-08-16T17:00:00')
  }
])

// 狀態統計
const statusStats = computed(() => {
  const stats = {
    pending: 0,
    additional: 0,
    approved: 0,
    negotiating: 0
  }
  
  filteredCases.value.forEach(c => {
    switch (c.status) {
      case '銀行審核中':
        stats.pending++
        break
      case '補件中':
        stats.additional++
        break
      case '已核准待撥款':
        stats.approved++
        break
      case '協商中':
        stats.negotiating++
        break
    }
  })
  
  return stats
})

// 計算過濾後的案件
const filteredCases = computed(() => {
  let cases = allCases.value

  // 權限過濾
  if (authStore.isSales && !authStore.hasPermission('all_access')) {
    cases = cases.filter(c => c.assignedSales.name === authStore.user?.name)
  }

  // 搜尋過濾
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    cases = cases.filter(c => 
      c.customerName.toLowerCase().includes(query) ||
      c.phone.includes(query) ||
      c.id.toLowerCase().includes(query)
    )
  }

  // 狀態過濾
  if (selectedStatus.value) {
    cases = cases.filter(c => c.status === selectedStatus.value)
  }

  // 銀行過濾
  if (selectedBank.value) {
    cases = cases.filter(c => c.bank === selectedBank.value)
  }

  // 貸款類型過濾
  if (selectedLoanType.value) {
    cases = cases.filter(c => c.loanType === selectedLoanType.value)
  }

  // 業務過濾
  if (selectedSales.value) {
    cases = cases.filter(c => c.assignedSales.name === selectedSales.value)
  }

  return cases
})

// 分頁計算
const totalPages = computed(() => Math.ceil(filteredCases.value.length / itemsPerPage))
const startIndex = computed(() => (currentPage.value - 1) * itemsPerPage)
const endIndex = computed(() => Math.min(startIndex.value + itemsPerPage, filteredCases.value.length))
const paginatedCases = computed(() => filteredCases.value.slice(startIndex.value, endIndex.value))

// 分頁方法
const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
  }
}

const previousPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
  }
}

// 工具方法
const formatCurrency = (amount) => {
  return new Intl.NumberFormat('zh-TW', {
    style: 'currency',
    currency: 'TWD',
    minimumFractionDigits: 0
  }).format(amount)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('zh-TW', {
    month: '2-digit',
    day: '2-digit'
  })
}

const getCaseStatusClass = (status) => {
  const statusMap = {
    '銀行審核中': 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
    '補件中': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300',
    '已核准待撥款': 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300',
    '協商中': 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300'
  }
  return statusMap[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
}

const getProgressClass = (progress) => {
  if (progress >= 80) return 'bg-green-500'
  if (progress >= 60) return 'bg-blue-500'
  if (progress >= 40) return 'bg-yellow-500'
  return 'bg-red-500'
}

// 操作方法
const updateCase = (caseItem) => {
  console.log('更新案件:', caseItem)
}

const viewCase = (caseItem) => {
  console.log('查看案件詳情:', caseItem)
}

// 頁面標題
useHead({
  title: '進行中案件 - 金融管理系統'
})
</script>