<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">核准未撥</h1>
        <p class="text-gray-600 mt-2">顯示已核准但尚未撥款的案件</p>
      </div>
    </div>

    <!-- 資料表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <DataTable
        title="核准未撥案件"
        :columns="columns"
        :data="filteredCases"
        :loading="loading"
        :error="error"
        search-placeholder="搜尋案件編號、客戶姓名、手機..."
        :search-query="searchQuery"
        @search="handleSearch"
        @refresh="loadCases"
        :current-page="currentPage"
        :items-per-page="itemsPerPage"
        @page-change="handlePageChange"
        @page-size-change="handlePageSizeChange"
      >
        <!-- 篩選器插槽 -->
        <template #filters>
          <div class="flex items-center space-x-3">
            <!-- 核准時間篩選 -->
            <div class="relative">
              <select
                v-model="approvalPeriodFilter"
                @change="loadCases"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部時間</option>
                <option value="today">今日核准</option>
                <option value="this_week">本週核准</option>
                <option value="this_month">本月核准</option>
              </select>
            </div>

            <!-- 優先級篩選 -->
            <div class="relative">
              <select
                v-model="priorityFilter"
                @change="loadCases"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部優先級</option>
                <option value="urgent">緊急</option>
                <option value="high">高</option>
                <option value="normal">一般</option>
              </select>
            </div>
          </div>
        </template>

        <!-- 核准金額欄位 -->
        <template #cell-approved_amount="{ item }">
          <span class="font-medium text-green-600">
            NT$ {{ formatAmount(item.approved_amount) }}
          </span>
        </template>

        <!-- 優先級欄位 -->
        <template #cell-priority="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getPriorityClass(item.priority)">
            {{ getPriorityText(item.priority) }}
          </span>
        </template>

        <!-- 狀態欄位 -->
        <template #cell-status="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
            待撥款
          </span>
        </template>

        <!-- 操作欄位 -->
        <template #cell-actions="{ item }">
          <div class="flex items-center space-x-2">
            <button
              @click="processDisburse(item)"
              class="text-green-600 hover:text-green-800 text-sm font-medium"
            >
              撥款
            </button>
            <button
              @click="viewDetails(item)"
              class="text-blue-600 hover:text-blue-800 text-sm font-medium"
            >
              詳情
            </button>
          </div>
        </template>
      </DataTable>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

definePageMeta({
  middleware: 'auth',
  title: '核准未撥'
})

// 響應式數據
const cases = ref([])
const loading = ref(false)
const error = ref('')
const searchQuery = ref('')
const approvalPeriodFilter = ref('')
const priorityFilter = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(10)

// API 組合
const { get, put } = useApi()

// 表格欄位定義
const columns = [
  { key: 'id', title: '案件編號', sortable: true, width: '100px' },
  { key: 'approved_at', title: '核准時間', sortable: true, width: '130px' },
  { key: 'customer_name', title: '客戶姓名', sortable: true, width: '120px' },
  { key: 'phone', title: '手機號碼', sortable: true, width: '130px' },
  { key: 'approved_amount', title: '核准金額', sortable: true, width: '120px' },
  { key: 'installment_count', title: '分期數', width: '80px' },
  { key: 'priority', title: '優先級', width: '100px' },
  { key: 'status', title: '狀態', width: '100px' },
  { key: 'actions', title: '操作', width: '120px' }
]

// 載入案件資料 - 只顯示核准但未撥款的案件
const loadCases = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      status: 'approved_pending', // 核准但未撥款
      page: currentPage.value,
      per_page: itemsPerPage.value
    }

    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    if (approvalPeriodFilter.value) {
      params.approval_period = approvalPeriodFilter.value
    }

    if (priorityFilter.value) {
      params.priority = priorityFilter.value
    }

    const { data, error: apiError } = await get('/submissions', params)

    if (apiError) {
      error.value = apiError
    } else {
      cases.value = data?.data || []
    }
  } catch (err) {
    error.value = '載入資料失敗'
    console.error('載入案件失敗:', err)
  } finally {
    loading.value = false
  }
}

// 篩選後的案件資料
const filteredCases = computed(() => {
  return cases.value
})

// 搜尋處理
const handleSearch = (query) => {
  searchQuery.value = query
  currentPage.value = 1
  loadCases()
}

// 分頁處理
const handlePageChange = (page) => {
  currentPage.value = page
  loadCases()
}

const handlePageSizeChange = (size) => {
  itemsPerPage.value = size
  currentPage.value = 1
  loadCases()
}

// 格式化金額
const formatAmount = (amount) => {
  if (!amount) return '0'
  return new Intl.NumberFormat('zh-TW').format(amount)
}

// 優先級樣式
const getPriorityClass = (priority) => {
  const priorityClasses = {
    'urgent': 'bg-red-100 text-red-800',
    'high': 'bg-orange-100 text-orange-800',
    'normal': 'bg-green-100 text-green-800'
  }
  return priorityClasses[priority] || 'bg-gray-100 text-gray-800'
}

// 優先級文字
const getPriorityText = (priority) => {
  const priorityTexts = {
    'urgent': '緊急',
    'high': '高',
    'normal': '一般'
  }
  return priorityTexts[priority] || '未知'
}

// 處理撥款
const processDisburse = async (caseItem) => {
  if (confirm(`確定要處理案件 ${caseItem.id} 的撥款嗎？`)) {
    try {
      const { error } = await put(`/submissions/${caseItem.id}/disburse`)
      if (!error) {
        loadCases() // 重新載入資料
      }
    } catch (err) {
      console.error('處理撥款失敗:', err)
    }
  }
}

// 查看詳情
const viewDetails = (caseItem) => {
  navigateTo(`/submissions/${caseItem.id}/details`)
}

// 組件掛載時載入資料
onMounted(() => {
  loadCases()
})
</script>