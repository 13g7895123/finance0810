<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">婉拒</h1>
        <p class="text-gray-600 mt-2">顯示被婉拒的案件</p>
      </div>
    </div>

    <!-- 資料表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <DataTable
        title="婉拒案件"
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
            <!-- 婉拒原因篩選 -->
            <div class="relative">
              <select
                v-model="declineReasonFilter"
                @change="loadCases"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部原因</option>
                <option value="insufficient_income">收入不足</option>
                <option value="poor_credit">信用不良</option>
                <option value="incomplete_docs">文件不全</option>
                <option value="policy_violation">違反政策</option>
              </select>
            </div>

            <!-- 婉拒時間篩選 -->
            <div class="relative">
              <select
                v-model="declinePeriodFilter"
                @change="loadCases"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部時間</option>
                <option value="today">今日</option>
                <option value="this_week">本週</option>
                <option value="this_month">本月</option>
              </select>
            </div>
          </div>
        </template>

        <!-- 婉拒原因欄位 -->
        <template #cell-decline_reason="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getDeclineReasonClass(item.decline_reason)">
            {{ getDeclineReasonText(item.decline_reason) }}
          </span>
        </template>

        <!-- 狀態欄位 -->
        <template #cell-status="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
            婉拒
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
              @click="resubmit(item)"
              class="text-green-600 hover:text-green-800 text-sm font-medium"
            >
              重新申請
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
  title: '婉拒'
})

// 響應式數據
const cases = ref([])
const loading = ref(false)
const error = ref('')
const searchQuery = ref('')
const declineReasonFilter = ref('')
const declinePeriodFilter = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(10)

// API 組合
const { get, put } = useApi()

// 表格欄位定義
const columns = [
  { key: 'id', title: '案件編號', sortable: true, width: '100px' },
  { key: 'declined_at', title: '婉拒時間', sortable: true, width: '130px' },
  { key: 'customer_name', title: '客戶姓名', sortable: true, width: '120px' },
  { key: 'phone', title: '手機號碼', sortable: true, width: '130px' },
  { key: 'loan_amount', title: '申請金額', sortable: true, width: '120px' },
  { key: 'decline_reason', title: '婉拒原因', width: '120px' },
  { key: 'reviewer', title: '審核人員', width: '100px' },
  { key: 'status', title: '狀態', width: '100px' },
  { key: 'actions', title: '操作', width: '120px' }
]

// 載入案件資料 - 只顯示婉拒的案件
const loadCases = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      status: 'declined', // 婉拒案件
      page: currentPage.value,
      per_page: itemsPerPage.value
    }

    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    if (declineReasonFilter.value) {
      params.decline_reason = declineReasonFilter.value
    }

    if (declinePeriodFilter.value) {
      params.decline_period = declinePeriodFilter.value
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

// 婉拒原因樣式
const getDeclineReasonClass = (reason) => {
  const reasonClasses = {
    'insufficient_income': 'bg-red-100 text-red-800',
    'poor_credit': 'bg-orange-100 text-orange-800',
    'incomplete_docs': 'bg-yellow-100 text-yellow-800',
    'policy_violation': 'bg-purple-100 text-purple-800'
  }
  return reasonClasses[reason] || 'bg-gray-100 text-gray-800'
}

// 婉拒原因文字
const getDeclineReasonText = (reason) => {
  const reasonTexts = {
    'insufficient_income': '收入不足',
    'poor_credit': '信用不良',
    'incomplete_docs': '文件不全',
    'policy_violation': '違反政策'
  }
  return reasonTexts[reason] || '未知'
}

// 查看詳情
const viewDetails = (caseItem) => {
  navigateTo(`/submissions/${caseItem.id}/details`)
}

// 重新申請
const resubmit = async (caseItem) => {
  if (confirm(`確定要重新申請案件 ${caseItem.id} 嗎？`)) {
    try {
      const { error } = await put(`/submissions/${caseItem.id}/resubmit`)
      if (!error) {
        loadCases() // 重新載入資料
      }
    } catch (err) {
      console.error('重新申請失敗:', err)
    }
  }
}

// 組件掛載時載入資料
onMounted(() => {
  loadCases()
})
</script>