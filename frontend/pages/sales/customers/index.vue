<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 ">客戶資料管理</h1>
        <p class="text-gray-600 mt-2">
          <span v-if="authStore.isSales">您的客戶清單</span>
          <span v-else>所有客戶資料總覽</span>
        </p>
      </div>
      
      <UModal v-model="createModalOpen">
        <CustomerForm :model-value="editing || {}" @save="handleSave" @cancel="closeCreateModal" />
      </UModal>
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
    <DataTable
      title="客戶清單"
      :columns="customerTableColumns"
      :data="filteredCustomers"
      :loading="loading"
      :error="loadError"
      :search-query="searchQuery"
      search-placeholder="搜尋客戶..."
      :current-page="currentPage"
      :items-per-page="itemsPerPage"
      loading-text="載入中..."
      empty-text="沒有客戶資料"
      @search="handleCustomerSearch"
      @refresh="loadCustomers"
      @retry="loadCustomers"
      @page-change="handleCustomerPageChange"
      @page-size-change="handleCustomerPageSizeChange"
    >
      <!-- Filter Controls -->
      <template #filters>
        <select
          v-model="statusFilter"
          class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">所有狀態</option>
          <option v-for="(label, value) in getStatusOptions()" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
      </template>
      
      <!-- Action Buttons -->
      <template #actions>
        <button
          v-if="authStore.hasPermission('customer_management')"
          @click="openCreateModal"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center space-x-2"
        >
          <PlusIcon class="w-5 h-5" />
          <span>新增客戶</span>
        </button>
      </template>
      
      <!-- Customer Info Cell -->
      <template #cell-customer_info="{ item }">
        <div class="flex items-center">
          <div class="flex-shrink-0 w-10 h-10">
            <img 
              :src="`https://ui-avatars.com/api/?name=${item.name}&background=6366f1&color=fff`" 
              :alt="item.name"
              class="w-10 h-10 rounded-full"
            />
          </div>
          <div class="ml-4">
            <div class="text-sm font-medium text-gray-900 ">
              {{ item.name }}
            </div>
            <div class="text-sm text-gray-500 ">
              {{ item.region || '未填寫地區' }}
            </div>
          </div>
        </div>
      </template>
      
      <!-- Contact Info Cell -->
      <template #cell-contact_info="{ item }">
        <div>
          <div class="text-sm text-gray-900 ">{{ item.email || '未提供' }}</div>
          <div class="text-sm text-gray-500 ">{{ item.phone }}</div>
        </div>
      </template>
      
      <!-- Status Cell -->
      <template #cell-status="{ item }">
        <span 
          class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
          :class="getStatusClass(item.status)"
        >
          {{ getStatusText(item.status) }}
        </span>
      </template>
      
      <!-- Case Status Cell -->
      <template #cell-case_status="{ item }">
        <span v-if="item.case_status" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full" 
              :class="getCaseStatusClass(item.case_status)">
          {{ getCaseStatusText(item.case_status) }}
        </span>
        <span v-else class="text-xs text-gray-400">無案件</span>
      </template>
      
      <!-- LINE Status Cell -->
      <template #cell-line_status="{ item }">
        <div class="flex items-center space-x-2">
          <div v-if="item.line_user_id" class="flex items-center space-x-1">
            <div class="w-2 h-2 bg-green-400 rounded-full"></div>
            <span class="text-xs text-green-600 ">已綁定</span>
          </div>
          <div v-else class="flex items-center space-x-1">
            <div class="w-2 h-2 bg-gray-300 rounded-full"></div>
            <span class="text-xs text-gray-500">未綁定</span>
          </div>
          <button 
            v-if="item.line_user_id"
            @click="checkLineFriend(item)"
            class="text-xs text-blue-600 hover:text-blue-800"
            title="檢查好友狀態"
          >
            檢查
          </button>
        </div>
      </template>
      
      <!-- Assigned User Cell -->
      <template #cell-assigned_user="{ item }">
        <span>{{ item.assigned_user?.name || '未分配' }}</span>
      </template>
      
      <!-- Actions Cell -->
      <template #cell-actions="{ item }">
        <div class="flex items-center space-x-2 justify-end">
          <button 
            @click="viewCustomer(item)"
            class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-all duration-200 group relative"
            title="查看客戶詳情"
          >
            <EyeIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              查看
            </span>
          </button>
          <button 
            v-if="authStore.hasPermission('customer_management') || item.assigned_to === authStore.user?.id"
            @click="editCustomer(item)"
            class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-50 rounded-lg transition-all duration-200 group relative"
            title="編輯客戶資料"
          >
            <PencilIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              編輯
            </span>
          </button>
          <button 
            v-if="authStore.hasPermission('customer_management')"
            @click="openAssignModal(item)"
            class="p-2 text-green-600 hover:text-green-800 hover:bg-green-50 rounded-lg transition-all duration-200 group relative"
            title="指派負責業務"
          >
            <UserPlusIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              指派
            </span>
          </button>
          <button 
            v-if="authStore.hasPermission('customer_management')"
            @click="confirmDeleteCustomer(item)"
            class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-all duration-200 group relative"
            title="刪除客戶"
          >
            <TrashIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              刪除
            </span>
          </button>
        </div>
      </template>
    </DataTable>

    <!-- 新增客戶模態窗口 -->
    <div 
      v-if="showCreateModal" 
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="closeCreateModal"
    >
      <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900 ">新增客戶</h3>
          <button 
            @click="closeCreateModal"
            class="text-gray-400 hover:text-gray-600 "
          >
            ✕
          </button>
        </div>
        
        <form @submit.prevent="submitCreateForm" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              客戶姓名 *
            </label>
            <input
              v-model="customerForm.name"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              電話 *
            </label>
            <input
              v-model="customerForm.phone"
              type="tel"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              電子郵件
            </label>
            <input
              v-model="customerForm.email"
              type="email"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              地區
            </label>
            <input
              v-model="customerForm.region"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              備註
            </label>
            <textarea
              v-model="customerForm.notes"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            ></textarea>
          </div>
          
          <div class="flex justify-end space-x-3 pt-4">
            <button
              type="button"
              @click="closeCreateModal"
              class="px-4 py-2 text-gray-600 hover:text-gray-800 "
            >
              取消
            </button>
            <button
              type="submit"
              :disabled="creating"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
              {{ creating ? '創建中...' : '創建客戶' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- 編輯客戶模態窗口 -->
    <div 
      v-if="showEditModal" 
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="closeEditModal"
    >
      <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900 ">編輯客戶</h3>
          <button 
            @click="closeEditModal"
            class="text-gray-400 hover:text-gray-600 "
          >
            ✕
          </button>
        </div>
        
        <form @submit.prevent="submitEditForm" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              客戶姓名 *
            </label>
            <input
              v-model="customerForm.name"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              電話 *
            </label>
            <input
              v-model="customerForm.phone"
              type="tel"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              電子郵件
            </label>
            <input
              v-model="customerForm.email"
              type="email"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              地區
            </label>
            <input
              v-model="customerForm.region"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              狀態
            </label>
            <select
              v-model="customerForm.status"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option v-for="(label, value) in getStatusOptions()" :key="value" :value="value">
                {{ label }}
              </option>
            </select>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              備註
            </label>
            <textarea
              v-model="customerForm.notes"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
            ></textarea>
          </div>
          
          <div class="flex justify-end space-x-3 pt-4">
            <button
              type="button"
              @click="closeEditModal"
              class="px-4 py-2 text-gray-600 hover:text-gray-800 "
            >
              取消
            </button>
            <button
              type="submit"
              :disabled="updating"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
              {{ updating ? '更新中...' : '更新客戶' }}
            </button>
          </div>
        </form>
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
          <h3 class="text-lg font-semibold text-gray-900 ">客戶詳情</h3>
          <button 
            @click="closeViewModal"
            class="text-gray-400 hover:text-gray-600 "
          >
            ✕
          </button>
        </div>
        
        <div v-if="selectedCustomer" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 ">客戶姓名</label>
              <p class="text-gray-900 ">{{ selectedCustomer.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 ">電話</label>
              <p class="text-gray-900 ">{{ selectedCustomer.phone }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 ">電子郵件</label>
              <p class="text-gray-900 ">{{ selectedCustomer.email || '未提供' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 ">地區</label>
              <p class="text-gray-900 ">{{ selectedCustomer.region || '未填寫' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 ">狀態</label>
              <span 
                class="inline-flex px-2 py-1 text-sm font-semibold rounded-full"
                :class="getStatusClass(selectedCustomer.status)"
              >
                {{ getStatusText(selectedCustomer.status) }}
              </span>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 ">負責業務</label>
              <p class="text-gray-900 ">{{ selectedCustomer.assigned_user?.name || '未分配' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 ">LINE 用戶</label>
              <p class="text-gray-900 ">
                {{ selectedCustomer.line_display_name || '未綁定' }}
                <span v-if="selectedCustomer.line_user_id" class="text-xs text-gray-500">
                  ({{ selectedCustomer.line_user_id }})
                </span>
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 ">建立時間</label>
              <p class="text-gray-900 ">{{ formatDate(selectedCustomer.created_at) }}</p>
            </div>
          </div>
          
          <div v-if="selectedCustomer.notes">
            <label class="block text-sm font-medium text-gray-700 ">備註</label>
            <p class="text-gray-900 whitespace-pre-wrap">{{ selectedCustomer.notes }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- 指派業務模態窗口 -->
    <div 
      v-if="showAssignModal" 
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="closeAssignModal"
    >
      <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900 ">指派業務</h3>
          <button 
            @click="closeAssignModal"
            class="text-gray-400 hover:text-gray-600 "
          >
            ✕
          </button>
        </div>
        
        <div v-if="assigningCustomer" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              客戶：{{ assigningCustomer.name }}
            </label>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              目前負責業務：{{ assigningCustomer.assigned_user?.name || '未分配' }}
            </label>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              選擇新的負責業務 * ({{ salesUsers.length }} 位業務人員)
            </label>
            <select
              v-model="selectedAssignUser"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            >
              <option value="">請選擇業務人員</option>
              <option value="null">取消分配</option>
              <option v-for="user in salesUsers" :key="user.id" :value="user.id">
                {{ user.name }} (ID: {{ user.id }})
              </option>
            </select>
            <!-- 除錯資訊 -->
            <div v-if="salesUsers.length === 0" class="text-sm text-red-500 mt-1">
              未載入任何業務人員資料
            </div>
          </div>
          
          <div class="flex justify-end space-x-3 pt-4">
            <button
              type="button"
              @click="closeAssignModal"
              class="px-4 py-2 text-gray-600 hover:text-gray-800 "
            >
              取消
            </button>
            <button
              @click="submitAssignForm"
              :disabled="assigning || !selectedAssignUser"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
            >
              {{ assigning ? '指派中...' : '確認指派' }}
            </button>
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
  ChartBarIcon,
  EyeIcon,
  PencilIcon,
  UserPlusIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'

// 明確匯入組件
import StatsCard from '~/components/StatsCard.vue'
import CustomerForm from '~/components/CustomerForm.vue'
import DataTable from '~/components/DataTable.vue'
import { formatters } from '~/utils/tableColumns'

definePageMeta({
  middleware: ['auth', 'role']
})

const authStore = useAuthStore()
const { alert, success, error: showError } = useNotification()
const { 
  getCustomers, 
  checkLineFriendStatus, 
  getStatusOptions,
  createCustomer,
  updateCustomer,
  deleteCustomer,
  assignCustomer
} = useCustomers()

const { getUsers } = useUsers()

// 搜尋和篩選
const searchQuery = ref('')
const statusFilter = ref('')

// 載入狀態
const loading = ref(false)
const loadError = ref(null)

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
const showViewModal = ref(false)
const showAssignModal = ref(false)
const editingCustomer = ref(null)
const selectedCustomer = ref(null)
const assigningCustomer = ref(null)

// 表單提交狀態
const creating = ref(false)
const updating = ref(false)
const assigning = ref(false)
const deleting = ref(false)

// 指派功能相關數據
const salesUsers = ref([])
const selectedAssignUser = ref('')







const getCaseStatusText = (status) => {
  switch (status) {
    case 'submitted': return '已送件'
    case 'approved': return '已核准'
    case 'rejected': return '已婉拒'
    case 'disbursed': return '已撥款'
    default: return status
  }
}

const getCaseStatusClass = (status) => {
  switch (status) {
    case 'submitted': return 'bg-blue-100 text-blue-800'
    case 'approved': return 'bg-green-100 text-green-800'
    case 'rejected': return 'bg-red-100 text-red-800'
    case 'disbursed': return 'bg-purple-100 text-purple-800'
    default: return 'bg-gray-100 text-gray-800'
  }
}


// 表單數據
const customerForm = ref({
  name: '',
  phone: '',
  email: '',
  region: '',
  website_source: '',
  channel: '',
  notes: '',
  assigned_to: null,
  status: 'new'
})

// 載入客戶數據
const loadCustomers = async () => {
  loading.value = true
  loadError.value = null
  
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
      loadError.value = apiError.message
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
    loadError.value = '載入客戶數據失敗'
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
const itemsPerPage = ref(5) // Default to 5 items per page as requested

const totalPages = computed(() => Math.ceil(filteredCustomers.value.length / itemsPerPage.value))

const paginatedCustomers = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredCustomers.value.slice(start, end)
})

// Customer Table configuration
const customerTableColumns = computed(() => {
  const baseColumns = [
    {
      key: 'customer_info',
      title: '客戶資訊',
      sortable: false,
      width: '250px'
    },
    {
      key: 'contact_info',
      title: '聯絡方式',
      sortable: false,
      width: '200px'
    },
    {
      key: 'status',
      title: '狀態',
      sortable: true,
      width: '120px'
    },
    {
      key: 'case_status',
      title: '案件狀態',
      sortable: true,
      width: '120px'
    },
    {
      key: 'line_status',
      title: 'LINE 狀態',
      sortable: false,
      width: '120px'
    },
    {
      key: 'updated_at',
      title: '最後聯絡',
      sortable: true,
      width: '140px',
      formatter: formatters.date
    }
  ]
  
  // Add assigned user column if not sales staff
  if (!authStore.isSales) {
    baseColumns.push({
      key: 'assigned_user',
      title: '負責業務',
      sortable: true,
      width: '120px'
    })
  }
  
  // Add actions column
  baseColumns.push({
    key: 'actions',
    title: '操作',
    sortable: false,
    width: '200px'
  })
  
  return baseColumns
})

// DataTable event handlers
const handleCustomerSearch = (query) => {
  searchQuery.value = query
  // The search will be handled by the existing watch function
}

const handleCustomerPageChange = (page) => {
  currentPage.value = page
}

const handleCustomerPageSizeChange = (size) => {
  itemsPerPage.value = size
  currentPage.value = 1 // Reset to first page
}

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
    
    // 待替換為更好的通知系統
    console.log('LINE好友狀態檢查結果:', data)
    
  } catch (err) {
    console.error('檢查LINE好友狀態錯誤:', err)
  }
}

// 搜尋防抖動
let searchTimeout = null
watch([searchQuery, statusFilter], () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }
  searchTimeout = setTimeout(() => {
    loadCustomers()
  }, 300)
})

// 模態窗口控制函數
const openCreateModal = () => {
  // 重置表單
  customerForm.value = {
    name: '',
    phone: '',
    email: '',
    region: '',
    website_source: '',
    channel: '',
    notes: '',
    assigned_to: null,
    status: 'new'
  }
  showCreateModal.value = true
}

const closeCreateModal = () => {
  showCreateModal.value = false
}

const openEditModal = (customer) => {
  editingCustomer.value = customer
  // 填充表單數據
  customerForm.value = {
    name: customer.name || '',
    phone: customer.phone || '',
    email: customer.email || '',
    region: customer.region || '',
    website_source: customer.website_source || '',
    channel: customer.channel || '',
    notes: customer.notes || '',
    assigned_to: customer.assigned_to,
    status: customer.status || 'new'
  }
  showEditModal.value = true
}

const closeEditModal = () => {
  showEditModal.value = false
  editingCustomer.value = null
}

const viewCustomer = (customer) => {
  selectedCustomer.value = customer
  showViewModal.value = true
}

const editCustomer = (customer) => {
  openEditModal(customer)
}

const closeViewModal = () => {
  showViewModal.value = false
  selectedCustomer.value = null
}

// 指派業務相關函數
const openAssignModal = async (customer) => {
  assigningCustomer.value = customer
  selectedAssignUser.value = ''
  
  // 載入業務人員列表
  await loadSalesUsers()
  
  showAssignModal.value = true
}

const closeAssignModal = () => {
  showAssignModal.value = false
  assigningCustomer.value = null
  selectedAssignUser.value = ''
}

const loadSalesUsers = async () => {
  try {
    console.log('開始載入業務人員列表...')
    const { success, users, error } = await getUsers({ role: 'staff' })
    
    console.log('API 回應:', { success, users, error })
    
    if (!success || error) {
      console.error('載入業務人員失敗:', error?.message || '未知錯誤')
      return
    }
    
    salesUsers.value = users || []
    console.log('載入的業務人員:', salesUsers.value)
    
  } catch (err) {
    console.error('載入業務人員錯誤:', err)
  }
}

const submitAssignForm = async () => {
  if (!assigningCustomer.value) return
  
  assigning.value = true
  try {
    const assignToId = selectedAssignUser.value === 'null' ? null : selectedAssignUser.value
    
    const { data, error: apiError } = await assignCustomer(assigningCustomer.value.id, assignToId)
    
    if (apiError) {
      await showError('指派業務失敗：' + apiError.message)
      return
    }
    
    await success('指派業務成功')
    closeAssignModal()
    loadCustomers() // 重新載入列表
    
  } catch (err) {
    console.error('指派業務錯誤:', err)
    await showError('指派業務時發生錯誤')
  } finally {
    assigning.value = false
  }
}

// 刪除客戶功能
const confirmDeleteCustomer = (customer) => {
  if (confirm(`確定要刪除客戶「${customer.name}」嗎？此操作無法復原。`)) {
    deleteCustomerRecord(customer)
  }
}

const deleteCustomerRecord = async (customer) => {
  deleting.value = true
  try {
    const { data, error: apiError } = await deleteCustomer(customer.id)
    
    if (apiError) {
      await showError('刪除客戶失敗：' + apiError.message)
      return
    }
    
    await success('客戶刪除成功')
    loadCustomers() // 重新載入列表
    
  } catch (err) {
    console.error('刪除客戶錯誤:', err)
    await showError('刪除客戶時發生錯誤')
  } finally {
    deleting.value = false
  }
}

// 表單提交函數
const submitCreateForm = async () => {
  creating.value = true
  try {
    const { data, error: apiError } = await createCustomer(customerForm.value)
    
    if (apiError) {
      await showError('創建客戶失敗：' + apiError.message)
      return
    }
    
    await success('客戶創建成功')
    closeCreateModal()
    loadCustomers() // 重新載入列表
    
  } catch (err) {
    console.error('Create customer error:', err)
    await showError('創建客戶時發生錯誤')
  } finally {
    creating.value = false
  }
}

const submitEditForm = async () => {
  if (!editingCustomer.value) return
  
  updating.value = true
  try {
    const { data, error: apiError } = await updateCustomer(editingCustomer.value.id, customerForm.value)
    
    if (apiError) {
      await showError('更新客戶失敗：' + apiError.message)
      return
    }
    
    await success('客戶更新成功')
    closeEditModal()
    loadCustomers() // 重新載入列表
    
  } catch (err) {
    console.error('Update customer error:', err)
    await showError('更新客戶時發生錯誤')
  } finally {
    updating.value = false
  }
}

// 狀態樣式
const getStatusClass = (status) => {
  const statusOptions = getStatusOptions()
  const classes = {
    'new': 'bg-blue-100 text-blue-800',
    'contacted': 'bg-yellow-100 text-yellow-800',
    'interested': 'bg-green-100 text-green-800',
    'not_interested': 'bg-red-100 text-red-800',
    'invalid': 'bg-gray-100 text-gray-800',
    'converted': 'bg-purple-100 text-purple-800'
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