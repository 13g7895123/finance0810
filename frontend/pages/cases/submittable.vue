<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">可送件案件</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">
          總共 {{ filteredCases.length }} 筆可送件案件
        </p>
      </div>
      <button
        @click="batchSubmit"
        :disabled="selectedCases.length === 0"
        class="px-4 py-2 bg-green-500 hover:bg-green-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-lg flex items-center space-x-2 transition-colors duration-200"
      >
        <PaperAirplaneIcon class="w-5 h-5" />
        <span>批次送件 ({{ selectedCases.length }})</span>
      </button>
    </div>

    <!-- 篩選和搜尋 -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- 搜尋框 -->
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="搜尋客戶姓名或手機號碼..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

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
      <!-- 表格標題和全選 -->
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <input
            type="checkbox"
            :checked="allSelected"
            @change="toggleAllSelection"
            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">可送件案件列表</h3>
        </div>
        <div class="text-sm text-gray-500 dark:text-gray-400">
          已選擇 {{ selectedCases.length }} 筆
        </div>
      </div>

      <!-- 表格內容 -->
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-12">
                選擇
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                案件編號
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                客戶資訊
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                貸款類型
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                申請金額
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                建議銀行
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                承辦業務
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                資料完整度
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                操作
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="caseItem in paginatedCases" :key="caseItem.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap">
                <input
                  type="checkbox"
                  :checked="selectedCases.includes(caseItem.id)"
                  @change="toggleCaseSelection(caseItem.id)"
                  class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                {{ caseItem.id }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ caseItem.customerName }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ caseItem.phone }}</div>
                <div class="text-xs text-gray-400 dark:text-gray-500">{{ caseItem.region }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300 rounded-full">
                  {{ caseItem.loanType }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                {{ formatCurrency(caseItem.requestedAmount) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300 rounded-full">
                  {{ caseItem.recommendedBank }}
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
                        :class="getCompletenessClass(caseItem.completeness)"
                        :style="{ width: `${caseItem.completeness}%` }"
                      ></div>
                    </div>
                  </div>
                  <span class="ml-2 text-sm font-medium text-gray-900 dark:text-white">{{ caseItem.completeness }}%</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                <button
                  @click="quickSubmit(caseItem)"
                  class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                >
                  送件
                </button>
                <button
                  @click="viewCase(caseItem)"
                  class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
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
import { 
  PaperAirplaneIcon, 
  MagnifyingGlassIcon 
} from '@heroicons/vue/24/outline'

definePageMeta({
  middleware: 'auth'
})

const authStore = useAuthStore()

// 響應式數據
const searchQuery = ref('')
const selectedBank = ref('')
const selectedLoanType = ref('')
const selectedSales = ref('')
const currentPage = ref(1)
const itemsPerPage = 10
const selectedCases = ref([])

// 業務人員列表
const salesStaff = ref([
  { id: 1, name: '業務員李小姐', avatar: 'https://ui-avatars.com/api/?name=李小姐&background=f97316&color=fff' },
  { id: 2, name: '業務員陳先生', avatar: 'https://ui-avatars.com/api/?name=陳先生&background=8b5cf6&color=fff' },
  { id: 3, name: '業務員王小姐', avatar: 'https://ui-avatars.com/api/?name=王小姐&background=22c55e&color=fff' }
])

// 模擬可送件案件數據
const allCases = ref([
  {
    id: 'C101',
    customerName: '張志明',
    phone: '0912345678',
    region: '台北',
    loanType: '汽車貸款',
    requestedAmount: 500000,
    recommendedBank: '中租',
    assignedSales: salesStaff.value[0],
    completeness: 95,
    createdAt: new Date('2024-08-07T14:30:00')
  },
  {
    id: 'C102',
    customerName: '李美玲',
    phone: '0923456789',
    region: '新北',
    loanType: '機車貸款',
    requestedAmount: 150000,
    recommendedBank: '和潤',
    assignedSales: salesStaff.value[1],
    completeness: 100,
    createdAt: new Date('2024-08-07T15:45:00')
  },
  {
    id: 'C103',
    customerName: '王建國',
    phone: '0934567890',
    region: '桃園',
    loanType: '手機貸款',
    requestedAmount: 80000,
    recommendedBank: '台新',
    assignedSales: salesStaff.value[0],
    completeness: 90,
    createdAt: new Date('2024-08-07T16:20:00')
  },
  {
    id: 'C104',
    customerName: '陳小婷',
    phone: '0945678901',
    region: '台中',
    loanType: '汽車貸款',
    requestedAmount: 800000,
    recommendedBank: '裕融',
    assignedSales: salesStaff.value[2],
    completeness: 85,
    createdAt: new Date('2024-08-08T09:10:00')
  },
  {
    id: 'C105',
    customerName: '劉志華',
    phone: '0956789012',
    region: '高雄',
    loanType: '機車貸款',
    requestedAmount: 120000,
    recommendedBank: '中租',
    assignedSales: salesStaff.value[1],
    completeness: 100,
    createdAt: new Date('2024-08-08T10:30:00')
  }
])

// 計算過濾後的案件
const filteredCases = computed(() => {
  let cases = allCases.value

  // 權限過濾 - 業務人員只能看到自己的案件
  if (authStore.isSales && !authStore.hasPermission('all_access')) {
    cases = cases.filter(c => c.assignedSales.name === authStore.user?.name)
  }

  // 搜尋過濾
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    cases = cases.filter(c => 
      c.customerName.toLowerCase().includes(query) ||
      c.phone.includes(query)
    )
  }

  // 銀行過濾
  if (selectedBank.value) {
    cases = cases.filter(c => c.recommendedBank === selectedBank.value)
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

// 全選狀態
const allSelected = computed(() => {
  return paginatedCases.value.length > 0 && paginatedCases.value.every(c => selectedCases.value.includes(c.id))
})

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

// 選擇方法
const toggleCaseSelection = (caseId) => {
  const index = selectedCases.value.indexOf(caseId)
  if (index > -1) {
    selectedCases.value.splice(index, 1)
  } else {
    selectedCases.value.push(caseId)
  }
}

const toggleAllSelection = () => {
  if (allSelected.value) {
    // 取消選擇當前頁面所有項目
    paginatedCases.value.forEach(c => {
      const index = selectedCases.value.indexOf(c.id)
      if (index > -1) {
        selectedCases.value.splice(index, 1)
      }
    })
  } else {
    // 選擇當前頁面所有項目
    paginatedCases.value.forEach(c => {
      if (!selectedCases.value.includes(c.id)) {
        selectedCases.value.push(c.id)
      }
    })
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

const getCompletenessClass = (completeness) => {
  if (completeness >= 95) return 'bg-green-500'
  if (completeness >= 80) return 'bg-blue-500'
  if (completeness >= 60) return 'bg-yellow-500'
  return 'bg-red-500'
}

// 操作方法
const quickSubmit = (caseItem) => {
  console.log('快速送件:', caseItem)
  // 送件邏輯
}

const batchSubmit = () => {
  console.log('批次送件:', selectedCases.value)
  // 批次送件邏輯
}

const viewCase = (caseItem) => {
  console.log('查看案件詳情:', caseItem)
  // 查看詳情邏輯
}

// 頁面標題
useHead({
  title: '可送件案件 - 金融管理系統'
})
</script>