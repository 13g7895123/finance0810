<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">追蹤管理</h1>
        <p class="text-gray-600 mt-2">
          <span v-if="authStore.isSales">您的客戶追蹤清單</span>
          <span v-else>客戶追蹤管理（自動排除無效客戶與黑名單）</span>
        </p>
      </div>
    </div>

    <!-- 統計卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <StatsCard
        title="追蹤客戶總數"
        :value="trackingStats.total"
        description="可追蹤的客戶總數"
        icon="UserGroupIcon"
        iconColor="blue"
      />
      
      <StatsCard
        title="A級客戶"
        :value="trackingStats.levelA"
        description="高優先級客戶"
        icon="StarIcon"
        iconColor="yellow"
      />
      
      <StatsCard
        title="B級客戶"
        :value="trackingStats.levelB"
        description="中等優先級客戶"
        icon="UserIcon"
        iconColor="green"
      />
      
      <StatsCard
        title="C級客戶"
        :value="trackingStats.levelC"
        description="低優先級客戶"
        icon="UsersIcon"
        iconColor="gray"
      />
    </div>

    <!-- 客戶追蹤列表 -->
    <DataTable
      title="客戶追蹤清單"
      :columns="trackingTableColumns"
      :data="trackingCustomers"
      :loading="loading"
      :error="loadError"
      :search-query="searchQuery"
      search-placeholder="搜尋客戶..."
      :current-page="currentPage"
      :items-per-page="itemsPerPage"
      loading-text="載入中..."
      empty-text="沒有可追蹤的客戶"
      @search="handleSearch"
      @refresh="loadTrackingCustomers"
      @retry="loadTrackingCustomers"
      @page-change="handlePageChange"
      @page-size-change="handlePageSizeChange"
    >
      <!-- Filter Controls -->
      <template #filters>
        <!-- 客戶等級篩選 -->
        <select
          v-model="levelFilter"
          class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">所有等級</option>
          <option value="A">A級客戶</option>
          <option value="B">B級客戶</option>
          <option value="C">C級客戶</option>
        </select>

        <!-- 地區篩選 -->
        <select
          v-model="regionFilter"
          class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">所有地區</option>
          <option v-for="region in regions" :key="region" :value="region">
            {{ region }}
          </option>
        </select>

        <!-- 業務篩選 (只對管理員顯示) -->
        <template v-if="!authStore.isSales">
          <select
            v-model="assigneeFilter"
            class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">所有業務</option>
            <option value="null">未指派</option>
            <option v-for="user in salesUsers" :key="user.id" :value="user.id">
              {{ user.name }}
            </option>
          </select>
        </template>
      </template>

      <!-- 自定義欄位渲染 -->
      <template #cell-customer_level="{ item }">
        <div class="flex items-center space-x-2">
          <span
            :class="{
              'bg-yellow-100 text-yellow-800': item.customer_level === 'A',
              'bg-green-100 text-green-800': item.customer_level === 'B',
              'bg-gray-100 text-gray-800': item.customer_level === 'C'
            }"
            class="px-2 py-1 text-xs font-medium rounded-full"
          >
            {{ item.customer_level }}級
          </span>
          <button
            @click="openLevelEditModal(item)"
            class="p-1 text-gray-400 hover:text-gray-600 rounded"
            title="編輯等級"
          >
            <PencilIcon class="w-4 h-4" />
          </button>
        </div>
      </template>

      <template #cell-status="{ item }">
        <span
          :class="getStatusClass(item.status)"
          class="px-2 py-1 text-xs font-medium rounded-full"
        >
          {{ getStatusLabel(item.status) }}
        </span>
      </template>

      <template #cell-assigned_user="{ item }">
        {{ item.assigned_user?.name || '未指派' }}
      </template>

      <template #cell-created_at="{ item }">
        <div>
          <div class="text-sm font-medium">{{ formatDate(item.created_at) }}</div>
          <div class="text-xs text-gray-500">{{ formatTime(item.created_at) }}</div>
        </div>
      </template>

      <template #cell-next_contact_date="{ item }">
        <div v-if="item.next_contact_date">
          <div class="text-sm font-medium">{{ formatDate(item.next_contact_date) }}</div>
          <div 
            :class="{
              'text-red-600': isOverdue(item.next_contact_date),
              'text-yellow-600': isToday(item.next_contact_date),
              'text-gray-500': isFuture(item.next_contact_date)
            }"
            class="text-xs"
          >
            {{ getContactDateStatus(item.next_contact_date) }}
          </div>
        </div>
        <span v-else class="text-gray-500">-</span>
      </template>

      <!-- Actions Cell -->
      <template #cell-actions="{ item }">
        <div class="flex items-center space-x-1">
          <!-- 編輯客戶等級按鈕 -->
          <button 
            @click="openLevelEditModal(item)"
            class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-all duration-200 group relative"
            title="編輯等級"
          >
            <StarIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              編輯等級
            </span>
          </button>

          <!-- 檢視詳細資料按鈕 -->
          <button 
            @click="viewCustomerDetails(item)"
            class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-50 rounded-lg transition-all duration-200 group relative"
            title="檢視"
          >
            <EyeIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              檢視
            </span>
          </button>
        </div>
      </template>
    </DataTable>

    <!-- 客戶等級編輯 Modal -->
    <div v-if="levelEditModal.open" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeLevelEditModal">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900">編輯客戶等級</h3>
          <button @click="closeLevelEditModal" class="text-gray-400 hover:text-gray-600">
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>
        
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">客戶資訊</label>
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="font-medium">{{ levelEditModal.customer?.name }}</div>
              <div class="text-sm text-gray-600">{{ levelEditModal.customer?.phone }}</div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">客戶等級</label>
            <select
              v-model="levelEditModal.level"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="A">A級客戶 - 高優先級</option>
              <option value="B">B級客戶 - 中等優先級</option>
              <option value="C">C級客戶 - 低優先級</option>
            </select>
          </div>
        </div>

        <div class="flex justify-end space-x-3 mt-6">
          <button
            @click="closeLevelEditModal"
            class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors"
          >
            取消
          </button>
          <button
            @click="saveCustomerLevel"
            :disabled="levelEditModal.saving"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
          >
            {{ levelEditModal.saving ? '儲存中...' : '儲存' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 客戶詳情模態窗口 -->
    <div
      v-if="showViewModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="closeViewModal"
    >
      <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900">客戶詳情</h3>
          <button
            @click="closeViewModal"
            class="text-gray-400 hover:text-gray-600"
          >
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>

        <div v-if="selectedCustomer" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">客戶姓名</label>
              <p class="text-gray-900">{{ selectedCustomer.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">電話</label>
              <p class="text-gray-900">{{ selectedCustomer.phone }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">電子郵件</label>
              <p class="text-gray-900">{{ selectedCustomer.email || '未提供' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">地區</label>
              <p class="text-gray-900">{{ selectedCustomer.region || '未填寫' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">狀態</label>
              <span
                class="inline-flex px-2 py-1 text-sm font-semibold rounded-full"
                :class="getStatusClass(selectedCustomer.status)"
              >
                {{ getStatusLabel(selectedCustomer.status) }}
              </span>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">客戶等級</label>
              <span
                :class="{
                  'bg-yellow-100 text-yellow-800': selectedCustomer.customer_level === 'A',
                  'bg-green-100 text-green-800': selectedCustomer.customer_level === 'B',
                  'bg-gray-100 text-gray-800': selectedCustomer.customer_level === 'C'
                }"
                class="px-2 py-1 text-sm font-medium rounded-full"
              >
                {{ selectedCustomer.customer_level }}級
              </span>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">負責業務</label>
              <p class="text-gray-900">{{ selectedCustomer.assigned_user?.name || '未分配' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">建立時間</label>
              <p class="text-gray-900">{{ formatDate(selectedCustomer.created_at) }}</p>
            </div>
            <div v-if="selectedCustomer.next_contact_date">
              <label class="block text-sm font-medium text-gray-700">下次聯絡日期</label>
              <p class="text-gray-900">{{ formatDate(selectedCustomer.next_contact_date) }}</p>
            </div>
          </div>

          <div v-if="selectedCustomer.notes">
            <label class="block text-sm font-medium text-gray-700">備註</label>
            <p class="text-gray-900 whitespace-pre-wrap">{{ selectedCustomer.notes }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { 
  PencilIcon, 
  EyeIcon, 
  XMarkIcon,
  StarIcon,
  UserIcon,
  UsersIcon,
  UserGroupIcon
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '~/stores/auth'
import { useNotificationsStore } from '~/stores/notifications'
import { useApi } from '~/composables/useApi'

// Stores
const authStore = useAuthStore()
const notificationsStore = useNotificationsStore()
const { get, patch } = useApi()

// Data
const loading = ref(true)
const loadError = ref(null)
const trackingCustomers = ref([])
const salesUsers = ref([])
const regions = ref([])

// Filters
const searchQuery = ref('')
const levelFilter = ref('')
const regionFilter = ref('')
const assigneeFilter = ref('')

// Pagination
const currentPage = ref(1)
const itemsPerPage = ref(15)

// Modals
const levelEditModal = reactive({
  open: false,
  customer: null,
  level: 'B',
  saving: false
})
const showViewModal = ref(false)
const selectedCustomer = ref(null)

// Statistics
const trackingStats = computed(() => {
  const stats = {
    total: trackingCustomers.value.length,
    levelA: 0,
    levelB: 0,
    levelC: 0
  }
  
  trackingCustomers.value.forEach(customer => {
    if (customer.customer_level === 'A') stats.levelA++
    else if (customer.customer_level === 'B') stats.levelB++
    else if (customer.customer_level === 'C') stats.levelC++
  })
  
  return stats
})

// Table columns
const trackingTableColumns = computed(() => {
  const baseColumns = [
    { key: 'name', title: '客戶姓名', sortable: true },
    { key: 'phone', title: '電話' },
    { key: 'customer_level', title: '客戶等級', width: '120px' },
    { key: 'status', title: '狀態', width: '100px' },
    { key: 'region', title: '地區' },
  ]

  // 非業務人員可以看到負責業務欄位
  if (!authStore.isSales) {
    baseColumns.push({ key: 'assigned_user', title: '負責業務' })
  }

  baseColumns.push(
    { key: 'next_contact_date', title: '下次聯絡', width: '120px' },
    { key: 'created_at', title: '建立時間', width: '120px' },
    { key: 'actions', title: '操作', width: '100px', align: 'center' }
  )

  return baseColumns
})

// Methods
const loadTrackingCustomers = async () => {
  try {
    loading.value = true
    loadError.value = null

    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      per_page: itemsPerPage.value.toString(),
      search: searchQuery.value,
    })

    if (levelFilter.value) {
      params.append('customer_level', levelFilter.value)
    }
    if (regionFilter.value) {
      params.append('region', regionFilter.value)
    }
    if (assigneeFilter.value && !authStore.isSales) {
      params.append('assigned_to', assigneeFilter.value)
    }

    const result = await get('/tracking/customers', Object.fromEntries(params))
    if (result.error) {
      throw new Error(result.error.message || result.error.error || '載入客戶資料失敗')
    }
    trackingCustomers.value = result.data.data
    
    // Extract unique regions
    const uniqueRegions = [...new Set(result.data.data.map(c => c.region).filter(Boolean))]
    regions.value = uniqueRegions
    
  } catch (error) {
    console.error('載入追蹤客戶失敗:', error)
    loadError.value = '載入客戶資料失敗'
    notificationsStore.addNotification({
      type: 'error',
      title: '載入客戶資料失敗',
      message: error.message
    })
  } finally {
    loading.value = false
  }
}

const loadSalesUsers = async () => {
  try {
    const result = await get('/tracking/sales-users')
    if (result.error) {
      throw new Error(result.error.message || result.error.error || '載入業務清單失敗')
    }
    salesUsers.value = result.data.data || result.data || []
  } catch (error) {
    console.error('載入業務清單失敗:', error)
    salesUsers.value = []
  }
}

const openLevelEditModal = (customer) => {
  levelEditModal.customer = customer
  levelEditModal.level = customer.customer_level || 'B'
  levelEditModal.open = true
}

const closeLevelEditModal = () => {
  levelEditModal.open = false
  levelEditModal.customer = null
  levelEditModal.level = 'B'
  levelEditModal.saving = false
}

const saveCustomerLevel = async () => {
  try {
    levelEditModal.saving = true

    const result = await patch(`/customers/${levelEditModal.customer.id}/level`, {
      customer_level: levelEditModal.level
    })

    if (result.error) {
      throw new Error(result.error.message || result.error.error || '更新客戶等級失敗')
    }

    // Update local data
    const customerIndex = trackingCustomers.value.findIndex(c => c.id === levelEditModal.customer.id)
    if (customerIndex !== -1) {
      trackingCustomers.value[customerIndex].customer_level = levelEditModal.level
    }

    notificationsStore.addNotification({
      type: 'success',
      title: '客戶等級已更新',
      message: `${levelEditModal.customer.name} 的等級已更新為 ${levelEditModal.level}級`
    })

    closeLevelEditModal()
  } catch (error) {
    console.error('更新客戶等級失敗:', error)
    notificationsStore.addNotification({
      type: 'error',
      title: '更新失敗',
      message: error.message || '無法更新客戶等級'
    })
  } finally {
    levelEditModal.saving = false
  }
}

const viewCustomerDetails = (customer) => {
  // Open customer details modal instead of navigating
  selectedCustomer.value = customer
  showViewModal.value = true
}

const closeViewModal = () => {
  showViewModal.value = false
  selectedCustomer.value = null
}

// Utility functions
const getStatusClass = (status) => {
  const classes = {
    'new': 'bg-blue-100 text-blue-800',
    'contacted': 'bg-yellow-100 text-yellow-800',
    'interested': 'bg-green-100 text-green-800',
    'not_interested': 'bg-red-100 text-red-800',
    'converted': 'bg-purple-100 text-purple-800'
  }
  return classes[status] || classes.new
}

const getStatusLabel = (status) => {
  const labels = {
    'new': '新客戶',
    'contacted': '已聯絡',
    'interested': '有興趣',
    'not_interested': '無興趣',
    'converted': '已成交'
  }
  return labels[status] || status
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('zh-TW')
}

const formatTime = (dateString) => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleTimeString('zh-TW', { 
    hour: '2-digit', 
    minute: '2-digit' 
  })
}

const isOverdue = (dateString) => {
  return new Date(dateString) < new Date()
}

const isToday = (dateString) => {
  const today = new Date().toDateString()
  return new Date(dateString).toDateString() === today
}

const isFuture = (dateString) => {
  return new Date(dateString) > new Date()
}

const getContactDateStatus = (dateString) => {
  if (isOverdue(dateString)) return '逾期'
  if (isToday(dateString)) return '今天'
  return '待聯絡'
}

// Event handlers
const handleSearch = (query) => {
  searchQuery.value = query
  currentPage.value = 1
  loadTrackingCustomers()
}

const handlePageChange = (page) => {
  currentPage.value = page
  loadTrackingCustomers()
}

const handlePageSizeChange = (size) => {
  itemsPerPage.value = size
  currentPage.value = 1
  loadTrackingCustomers()
}

// Watchers for filters
watch([levelFilter, regionFilter, assigneeFilter], () => {
  currentPage.value = 1
  loadTrackingCustomers()
})

// Initialize
onMounted(() => {
  loadTrackingCustomers()
  if (!authStore.isSales) {
    loadSalesUsers()
  }
})
</script>