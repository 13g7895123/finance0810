<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">有效客</h1>
        <p class="text-gray-600 mt-2">顯示狀態為有效的客戶案件</p>
      </div>
    </div>

    <!-- 資料表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <DataTable
        title="有效客戶案件"
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
            <!-- 案件狀態下拉選單 -->
            <div class="relative">
              <select
                v-model="statusFilter"
                @change="handleStatusChange"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部狀態</option>
                <option value="pending">待處理</option>
                <option value="contacted">已聯繫</option>
                <option value="qualified">已評估</option>
                <option value="submitted">已送件</option>
              </select>
            </div>

            <!-- 網站篩選 -->
            <div class="relative">
              <select
                v-model="websiteFilter"
                @change="loadCases"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部網站</option>
                <option v-for="website in websites" :key="website.id" :value="website.id">
                  {{ website.name }}
                </option>
              </select>
            </div>
          </div>
        </template>

        <!-- 狀態欄位 -->
        <template #cell-status="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getStatusClass(item.status)">
            {{ getStatusText(item.status) }}
          </span>
        </template>

        <!-- 操作欄位 -->
        <template #cell-actions="{ item }">
          <div class="flex items-center space-x-2">
            <button
              @click="editCase(item)"
              class="text-blue-600 hover:text-blue-800 text-sm font-medium"
            >
              編輯
            </button>
            <button
              @click="deleteCase(item)"
              class="text-red-600 hover:text-red-800 text-sm font-medium"
            >
              刪除
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
  title: '有效客'
})

// 響應式數據
const cases = ref([])
const loading = ref(false)
const error = ref('')
const searchQuery = ref('')
const statusFilter = ref('')
const websiteFilter = ref('')
const websites = ref([])
const currentPage = ref(1)
const itemsPerPage = ref(10)

// API 組合
const { get, post, put, delete: deleteApi } = useApi()

// 表格欄位定義
const columns = [
  { key: 'id', title: '案件編號', sortable: true, width: '100px' },
  { key: 'created_at', title: '建立時間', sortable: true, width: '130px' },
  { key: 'customer_name', title: '客戶姓名', sortable: true, width: '120px' },
  { key: 'phone', title: '手機號碼', sortable: true, width: '130px' },
  { key: 'loan_amount', title: '貸款金額', sortable: true, width: '120px' },
  { key: 'website_name', title: '來源網站', width: '130px' },
  { key: 'status', title: '案件狀態', width: '100px' },
  { key: 'actions', title: '操作', width: '120px' }
]

// 載入案件資料 - 只顯示有效客戶（valid status）
const loadCases = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      valid_customer: true, // 只獲取有效客戶
      page: currentPage.value,
      per_page: itemsPerPage.value
    }

    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    if (statusFilter.value) {
      params.status = statusFilter.value
    }

    if (websiteFilter.value) {
      params.website_id = websiteFilter.value
    }

    const { data, error: apiError } = await get('/leads', params)

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

// 載入網站列表
const loadWebsites = async () => {
  try {
    const { data } = await get('/websites')
    websites.value = data || []
  } catch (err) {
    console.error('載入網站列表失敗:', err)
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

// 狀態變更處理
const handleStatusChange = () => {
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

// 狀態樣式
const getStatusClass = (status) => {
  const statusClasses = {
    'pending': 'bg-yellow-100 text-yellow-800',
    'contacted': 'bg-blue-100 text-blue-800',
    'qualified': 'bg-green-100 text-green-800',
    'submitted': 'bg-purple-100 text-purple-800'
  }
  return statusClasses[status] || 'bg-gray-100 text-gray-800'
}

// 狀態文字
const getStatusText = (status) => {
  const statusTexts = {
    'pending': '待處理',
    'contacted': '已聯繫',
    'qualified': '已評估',
    'submitted': '已送件'
  }
  return statusTexts[status] || '未知'
}

// 編輯案件
const editCase = (caseItem) => {
  // 導航到編輯頁面或打開編輯對話框
  navigateTo(`/cases/${caseItem.id}/edit`)
}

// 刪除案件
const deleteCase = async (caseItem) => {
  if (confirm(`確定要刪除案件 ${caseItem.id} 嗎？`)) {
    try {
      const { error } = await deleteApi(`/leads/${caseItem.id}`)
      if (!error) {
        loadCases() // 重新載入資料
      }
    } catch (err) {
      console.error('刪除案件失敗:', err)
    }
  }
}

// 組件掛載時載入資料
onMounted(() => {
  loadCases()
  loadWebsites()
})
</script>