<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">追蹤紀錄</h1>
        <p class="text-gray-600 mt-2">查看所有客戶追蹤紀錄與活動歷史</p>
      </div>
    </div>

    <!-- 資料表格 -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <DataTable
        title="客戶追蹤紀錄"
        :columns="columns"
        :data="filteredRecords"
        :loading="loading"
        :error="error"
        search-placeholder="搜尋客戶姓名、手機、紀錄內容..."
        :search-query="searchQuery"
        @search="handleSearch"
        @refresh="loadRecords"
        :current-page="currentPage"
        :items-per-page="itemsPerPage"
        @page-change="handlePageChange"
        @page-size-change="handlePageSizeChange"
      >
        <!-- 篩選器插槽 -->
        <template #filters>
          <div class="flex items-center space-x-3">
            <!-- 追蹤類型篩選 -->
            <div class="relative">
              <select
                v-model="trackingTypeFilter"
                @change="loadRecords"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部類型</option>
                <option value="phone_call">電話聯繫</option>
                <option value="meeting">面談會議</option>
                <option value="email">電子郵件</option>
                <option value="follow_up">後續追蹤</option>
              </select>
            </div>

            <!-- 結果篩選 -->
            <div class="relative">
              <select
                v-model="resultFilter"
                @change="loadRecords"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部結果</option>
                <option value="success">成功</option>
                <option value="no_answer">未接聽</option>
                <option value="not_interested">無興趣</option>
                <option value="callback_requested">要求回撥</option>
              </select>
            </div>

            <!-- 業務人員篩選 -->
            <div class="relative">
              <select
                v-model="salesPersonFilter"
                @change="loadRecords"
                class="border border-gray-300 rounded-lg bg-white text-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-32"
              >
                <option value="">全部業務</option>
                <option v-for="person in salesPersons" :key="person.id" :value="person.id">
                  {{ person.name }}
                </option>
              </select>
            </div>
          </div>
        </template>

        <!-- 追蹤類型欄位 -->
        <template #cell-tracking_type="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getTrackingTypeClass(item.tracking_type)">
            {{ getTrackingTypeText(item.tracking_type) }}
          </span>
        </template>

        <!-- 結果欄位 -->
        <template #cell-result="{ item }">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getResultClass(item.result)">
            {{ getResultText(item.result) }}
          </span>
        </template>

        <!-- 內容欄位 -->
        <template #cell-content="{ item }">
          <div class="max-w-xs truncate" :title="item.content">
            {{ item.content }}
          </div>
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
              @click="addFollowUp(item)"
              class="text-green-600 hover:text-green-800 text-sm font-medium"
            >
              追蹤
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
  title: '追蹤紀錄'
})

// 響應式數據
const records = ref([])
const loading = ref(false)
const error = ref('')
const searchQuery = ref('')
const trackingTypeFilter = ref('')
const resultFilter = ref('')
const salesPersonFilter = ref('')
const salesPersons = ref([])
const currentPage = ref(1)
const itemsPerPage = ref(10)

// API 組合
const { get, post } = useApi()

// 表格欄位定義
const columns = [
  { key: 'created_at', title: '追蹤時間', sortable: true, width: '130px' },
  { key: 'customer_name', title: '客戶姓名', sortable: true, width: '120px' },
  { key: 'phone', title: '手機號碼', sortable: true, width: '130px' },
  { key: 'tracking_type', title: '追蹤類型', width: '100px' },
  { key: 'content', title: '追蹤內容', width: '200px' },
  { key: 'result', title: '結果', width: '100px' },
  { key: 'sales_person', title: '業務人員', width: '100px' },
  { key: 'next_contact_date', title: '下次聯繫', width: '120px' },
  { key: 'actions', title: '操作', width: '120px' }
]

// 載入追蹤紀錄
const loadRecords = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value
    }

    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    if (trackingTypeFilter.value) {
      params.tracking_type = trackingTypeFilter.value
    }

    if (resultFilter.value) {
      params.result = resultFilter.value
    }

    if (salesPersonFilter.value) {
      params.sales_person_id = salesPersonFilter.value
    }

    const { data, error: apiError } = await get('/tracking-records', params)

    if (apiError) {
      error.value = apiError
    } else {
      records.value = data?.data || []
    }
  } catch (err) {
    error.value = '載入資料失敗'
    console.error('載入追蹤紀錄失敗:', err)
  } finally {
    loading.value = false
  }
}

// 載入業務人員列表
const loadSalesPersons = async () => {
  try {
    const { data } = await get('/users', { role: 'staff' })
    salesPersons.value = data || []
  } catch (err) {
    console.error('載入業務人員失敗:', err)
  }
}

// 篩選後的紀錄
const filteredRecords = computed(() => {
  return records.value
})

// 搜尋處理
const handleSearch = (query) => {
  searchQuery.value = query
  currentPage.value = 1
  loadRecords()
}

// 分頁處理
const handlePageChange = (page) => {
  currentPage.value = page
  loadRecords()
}

const handlePageSizeChange = (size) => {
  itemsPerPage.value = size
  currentPage.value = 1
  loadRecords()
}

// 追蹤類型樣式
const getTrackingTypeClass = (type) => {
  const typeClasses = {
    'phone_call': 'bg-blue-100 text-blue-800',
    'meeting': 'bg-green-100 text-green-800',
    'email': 'bg-purple-100 text-purple-800',
    'follow_up': 'bg-orange-100 text-orange-800'
  }
  return typeClasses[type] || 'bg-gray-100 text-gray-800'
}

// 追蹤類型文字
const getTrackingTypeText = (type) => {
  const typeTexts = {
    'phone_call': '電話聯繫',
    'meeting': '面談會議',
    'email': '電子郵件',
    'follow_up': '後續追蹤'
  }
  return typeTexts[type] || '未知'
}

// 結果樣式
const getResultClass = (result) => {
  const resultClasses = {
    'success': 'bg-green-100 text-green-800',
    'no_answer': 'bg-yellow-100 text-yellow-800',
    'not_interested': 'bg-red-100 text-red-800',
    'callback_requested': 'bg-blue-100 text-blue-800'
  }
  return resultClasses[result] || 'bg-gray-100 text-gray-800'
}

// 結果文字
const getResultText = (result) => {
  const resultTexts = {
    'success': '成功',
    'no_answer': '未接聽',
    'not_interested': '無興趣',
    'callback_requested': '要求回撥'
  }
  return resultTexts[result] || '未知'
}

// 查看詳情
const viewDetails = (record) => {
  navigateTo(`/tracking-records/${record.id}/details`)
}

// 新增追蹤
const addFollowUp = (record) => {
  navigateTo(`/cases/${record.case_id}/tracking?add_follow_up=true`)
}

// 組件掛載時載入資料
onMounted(() => {
  loadRecords()
  loadSalesPersons()
})
</script>