<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">附條件</h1>
        <p class="text-gray-600 mt-2">顯示附條件核准的案件</p>
      </div>
    </div>

    <!-- 資料表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <DataTable
        title="附條件核准案件"
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
            <!-- 條件類型篩選 -->
            <div class="relative">
              <select
                v-model="conditionTypeFilter"
                @change="loadCases"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部條件</option>
                <option value="income_proof">收入證明</option>
                <option value="additional_docs">額外文件</option>
                <option value="guarantor">保證人</option>
                <option value="collateral">擔保品</option>
              </select>
            </div>

            <!-- 狀態篩選 -->
            <div class="relative">
              <select
                v-model="conditionStatusFilter"
                @change="loadCases"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部狀態</option>
                <option value="pending">待提供</option>
                <option value="submitted">已提供</option>
                <option value="reviewing">審核中</option>
              </select>
            </div>
          </div>
        </template>

        <!-- 條件類型欄位 -->
        <template #cell-condition_type="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getConditionTypeClass(item.condition_type)">
            {{ getConditionTypeText(item.condition_type) }}
          </span>
        </template>

        <!-- 條件狀態欄位 -->
        <template #cell-condition_status="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getConditionStatusClass(item.condition_status)">
            {{ getConditionStatusText(item.condition_status) }}
          </span>
        </template>

        <!-- 狀態欄位 -->
        <template #cell-status="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
            附條件
          </span>
        </template>

        <!-- 操作欄位 -->
        <template #cell-actions="{ item }">
          <div class="flex items-center space-x-2">
            <button
              @click="reviewConditions(item)"
              class="text-blue-600 hover:text-blue-800 text-sm font-medium"
            >
              審核
            </button>
            <button
              @click="contactCustomer(item)"
              class="text-orange-600 hover:text-orange-800 text-sm font-medium"
            >
              聯繫
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
  title: '附條件'
})

// 響應式數據
const cases = ref([])
const loading = ref(false)
const error = ref('')
const searchQuery = ref('')
const conditionTypeFilter = ref('')
const conditionStatusFilter = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(10)

// API 組合
const { get, put } = useApi()

// 表格欄位定義
const columns = [
  { key: 'id', title: '案件編號', sortable: true, width: '100px' },
  { key: 'conditional_at', title: '附條件時間', sortable: true, width: '130px' },
  { key: 'customer_name', title: '客戶姓名', sortable: true, width: '120px' },
  { key: 'phone', title: '手機號碼', sortable: true, width: '130px' },
  { key: 'condition_type', title: '條件類型', width: '120px' },
  { key: 'condition_status', title: '條件狀態', width: '100px' },
  { key: 'deadline', title: '截止日期', width: '120px' },
  { key: 'status', title: '狀態', width: '100px' },
  { key: 'actions', title: '操作', width: '120px' }
]

// 載入案件資料 - 只顯示附條件的案件
const loadCases = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      status: 'conditional', // 附條件核准
      page: currentPage.value,
      per_page: itemsPerPage.value
    }

    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    if (conditionTypeFilter.value) {
      params.condition_type = conditionTypeFilter.value
    }

    if (conditionStatusFilter.value) {
      params.condition_status = conditionStatusFilter.value
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

// 條件類型樣式
const getConditionTypeClass = (type) => {
  const typeClasses = {
    'income_proof': 'bg-blue-100 text-blue-800',
    'additional_docs': 'bg-green-100 text-green-800',
    'guarantor': 'bg-orange-100 text-orange-800',
    'collateral': 'bg-purple-100 text-purple-800'
  }
  return typeClasses[type] || 'bg-gray-100 text-gray-800'
}

// 條件類型文字
const getConditionTypeText = (type) => {
  const typeTexts = {
    'income_proof': '收入證明',
    'additional_docs': '額外文件',
    'guarantor': '保證人',
    'collateral': '擔保品'
  }
  return typeTexts[type] || '未知'
}

// 條件狀態樣式
const getConditionStatusClass = (status) => {
  const statusClasses = {
    'pending': 'bg-yellow-100 text-yellow-800',
    'submitted': 'bg-blue-100 text-blue-800',
    'reviewing': 'bg-purple-100 text-purple-800'
  }
  return statusClasses[status] || 'bg-gray-100 text-gray-800'
}

// 條件狀態文字
const getConditionStatusText = (status) => {
  const statusTexts = {
    'pending': '待提供',
    'submitted': '已提供',
    'reviewing': '審核中'
  }
  return statusTexts[status] || '未知'
}

// 審核條件
const reviewConditions = (caseItem) => {
  navigateTo(`/submissions/${caseItem.id}/conditions`)
}

// 聯繫客戶
const contactCustomer = async (caseItem) => {
  try {
    const { error } = await put(`/submissions/${caseItem.id}/contact`)
    if (!error) {
      loadCases() // 重新載入資料
    }
  } catch (err) {
    console.error('聯繫客戶失敗:', err)
  }
}

// 組件掛載時載入資料
onMounted(() => {
  loadCases()
})
</script>