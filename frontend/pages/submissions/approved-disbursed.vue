<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">核准撥款</h1>
        <p class="text-gray-600 mt-2">顯示已核准且已撥款的案件</p>
      </div>
    </div>

    <!-- 資料表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <DataTable
        title="核准撥款案件"
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
            <!-- 金額範圍篩選 -->
            <div class="relative">
              <select
                v-model="amountRangeFilter"
                @change="loadCases"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部金額</option>
                <option value="0-100000">低於10萬</option>
                <option value="100000-500000">10-50萬</option>
                <option value="500000-1000000">50-100萬</option>
                <option value="1000000+">高於100萬</option>
              </select>
            </div>

            <!-- 撥款時間篩選 -->
            <div class="relative">
              <select
                v-model="disbursementPeriodFilter"
                @change="loadCases"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部時間</option>
                <option value="today">今日</option>
                <option value="this_week">本週</option>
                <option value="this_month">本月</option>
                <option value="last_month">上月</option>
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

        <!-- 撥款金額欄位 -->
        <template #cell-disbursed_amount="{ item }">
          <span class="font-medium text-blue-600">
            NT$ {{ formatAmount(item.disbursed_amount) }}
          </span>
        </template>

        <!-- 狀態欄位 -->
        <template #cell-status="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
            已撥款
          </span>
        </template>

        <!-- 操作欄位 -->
        <template #cell-actions="{ item }">
          <div class="flex items-center space-x-2">
            <button
              @click="viewDetails(item)"
              class="text-blue-600 hover:text-blue-800 text-sm font-medium"
            >
              詳情
            </button>
            <button
              @click="downloadContract(item)"
              class="text-green-600 hover:text-green-800 text-sm font-medium"
            >
              合約
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
  title: '核准撥款'
})

// 響應式數據
const cases = ref([])
const loading = ref(false)
const error = ref('')
const searchQuery = ref('')
const amountRangeFilter = ref('')
const disbursementPeriodFilter = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(10)

// API 組合
const { get } = useApi()

// 表格欄位定義
const columns = [
  { key: 'id', title: '案件編號', sortable: true, width: '100px' },
  { key: 'disbursed_at', title: '撥款時間', sortable: true, width: '130px' },
  { key: 'customer_name', title: '客戶姓名', sortable: true, width: '120px' },
  { key: 'phone', title: '手機號碼', sortable: true, width: '130px' },
  { key: 'approved_amount', title: '核准金額', sortable: true, width: '120px' },
  { key: 'disbursed_amount', title: '撥款金額', sortable: true, width: '120px' },
  { key: 'installment_count', title: '分期數', width: '80px' },
  { key: 'status', title: '狀態', width: '100px' },
  { key: 'actions', title: '操作', width: '120px' }
]

// 載入案件資料 - 只顯示核准且已撥款的案件
const loadCases = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      status: 'approved_disbursed', // 核准且已撥款
      page: currentPage.value,
      per_page: itemsPerPage.value
    }

    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    if (amountRangeFilter.value) {
      params.amount_range = amountRangeFilter.value
    }

    if (disbursementPeriodFilter.value) {
      params.disbursement_period = disbursementPeriodFilter.value
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

// 查看詳情
const viewDetails = (caseItem) => {
  navigateTo(`/submissions/${caseItem.id}/details`)
}

// 下載合約
const downloadContract = async (caseItem) => {
  try {
    // 下載合約文件的程式碼
    const response = await get(`/submissions/${caseItem.id}/contract`)
    // 處理文件下載邏輯
  } catch (err) {
    console.error('下載合約失敗:', err)
  }
}

// 組件掛載時載入資料
onMounted(() => {
  loadCases()
})
</script>