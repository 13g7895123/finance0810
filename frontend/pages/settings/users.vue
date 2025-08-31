<template>
  <div class="space-y-6">
    <!-- Access Denied State -->
    <div v-if="!authStore.hasPermission('user.view') && !authStore.isAdmin && !authStore.isManager" class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
      <ShieldExclamationIcon class="w-12 h-12 text-red-500 mx-auto mb-4" />
      <h3 class="text-lg font-medium text-gray-900 mb-2">存取被拒絕</h3>
      <p class="text-gray-600">您沒有權限使用此功能</p>
    </div>
    
    <!-- Main DataTable -->
    <DataTable
      v-else
      title="用戶管理"
      :columns="tableColumns"
      :data="filteredUsers"
      :loading="loading"
      :search-query="searchQuery"
      search-placeholder="搜尋用戶..."
      :current-page="currentPage"
      :items-per-page="perPage"
      loading-text="載入用戶資料中..."
      empty-text="沒有找到用戶"
      @search="handleSearch"
      @refresh="refreshUsers"
      @retry="loadUsers"
      @page-change="handlePageChange"
      @page-size-change="handlePageSizeChange"
    >
      <!-- Action Buttons -->
      <template #actions>
        <button
          @click="showAddModal = true"
          class="inline-flex items-center px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors duration-200"
        >
          <PlusIcon class="w-5 h-5 mr-2" />
          {{ t('auth.add_user') }}
        </button>
      </template>
      
      <!-- User Info Cell -->
      <template #cell-user="{ item }">
        <div class="flex items-center">
          <img 
            :src="item.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(item.name)}&background=6366f1&color=fff`" 
            :alt="item.name" 
            class="w-10 h-10 rounded-full"
          />
          <div class="ml-4">
            <div class="text-sm font-medium text-gray-900">{{ item.name }}</div>
            <div class="text-sm text-gray-500">{{ item.email }}</div>
          </div>
        </div>
      </template>
      
      <!-- Role Cell -->
      <template #cell-role="{ item }">
        <span 
          class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
          :class="{
            'bg-purple-100 text-purple-800': item.roles?.[0]?.name === 'admin' || item.roles?.[0]?.name === 'executive',
            'bg-blue-100 text-blue-800': item.roles?.[0]?.name === 'manager',
            'bg-green-100 text-green-800': item.roles?.[0]?.name === 'staff'
          }"
        >
          {{ item.roles?.[0]?.display_name || item.roles?.[0]?.name || '無角色' }}
        </span>
      </template>
      
      <!-- Status Cell -->
      <template #cell-status="{ item }">
        <span 
          class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
          :class="{
            'bg-green-600 text-white': item.status === 'active',
            'bg-red-600 text-white': item.status === 'inactive',
            'bg-yellow-600 text-white': item.status === 'suspended'
          }"
        >
          {{ t(`auth.status_${item.status}`) }}
        </span>
      </template>
      
      <!-- Actions Cell -->
      <template #cell-actions="{ item }">
        <div class="flex items-center space-x-2 justify-end">
          <!-- Toggle Status -->
          <button
            v-if="item.id !== authStore.user?.id"
            @click="toggleStatus(item)"
            class="p-2 rounded-lg transition-all duration-200 group relative"
            :class="item.status === 'active' ? 'text-orange-600 hover:text-orange-800 hover:bg-orange-50' : 'text-green-600 hover:text-green-800 hover:bg-green-50'"
            :title="item.status === 'active' ? '停用用戶' : '啟用用戶'"
          >
            <PauseIcon v-if="item.status === 'active'" class="w-4 h-4" />
            <PlayIcon v-else class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              {{ item.status === 'active' ? '停用' : '啟用' }}
            </span>
          </button>
          
          <!-- Assign Customers (Only for sales staff) -->
          <button
            v-if="item.roles?.[0]?.name === 'staff' && authStore.hasPermission('customer_management')"
            @click="openAssignCustomersModal(item)"
            class="p-2 text-green-600 hover:text-green-800 hover:bg-green-50 rounded-lg transition-all duration-200 group relative"
            title="指派客戶"
          >
            <UserPlusIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              指派客戶
            </span>
          </button>
          
          <!-- Edit -->
          <button
            @click="editUser(item)"
            class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-all duration-200 group relative"
            title="編輯用戶"
          >
            <PencilIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              編輯
            </span>
          </button>
          
          <!-- Delete -->
          <button
            v-if="item.id !== authStore.user?.id"
            @click="deleteUserConfirm(item)"
            class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-all duration-200 group relative"
            title="刪除用戶"
          >
            <TrashIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              刪除
            </span>
          </button>
        </div>
      </template>
    </DataTable>
  </div>

  <!-- Add User Modal - Moved outside main container -->
  <div v-if="showAddModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4">
        {{ t('auth.add_user') }}
      </h3>
      
      <div class="space-y-4">
        <!-- Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ t('auth.full_name') }}
          </label>
          <input
            v-model="addForm.name"
            type="text"
            required
            class="w-full px-3 py-2 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white text-gray-900"
          />
        </div>

        <!-- Username -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ t('auth.username') }}
          </label>
          <input
            v-model="addForm.username"
            type="text"
            required
            class="w-full px-3 py-2 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white text-gray-900"
          />
        </div>
        
        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ t('auth.email') }}
          </label>
          <input
            v-model="addForm.email"
            type="email"
            required
            class="w-full px-3 py-2 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white text-gray-900"
          />
        </div>

        <!-- Password -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ t('auth.password') }}
          </label>
          <input
            v-model="addForm.password"
            type="password"
            required
            minlength="6"
            class="w-full px-3 py-2 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white text-gray-900"
          />
        </div>

        <!-- Confirm Password -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ t('auth.confirm_password') }}
          </label>
          <input
            v-model="addForm.password_confirmation"
            type="password"
            required
            minlength="6"
            class="w-full px-3 py-2 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white text-gray-900"
          />
        </div>

        <!-- Role -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ t('auth.role') }}
          </label>
          <select
            v-model="addForm.role"
            required
            class="w-full px-3 py-2 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white text-gray-900"
          >
            <option value="">選擇角色</option>
            <option v-for="role in roles" :key="role.id" :value="role.name">
              {{ role.display_name }}
            </option>
          </select>
        </div>

        <!-- Status -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ t('auth.status') }}
          </label>
          <select
            v-model="addForm.status"
            class="w-full px-3 py-2 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white text-gray-900"
          >
            <option value="active">啟用</option>
            <option value="inactive">停用</option>
            <option value="suspended">暫停</option>
          </select>
        </div>
      </div>

      <!-- Modal Actions -->
      <div class="flex justify-end space-x-3 mt-6">
        <button
          @click="showAddModal = false; resetAddForm()"
          class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200"
        >
          {{ t('common.cancel') }}
        </button>
        <button
          @click="addUser"
          class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors duration-200"
        >
          {{ t('common.create') }}
        </button>
      </div>
    </div>
  </div>

  <!-- Edit User Modal - Moved outside main container -->
  <div v-if="showEditModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4">
        {{ t('auth.edit_user') }}
      </h3>
      
      <div class="space-y-4">
        <!-- Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ t('auth.full_name') }}
          </label>
          <input
            v-model="editForm.name"
            type="text"
            class="w-full px-3 py-2 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white text-gray-900"
          />
        </div>
        
        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ t('auth.email') }}
          </label>
          <input
            v-model="editForm.email"
            type="email"
            class="w-full px-3 py-2 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white text-gray-900"
          />
        </div>

        <!-- Role -->
        <div v-if="editForm.id !== authStore.user?.id">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ t('auth.role') }}
          </label>
          <select
            v-model="editForm.role"
            class="w-full px-3 py-2 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white text-gray-900"
          >
            <option v-for="role in roles" :key="role.id" :value="role.name">
              {{ role.display_name }}
            </option>
          </select>
        </div>
      </div>

      <!-- Modal Actions -->
      <div class="flex justify-end space-x-3 mt-6">
        <button
          @click="showEditModal = false"
          class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200"
        >
          {{ t('common.cancel') }}
        </button>
        <button
          @click="saveUser"
          class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors duration-200"
        >
          {{ t('common.save') }}
        </button>
      </div>
    </div>
  </div>

  <!-- Assign Customers Modal -->
  <div v-if="showAssignCustomersModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full p-6 max-h-[90vh] overflow-y-auto">
      <h3 class="text-lg font-medium text-gray-900 mb-4">
        指派客戶給 {{ selectedStaff?.name }}
      </h3>
      
      <!-- Search for customers -->
      <div class="mb-4">
        <div class="relative">
          <input
            v-model="customerSearchQuery"
            type="text"
            placeholder="搜尋客戶..."
            class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white text-gray-900"
          />
          <MagnifyingGlassIcon class="w-5 h-5 text-gray-500 absolute left-3 top-2.5" />
        </div>
      </div>

      <!-- Customer assignment options -->
      <div class="mb-4 flex space-x-4">
        <label class="flex items-center">
          <input
            type="radio"
            v-model="assignmentMode"
            value="unassigned"
            class="mr-2"
          />
          <span class="text-sm text-gray-700">僅顯示未分配客戶</span>
        </label>
        <label class="flex items-center">
          <input
            type="radio"
            v-model="assignmentMode"
            value="all"
            class="mr-2"
          />
          <span class="text-sm text-gray-700">顯示所有客戶</span>
        </label>
      </div>
      
      <!-- Loading state -->
      <div v-if="loadingCustomers" class="text-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500 mx-auto mb-4"></div>
        <p class="text-gray-600">載入客戶資料中...</p>
      </div>
      
      <!-- Customers list -->
      <div v-else class="space-y-2 max-h-96 overflow-y-auto">
        <div
          v-for="customer in filteredCustomersForAssignment"
          :key="customer.id"
          class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50"
        >
          <div class="flex items-center space-x-3">
            <input
              type="checkbox"
              :value="customer.id"
              v-model="selectedCustomerIds"
              class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
            />
            <div>
              <div class="font-medium text-gray-900">{{ customer.name }}</div>
              <div class="text-sm text-gray-500">{{ customer.phone }} · {{ customer.region || '未填寫地區' }}</div>
            </div>
          </div>
          <div class="text-sm text-gray-500">
            <span v-if="customer.assigned_user">
              目前負責：{{ customer.assigned_user.name }}
            </span>
            <span v-else class="text-yellow-600">
              未分配
            </span>
          </div>
        </div>
        
        <!-- No customers found -->
        <div v-if="filteredCustomersForAssignment.length === 0" class="text-center py-8">
          <p class="text-gray-500">沒有找到符合條件的客戶</p>
        </div>
      </div>

      <!-- Assignment summary -->
      <div v-if="selectedCustomerIds.length > 0" class="mt-4 p-3 bg-blue-50 rounded-lg">
        <p class="text-sm text-blue-800">
          已選擇 {{ selectedCustomerIds.length }} 位客戶將指派給 {{ selectedStaff?.name }}
        </p>
      </div>

      <!-- Modal Actions -->
      <div class="flex justify-end space-x-3 mt-6">
        <button
          @click="closeAssignCustomersModal"
          class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200"
        >
          取消
        </button>
        <button
          @click="assignCustomersToStaff"
          :disabled="selectedCustomerIds.length === 0 || assigningCustomers"
          class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
        >
          {{ assigningCustomers ? '指派中...' : `指派 ${selectedCustomerIds.length} 位客戶` }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  MagnifyingGlassIcon,
  ShieldExclamationIcon,
  UsersIcon,
  PlusIcon,
  ArrowPathIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  PlayIcon,
  PauseIcon,
  UserPlusIcon,
  PencilIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'
import DataTable from '~/components/DataTable.vue'
import { formatters } from '~/utils/tableColumns'

definePageMeta({
  middleware: 'auth'
})

const { t } = useI18n()
const authStore = useAuthStore()
const { alert, success, error: showError } = useNotification()
const { getUsers, createUser, updateUser, deleteUser, getRoles, assignRole } = useUserManagement()
const { getCustomers, assignCustomer } = useCustomers()

const searchQuery = ref('')
const showEditModal = ref(false)
const showAddModal = ref(false)
const editForm = ref({})
const addForm = ref({
  name: '',
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: '',
  status: 'active'
})
const loading = ref(false)
const refreshing = ref(false)
const users = ref([])
const roles = ref([])

// Customer assignment state
const showAssignCustomersModal = ref(false)
const selectedStaff = ref(null)
const customerSearchQuery = ref('')
const assignmentMode = ref('unassigned')
const loadingCustomers = ref(false)
const assigningCustomers = ref(false)
const customers = ref([])
const selectedCustomerIds = ref([])

// Pagination state
const currentPage = ref(1)
const totalPages = ref(1)
const perPage = ref(10)
const totalUsers = ref(0)

// Table configuration
const tableColumns = [
  {
    key: 'user',
    title: t('auth.user'),
    sortable: false,
    width: '300px'
  },
  {
    key: 'role',
    title: t('auth.role'),
    sortable: true,
    width: '150px'
  },
  {
    key: 'status',
    title: t('auth.status'),
    sortable: true,
    width: '120px'
  },
  {
    key: 'last_login_at',
    title: t('auth.last_login'),
    sortable: true,
    width: '180px',
    formatter: formatters.datetime
  },
  {
    key: 'actions',
    title: t('auth.actions'),
    sortable: false,
    width: '300px'
  }
]

// Event handlers for DataTable
const handleSearch = (query) => {
  searchQuery.value = query
  loadUsers(1) // Reset to first page when searching
}

const handlePageChange = (page) => {
  loadUsers(page)
}

const handlePageSizeChange = (size) => {
  perPage.value = size
  loadUsers(1) // Reset to first page when changing page size
}

// 載入用戶數據
const loadUsers = async (page = 1) => {
  try {
    loading.value = true
    const response = await getUsers({ 
      search: searchQuery.value,
      page: page,
      per_page: perPage.value
    })
    
    // Handle different response formats
    if (response.data && Array.isArray(response.data)) {
      users.value = response.data
      currentPage.value = response.current_page || page
      totalPages.value = response.last_page || Math.ceil((response.total || response.data.length) / perPage.value)
      totalUsers.value = response.total || response.data.length
    } else if (Array.isArray(response)) {
      users.value = response
      currentPage.value = page
      totalPages.value = Math.ceil(response.length / perPage.value)
      totalUsers.value = response.length
    } else {
      users.value = []
    }
  } catch (error) {
    console.error('Failed to load users:', error)
  } finally {
    loading.value = false
  }
}

// 載入角色數據
const loadRoles = async () => {
  try {
    const response = await getRoles()
    const rolesData = Array.isArray(response) ? response : []
    
    // 去重處理，確保角色ID是唯一的
    const uniqueRoles = rolesData.filter((role, index, self) => 
      index === self.findIndex(r => r.id === role.id)
    )
    
    roles.value = uniqueRoles
  } catch (error) {
    console.error('Failed to load roles:', error)
    roles.value = []
  }
}

// Filter users based on search query - 搜索功能由API處理
const filteredUsers = computed(() => users.value)

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

// 監聽搜索查詢變化
const debounce = (func, delay) => {
  let timeoutId
  return (...args) => {
    clearTimeout(timeoutId)
    timeoutId = setTimeout(() => func(...args), delay)
  }
}

watch(searchQuery, debounce(() => {
  if (authStore.hasPermission('user.view') || authStore.isAdmin || authStore.isManager) {
    loadUsers()
  }
}, 300))

// Format date for display - consistent between server and client
const formatDate = (date) => {
  if (!date) return '從未登入'
  
  try {
    const dateObj = new Date(date)
    if (isNaN(dateObj.getTime())) return '無效日期'
    
    // Use ISO string format to ensure consistency
    return dateObj.toLocaleDateString('zh-TW', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      timeZone: 'Asia/Taipei'
    })
  } catch (error) {
    console.error('Date formatting error:', error)
    return '日期錯誤'
  }
}

// Toggle user status
const toggleStatus = async (user) => {
  try {
    const newStatus = user.status === 'active' ? 'inactive' : 'active'
    await updateUser(user.id, { status: newStatus })
    // 重新載入用戶列表
    await loadUsers()
  } catch (error) {
    console.error('Failed to toggle user status:', error)
  }
}

// Edit user
const editUser = (user) => {
  editForm.value = { 
    ...user,
    role: user.roles && user.roles.length > 0 ? user.roles[0].name : ''
  }
  showEditModal.value = true
}

// Reset add form
const resetAddForm = () => {
  addForm.value = {
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    status: 'active'
  }
}

// Add new user
const addUser = async () => {
  try {
    if (!addForm.value.name || !addForm.value.username || !addForm.value.email || !addForm.value.password || !addForm.value.password_confirmation || !addForm.value.role) {
      await showError('請填寫所有必要欄位')
      return
    }

    if (addForm.value.password !== addForm.value.password_confirmation) {
      await showError('密碼確認不相符')
      return
    }

    if (addForm.value.password.length < 6) {
      await showError('密碼長度至少需要6個字元')
      return
    }
    
    const response = await createUser({
      name: addForm.value.name,
      username: addForm.value.username,
      email: addForm.value.email,
      password: addForm.value.password,
      password_confirmation: addForm.value.password_confirmation,
      role: addForm.value.role,
      status: addForm.value.status
    })
    
    // Show success message
    if (response?.success !== false) {
      await success('使用者建立成功')
      showAddModal.value = false
      resetAddForm()
      // 重新載入用戶列表
      await loadUsers()
    }
  } catch (error) {
    console.error('Failed to create user:', error)
    
    // Handle validation errors
    if (error?.errors) {
      const errorMessages = []
      for (const field in error.errors) {
        errorMessages.push(`${field}: ${error.errors[field].join(', ')}`)
      }
      await showError(`表單驗證失敗:\n${errorMessages.join('\n')}`)
    } else if (error?.message) {
      await showError(`新增用戶失敗: ${error.message}`)
    } else if (error?.error) {
      await showError(`系統錯誤: ${error.error}`)
    } else {
      await showError('新增用戶失敗，請重試')
    }
  }
}

// Save user changes
const saveUser = async () => {
  try {
    await updateUser(editForm.value.id, {
      name: editForm.value.name,
      email: editForm.value.email
    })
    
    // 如果角色有變更，另外處理角色指派
    if (editForm.value.role) {
      await assignRole(editForm.value.id, editForm.value.role)
    }
    
    showEditModal.value = false
    // 重新載入用戶列表
    await loadUsers()
  } catch (error) {
    console.error('Failed to update user:', error)
    await showError('更新用戶失敗，請重試')
  }
}

// Delete user
const deleteUserConfirm = async (user) => {
  if (confirm('確定要刪除此用戶嗎？此操作無法復原。')) {
    try {
      await deleteUser(user.id)
      // 重新載入用戶列表
      await loadUsers()
    } catch (error) {
      console.error('Failed to delete user:', error)
      await showError('刪除用戶失敗，請重試')
    }
  }
}

// Refresh users
const refreshUsers = async () => {
  try {
    refreshing.value = true
    await loadUsers()
  } finally {
    refreshing.value = false
  }
}

// Customer assignment functionality
const openAssignCustomersModal = async (user) => {
  selectedStaff.value = user
  selectedCustomerIds.value = []
  customerSearchQuery.value = ''
  assignmentMode.value = 'unassigned'
  showAssignCustomersModal.value = true
  
  // Load customers
  await loadCustomersForAssignment()
}

const closeAssignCustomersModal = () => {
  showAssignCustomersModal.value = false
  selectedStaff.value = null
  customers.value = []
  selectedCustomerIds.value = []
  customerSearchQuery.value = ''
}

const loadCustomersForAssignment = async () => {
  try {
    loadingCustomers.value = true
    
    const params = {}
    if (assignmentMode.value === 'unassigned') {
      params.assigned_to = 'null' // Filter for unassigned customers
    }
    if (customerSearchQuery.value.trim()) {
      params.search = customerSearchQuery.value.trim()
    }
    
    const { data, error } = await getCustomers(params)
    
    if (error) {
      console.error('Failed to load customers:', error)
      customers.value = []
      return
    }
    
    customers.value = data.data || []
  } catch (err) {
    console.error('Load customers error:', err)
    customers.value = []
  } finally {
    loadingCustomers.value = false
  }
}

const filteredCustomersForAssignment = computed(() => {
  let filtered = customers.value
  
  // Filter by assignment mode
  if (assignmentMode.value === 'unassigned') {
    filtered = filtered.filter(customer => !customer.assigned_to || customer.assigned_to === null)
  }
  
  // Filter by search query
  if (customerSearchQuery.value.trim()) {
    const query = customerSearchQuery.value.toLowerCase()
    filtered = filtered.filter(customer =>
      customer.name.toLowerCase().includes(query) ||
      customer.phone.toLowerCase().includes(query) ||
      (customer.email && customer.email.toLowerCase().includes(query))
    )
  }
  
  return filtered
})

const assignCustomersToStaff = async () => {
  if (!selectedStaff.value || selectedCustomerIds.value.length === 0) return
  
  try {
    assigningCustomers.value = true
    
    // Assign each selected customer to the staff member
    const promises = selectedCustomerIds.value.map(customerId => 
      assignCustomer(customerId, selectedStaff.value.id)
    )
    
    await Promise.all(promises)
    
    await success(`成功指派 ${selectedCustomerIds.value.length} 位客戶給 ${selectedStaff.value.name}`)
    closeAssignCustomersModal()
    
  } catch (error) {
    console.error('Failed to assign customers:', error)
    await showError('指派客戶失敗，請重試')
  } finally {
    assigningCustomers.value = false
  }
}

// Watch for changes in assignment mode and search query
watch([assignmentMode, customerSearchQuery], () => {
  if (showAssignCustomersModal.value) {
    loadCustomersForAssignment()
  }
})

// 頁面初始化
onMounted(() => {
  if (authStore.hasPermission('user.view') || authStore.isAdmin || authStore.isManager) {
    loadUsers()
    loadRoles()
  }
})
</script>