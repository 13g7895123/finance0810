<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">客戶資料管理</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">
          <span v-if="authStore.isSales">您的客戶清單</span>
          <span v-else>所有客戶資料總覽</span>
        </p>
      </div>
      
      <div class="flex space-x-3">
        <button
          v-if="authStore.hasPermission('customer_management')"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center space-x-2"
        >
          <PlusIcon class="w-5 h-5" />
          <span>新增客戶</span>
        </button>
      </div>
    </div>

    <!-- 統計卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <StatsCard
        title="總客戶數"
        :value="customerStats.total"
        description="系統中的客戶總數"
        icon="UserGroupIcon"
        iconColor="blue"
        :trend="5.2"
      />
      
      <StatsCard
        title="活躍客戶"
        :value="customerStats.active"
        description="近30天有互動"
        icon="CheckCircleIcon"
        iconColor="green"
        :trend="12.3"
        :progress="78"
      />
      
      <StatsCard
        title="新增客戶"
        :value="customerStats.new"
        description="本月新增"
        icon="PlusIcon"
        iconColor="yellow"
        :trend="8.1"
      />
      
      <StatsCard
        v-if="!authStore.isSales"
        title="轉換率"
        :value="customerStats.conversionRate"
        format="percentage"
        description="潛在客戶轉換率"
        icon="ChartBarIcon"
        iconColor="purple"
        :trend="-2.4"
        :progress="65"
      />
    </div>

    <!-- 客戶列表 -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
      <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white">客戶清單</h2>
          
          <div class="flex items-center space-x-4">
            <!-- 搜尋框 -->
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="搜尋客戶..."
                class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <MagnifyingGlassIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            </div>
            
            <!-- 篩選器 -->
            <select
              v-model="statusFilter"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">所有狀態</option>
              <option v-for="(label, value) in getStatusOptions()" :key="value" :value="value">
                {{ label }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <!-- Loading state -->
        <div v-if="loading" class="p-8 text-center">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <p class="mt-2 text-gray-600 dark:text-gray-400">載入中...</p>
        </div>
        
        <!-- Error state -->
        <div v-else-if="error" class="p-8 text-center">
          <p class="text-red-600 dark:text-red-400">{{ error }}</p>
          <button 
            @click="loadCustomers"
            class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
          >
            重試
          </button>
        </div>
        
        <!-- Data table -->
        <table v-else class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                客戶資訊
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                聯絡方式
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                狀態
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                LINE 狀態
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                最後聯絡
              </th>
              <th v-if="!authStore.isSales" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                負責業務
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                操作
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr 
              v-for="customer in paginatedCustomers" 
              :key="customer.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 w-10 h-10">
                    <img 
                      :src="`https://ui-avatars.com/api/?name=${customer.name}&background=6366f1&color=fff`" 
                      :alt="customer.name"
                      class="w-10 h-10 rounded-full"
                    />
                  </div>
                  <div class="ml-4">
                    <div class="text-base font-medium text-gray-900 dark:text-white">
                      {{ customer.name }}
                    </div>
                    <div class="text-base text-gray-500 dark:text-gray-400">
                      {{ customer.region || '未填寫地區' }}
                    </div>
                  </div>
                </div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-base text-gray-900 dark:text-white">{{ customer.email }}</div>
                <div class="text-base text-gray-500 dark:text-gray-400">{{ customer.phone }}</div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  class="inline-flex px-2 py-1 text-sm font-semibold rounded-full"
                  :class="getStatusClass(customer.status)"
                >
                  {{ getStatusText(customer.status) }}
                </span>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-2">
                  <div v-if="customer.line_user_id" class="flex items-center space-x-1">
                    <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                    <span class="text-xs text-green-600 dark:text-green-400">已綁定</span>
                  </div>
                  <div v-else class="flex items-center space-x-1">
                    <div class="w-2 h-2 bg-gray-300 rounded-full"></div>
                    <span class="text-xs text-gray-500">未綁定</span>
                  </div>
                  <button 
                    v-if="customer.line_user_id"
                    @click="checkLineFriend(customer)"
                    class="text-xs text-blue-600 hover:text-blue-800"
                    title="檢查好友狀態"
                  >
                    檢查
                  </button>
                </div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-500 dark:text-gray-400">
                {{ customer.updated_at ? formatDate(customer.updated_at) : '無記錄' }}
              </td>
              
              <td v-if="!authStore.isSales" class="px-6 py-4 whitespace-nowrap text-base text-gray-500 dark:text-gray-400">
                {{ customer.assigned_user?.name || '未分配' }}
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-right text-base font-medium space-x-2">
                <button class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                  查看
                </button>
                <button 
                  v-if="authStore.hasPermission('customer_management') || customer.assigned_to === authStore.user?.id"
                  class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"
                >
                  編輯
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination Controls -->
      <div v-if="totalPages > 1" class="mt-6 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-4">
        <div class="flex-1 flex justify-between sm:hidden">
          <button
            @click="previousPage"
            :disabled="currentPage === 1"
            class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            上一頁
          </button>
          <button
            @click="nextPage"
            :disabled="currentPage === totalPages"
            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            下一頁
          </button>
        </div>
        
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700 dark:text-gray-300">
              顯示第 <span class="font-medium">{{ ((currentPage - 1) * itemsPerPage) + 1 }}</span> 
              到 <span class="font-medium">{{ Math.min(currentPage * itemsPerPage, filteredCustomers.length) }}</span> 
              筆，共 <span class="font-medium">{{ filteredCustomers.length }}</span> 筆記錄
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="分頁導航">
              <button
                @click="previousPage"
                :disabled="currentPage === 1"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                ❮
              </button>
              
              <template v-for="page in getVisiblePages()" :key="page">
                <button
                  v-if="typeof page === 'number'"
                  @click="goToPage(page)"
                  :class="[
                    page === currentPage
                      ? 'bg-primary-50 border-primary-500 text-primary-600 dark:bg-primary-900/50 dark:border-primary-400 dark:text-primary-300'
                      : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700',
                    'relative inline-flex items-center px-4 py-2 border text-sm font-medium'
                  ]"
                >
                  {{ page }}
                </button>
                <span
                  v-else
                  class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                  ...
                </span>
              </template>
              
              <button
                @click="nextPage"
                :disabled="currentPage === totalPages"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                ❯
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { 
  PlusIcon,
  MagnifyingGlassIcon,
  UserGroupIcon,
  CheckCircleIcon,
  ChartBarIcon
} from '@heroicons/vue/24/outline'

// 明確匯入 StatsCard 組件
import StatsCard from '~/components/StatsCard.vue'

definePageMeta({
  middleware: ['auth', 'role']
})

const authStore = useAuthStore()
const { 
  getCustomers, 
  checkLineFriendStatus, 
  getStatusOptions,
  createCustomer,
  updateCustomer,
  deleteCustomer
} = useCustomers()

// 搜尋和篩選
const searchQuery = ref('')
const statusFilter = ref('')

// 載入狀態
const loading = ref(false)
const error = ref(null)

// 客戶數據
const customers = ref([])
const customerStats = ref({
  total: 0,
  active: 0,
  new: 0,
  conversionRate: 0
})

// 模態窗口狀態
const showCreateModal = ref(false)
const showEditModal = ref(false)
const editingCustomer = ref(null)

// 表單數據
const customerForm = ref({
  name: '',
  phone: '',
  email: '',
  region: '',
  website_source: '',
  channel: '',
  notes: '',
  assigned_to: null
})

// 載入客戶數據
const loadCustomers = async () => {
  loading.value = true
  error.value = null
  
  try {
    const params = {}
    
    // 添加搜尋參數
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim()
    }
    
    // 添加狀態過濾
    if (statusFilter.value) {
      params.status = statusFilter.value
    }
    
    const { data, error: apiError } = await getCustomers(params)
    
    if (apiError) {
      error.value = apiError.message
      return
    }
    
    customers.value = data.data || []
    
    // 計算統計數據
    const total = customers.value.length
    const activeCount = customers.value.filter(c => ['new', 'contacted', 'interested'].includes(c.status)).length
    const newCount = customers.value.filter(c => c.status === 'new').length
    const convertedCount = customers.value.filter(c => c.status === 'converted').length
    
    customerStats.value = {
      total,
      active: activeCount,
      new: newCount,
      conversionRate: total > 0 ? Math.round((convertedCount / total) * 100) : 0
    }
    
  } catch (err) {
    error.value = '載入客戶數據失敗'
    console.error('Load customers error:', err)
  } finally {
    loading.value = false
  }
}

// 過濾客戶列表
const filteredCustomers = computed(() => {
  return customers.value
})

// Pagination
const currentPage = ref(1)
const itemsPerPage = 10

const totalPages = computed(() => Math.ceil(filteredCustomers.value.length / itemsPerPage))

const paginatedCustomers = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredCustomers.value.slice(start, end)
})

// Pagination methods
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

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

// Generate visible page numbers for pagination
const getVisiblePages = () => {
  const pages = []
  const maxVisible = 7
  
  if (totalPages.value <= maxVisible) {
    for (let i = 1; i <= totalPages.value; i++) {
      pages.push(i)
    }
  } else {
    if (currentPage.value <= 4) {
      for (let i = 1; i <= 5; i++) {
        pages.push(i)
      }
      pages.push('...')
      pages.push(totalPages.value)
    } else if (currentPage.value >= totalPages.value - 3) {
      pages.push(1)
      pages.push('...')
      for (let i = totalPages.value - 4; i <= totalPages.value; i++) {
        pages.push(i)
      }
    } else {
      pages.push(1)
      pages.push('...')
      for (let i = currentPage.value - 1; i <= currentPage.value + 1; i++) {
        pages.push(i)
      }
      pages.push('...')
      pages.push(totalPages.value)
    }
  }
  
  return pages
}

// 檢查LINE好友狀態
const checkLineFriend = async (customer) => {
  try {
    const { data, error: apiError } = await checkLineFriendStatus(customer.id)
    
    if (apiError) {
      console.error('檢查LINE好友狀態失敗:', apiError.message)
      return
    }
    
    // 顯示結果 - 使用簡單的 alert 或可以替換為更好的通知系統
    if (data.is_friend === true) {
      alert('已建立好友關係')
    } else if (data.is_friend === false) {
      alert('未建立好友關係')
    } else {
      alert(data.message || 'LINE整合未設定')
    }
    
  } catch (err) {
    console.error('檢查LINE好友狀態錯誤:', err)
  }
}

// 監聽搜尋和篩選變化，自動重新載入數據
watch([searchQuery, statusFilter], () => {
  loadCustomers()
}, { debounce: 300 })

// 狀態樣式
const getStatusClass = (status) => {
  const statusOptions = getStatusOptions()
  const classes = {
    'new': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    'contacted': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    'interested': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    'not_interested': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    'invalid': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    'converted': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300'
  }
  return classes[status] || classes.new
}

// 狀態文字
const getStatusText = (status) => {
  const statusOptions = getStatusOptions()
  return statusOptions[status] || '未知'
}

// 日期格式化
const formatDate = (date) => {
  return new Date(date).toLocaleDateString('zh-TW', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

// 頁面載入時獲取數據
onMounted(() => {
  loadCustomers()
})

// 設定頁面標題
useHead({
  title: '客戶管理 - 貸款案件管理系統'
})
</script>