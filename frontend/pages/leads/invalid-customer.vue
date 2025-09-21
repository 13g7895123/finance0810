<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">無效客</h1>
        <p class="text-gray-600 mt-2">顯示狀態為無效的客戶案件</p>
      </div>
    </div>

    <!-- 資料表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <DataTable
        title="無效客戶案件"
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
            <!-- 無效原因篩選 -->
            <div class="relative">
              <select
                v-model="invalidReasonFilter"
                @change="loadCases"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部原因</option>
                <option value="rejected">拒絕追蹤</option>
                <option value="malicious">惡意人士</option>
                <option value="duplicate">重複資料</option>
                <option value="invalid_info">資訊無效</option>
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

        <!-- 無效原因欄位 -->
        <template #cell-invalid_reason="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getInvalidReasonClass(item.invalid_reason)">
            {{ getInvalidReasonText(item.invalid_reason) }}
          </span>
        </template>

        <!-- 操作欄位 -->
        <template #cell-actions="{ item }">
          <div class="flex items-center space-x-2">
            <button
              @click="restoreCase(item)"
              class="text-green-600 hover:text-green-800 text-sm font-medium"
            >
              恢復
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
  title: '無效客'
})

// 響應式數據
const cases = ref([])
const loading = ref(false)
const error = ref('')
const searchQuery = ref('')
const invalidReasonFilter = ref('')
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
  { key: 'invalid_reason', title: '無效原因', width: '120px' },
  { key: 'actions', title: '操作', width: '120px' }
]

// 載入案件資料 - 只顯示無效客戶
const loadCases = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      invalid_customer: true, // 只獲取無效客戶
      page: currentPage.value,
      per_page: itemsPerPage.value
    }

    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    if (invalidReasonFilter.value) {
      params.invalid_reason = invalidReasonFilter.value
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

// 無效原因樣式
const getInvalidReasonClass = (reason) => {
  const reasonClasses = {
    'rejected': 'bg-red-100 text-red-800',
    'malicious': 'bg-orange-100 text-orange-800',
    'duplicate': 'bg-yellow-100 text-yellow-800',
    'invalid_info': 'bg-gray-100 text-gray-800'
  }
  return reasonClasses[reason] || 'bg-gray-100 text-gray-800'
}

// 無效原因文字
const getInvalidReasonText = (reason) => {
  const reasonTexts = {
    'rejected': '拒絕追蹤',
    'malicious': '惡意人士',
    'duplicate': '重複資料',
    'invalid_info': '資訊無效'
  }
  return reasonTexts[reason] || '未知'
}

// 恢復案件
const restoreCase = async (caseItem) => {
  if (confirm(`確定要恢復案件 ${caseItem.id} 為有效客戶嗎？`)) {
    try {
      const { error } = await put(`/leads/${caseItem.id}/restore`)
      if (!error) {
        loadCases() // 重新載入資料
      }
    } catch (err) {
      console.error('恢復案件失敗:', err)
    }
  }
}

// 刪除案件
const deleteCase = async (caseItem) => {
  if (confirm(`確定要永久刪除案件 ${caseItem.id} 嗎？`)) {
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