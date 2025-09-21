<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">客服</h1>
        <p class="text-gray-600 mt-2">顯示需要客服處理的案件</p>
      </div>
    </div>

    <!-- 資料表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <DataTable
        title="客服案件"
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
            <!-- 優先級篩選 -->
            <div class="relative">
              <select
                v-model="priorityFilter"
                @change="loadCases"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部優先級</option>
                <option value="high">高</option>
                <option value="medium">中</option>
                <option value="low">低</option>
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

        <!-- 優先級欄位 -->
        <template #cell-priority="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getPriorityClass(item.priority)">
            {{ getPriorityText(item.priority) }}
          </span>
        </template>

        <!-- 操作欄位 -->
        <template #cell-actions="{ item }">
          <div class="flex items-center space-x-2">
            <button
              @click="handleCase(item)"
              class="text-blue-600 hover:text-blue-800 text-sm font-medium"
            >
              處理
            </button>
            <button
              @click="escalateCase(item)"
              class="text-orange-600 hover:text-orange-800 text-sm font-medium"
            >
              升級
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
  title: '客服'
})

// 響應式數據
const cases = ref([])
const loading = ref(false)
const error = ref('')
const searchQuery = ref('')
const priorityFilter = ref('')
const websiteFilter = ref('')
const websites = ref([])
const currentPage = ref(1)
const itemsPerPage = ref(10)

// API 組合
const { get, post, put } = useApi()

// 表格欄位定義
const columns = [
  { key: 'id', title: '案件編號', sortable: true, width: '100px' },
  { key: 'created_at', title: '建立時間', sortable: true, width: '130px' },
  { key: 'customer_name', title: '客戶姓名', sortable: true, width: '120px' },
  { key: 'phone', title: '手機號碼', sortable: true, width: '130px' },
  { key: 'loan_amount', title: '貸款金額', sortable: true, width: '120px' },
  { key: 'website_name', title: '來源網站', width: '130px' },
  { key: 'priority', title: '優先級', width: '100px' },
  { key: 'actions', title: '操作', width: '120px' }
]

// 載入案件資料 - 只顯示客服案件
const loadCases = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      customer_service: true, // 只獲取客服案件
      page: currentPage.value,
      per_page: itemsPerPage.value
    }

    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    if (priorityFilter.value) {
      params.priority = priorityFilter.value
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

// 優先級樣式
const getPriorityClass = (priority) => {
  const priorityClasses = {
    'high': 'bg-red-100 text-red-800',
    'medium': 'bg-yellow-100 text-yellow-800',
    'low': 'bg-green-100 text-green-800'
  }
  return priorityClasses[priority] || 'bg-gray-100 text-gray-800'
}

// 優先級文字
const getPriorityText = (priority) => {
  const priorityTexts = {
    'high': '高',
    'medium': '中',
    'low': '低'
  }
  return priorityTexts[priority] || '未知'
}

// 處理案件
const handleCase = async (caseItem) => {
  try {
    const { error } = await put(`/leads/${caseItem.id}/handle`)
    if (!error) {
      loadCases() // 重新載入資料
    }
  } catch (err) {
    console.error('處理案件失敗:', err)
  }
}

// 升級案件
const escalateCase = async (caseItem) => {
  if (confirm(`確定要升級案件 ${caseItem.id} 嗎？`)) {
    try {
      const { error } = await put(`/leads/${caseItem.id}/escalate`)
      if (!error) {
        loadCases() // 重新載入資料
      }
    } catch (err) {
      console.error('升級案件失敗:', err)
    }
  }
}

// 組件掛載時載入資料
onMounted(() => {
  loadCases()
  loadWebsites()
})
</script>