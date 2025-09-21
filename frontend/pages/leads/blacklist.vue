<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">黑名單</h1>
        <p class="text-gray-600 mt-2">顯示被列入黑名單的客戶資料</p>
      </div>
    </div>

    <!-- 資料表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <DataTable
        title="黑名單客戶"
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
            <!-- 黑名單原因篩選 -->
            <div class="relative">
              <select
                v-model="blacklistReasonFilter"
                @change="loadCases"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部原因</option>
                <option value="fraud">詐欺</option>
                <option value="harassment">騷擾</option>
                <option value="abuse">濫用</option>
                <option value="repeat_offender">惡意重複</option>
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

        <!-- 黑名單原因欄位 -->
        <template #cell-blacklist_reason="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getBlacklistReasonClass(item.blacklist_reason)">
            {{ getBlacklistReasonText(item.blacklist_reason) }}
          </span>
        </template>

        <!-- 操作欄位 -->
        <template #cell-actions="{ item }">
          <div class="flex items-center space-x-2">
            <button
              @click="removeFromBlacklist(item)"
              class="text-green-600 hover:text-green-800 text-sm font-medium"
            >
              移除
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
  title: '黑名單'
})

// 響應式數據
const cases = ref([])
const loading = ref(false)
const error = ref('')
const searchQuery = ref('')
const blacklistReasonFilter = ref('')
const websiteFilter = ref('')
const websites = ref([])
const currentPage = ref(1)
const itemsPerPage = ref(10)

// API 組合
const { get, post, put, delete: deleteApi } = useApi()

// 表格欄位定義
const columns = [
  { key: 'id', title: '案件編號', sortable: true, width: '100px' },
  { key: 'blacklisted_at', title: '加入時間', sortable: true, width: '130px' },
  { key: 'customer_name', title: '客戶姓名', sortable: true, width: '120px' },
  { key: 'phone', title: '手機號碼', sortable: true, width: '130px' },
  { key: 'email', title: '電子信箱', sortable: true, width: '180px' },
  { key: 'website_name', title: '來源網站', width: '130px' },
  { key: 'blacklist_reason', title: '黑名單原因', width: '120px' },
  { key: 'actions', title: '操作', width: '120px' }
]

// 載入案件資料 - 只顯示黑名單客戶
const loadCases = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      blacklisted: true, // 只獲取黑名單客戶
      page: currentPage.value,
      per_page: itemsPerPage.value
    }

    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    if (blacklistReasonFilter.value) {
      params.blacklist_reason = blacklistReasonFilter.value
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

// 黑名單原因樣式
const getBlacklistReasonClass = (reason) => {
  const reasonClasses = {
    'fraud': 'bg-red-100 text-red-800',
    'harassment': 'bg-orange-100 text-orange-800',
    'abuse': 'bg-pink-100 text-pink-800',
    'repeat_offender': 'bg-purple-100 text-purple-800'
  }
  return reasonClasses[reason] || 'bg-gray-100 text-gray-800'
}

// 黑名單原因文字
const getBlacklistReasonText = (reason) => {
  const reasonTexts = {
    'fraud': '詐欺',
    'harassment': '騷擾',
    'abuse': '濫用',
    'repeat_offender': '惡意重複'
  }
  return reasonTexts[reason] || '未知'
}

// 移除黑名單
const removeFromBlacklist = async (caseItem) => {
  if (confirm(`確定要將 ${caseItem.customer_name} 從黑名單中移除嗎？`)) {
    try {
      const { error } = await put(`/leads/${caseItem.id}/remove-blacklist`)
      if (!error) {
        loadCases() // 重新載入資料
      }
    } catch (err) {
      console.error('移除黑名單失敗:', err)
    }
  }
}

// 查看詳情
const viewDetails = (caseItem) => {
  // 導航到詳情頁面
  navigateTo(`/leads/${caseItem.id}/details`)
}

// 組件掛載時載入資料
onMounted(() => {
  loadCases()
  loadWebsites()
})
</script>