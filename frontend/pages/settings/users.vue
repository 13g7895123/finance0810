<template>
  <div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg-custom shadow-sm p-6">
      <!-- Title -->
      <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
          {{ t('nav.user_management') }}
        </h2>
      </div>

      <!-- Action Bar -->
      <div class="flex items-center justify-between mb-6">
        <!-- Add User Button - Left Side -->
        <button
          @click="showAddModal = true"
          class="inline-flex items-center px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors duration-200"
        >
          <PlusIcon class="w-5 h-5 mr-2" />
          {{ t('auth.add_user') }}
        </button>

        <!-- Search and Refresh - Right Side -->
        <div class="flex items-center space-x-3">
          <!-- Search -->
          <div class="relative">
            <input
              v-model="searchQuery"
              type="text"
              :placeholder="t('common.search') + '...'"
              class="w-64 px-4 py-2 pl-10 border-2 border-gray-600 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white bg-gray-50"
            />
            <MagnifyingGlassIcon class="w-5 h-5 text-gray-500 absolute left-3 top-2.5" />
          </div>
          
          <!-- Refresh Button -->
          <button
            @click="refreshUsers"
            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
            :disabled="refreshing"
          >
            <ArrowPathIcon class="w-4 h-4 mr-2" :class="{ 'animate-spin': refreshing }" />
            重新整理
          </button>
        </div>
      </div>

      <!-- Access Denied for Non-Admin -->
      <div v-if="!authStore.hasPermission('user.view') && !authStore.isAdmin && !authStore.isManager" class="text-center py-12">
        <ShieldExclamationIcon class="w-12 h-12 text-red-500 mx-auto mb-4" />
        <h3 class="text-lg font-medium text-gray-900 mb-2">存取被拒絕</h3>
        <p class="text-gray-600">您沒有權限使用此功能</p>
      </div>

      <!-- Loading State -->
      <div v-else-if="loading" class="text-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mx-auto mb-4"></div>
        <p class="text-gray-600">載入用戶資料中...</p>
      </div>

      <!-- Users Table -->
      <div v-else class="overflow-x-auto">
        <ClientOnly>
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  {{ t('auth.user') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  {{ t('auth.role') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  {{ t('auth.status') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  {{ t('auth.last_login') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  {{ t('auth.actions') }}
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <!-- User Info -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <img :src="user.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=6366f1&color=fff`" :alt="user.name" class="w-10 h-10 rounded-full" />
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ user.name }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</div>
                  </div>
                </div>
              </td>

              <!-- Role -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  :class="{
                    'bg-purple-100 text-purple-800': user.roles?.[0]?.name === 'admin' || user.roles?.[0]?.name === 'executive',
                    'bg-blue-100 text-blue-800': user.roles?.[0]?.name === 'manager',
                    'bg-green-100 text-green-800': user.roles?.[0]?.name === 'staff'
                  }"
                >
                  {{ user.roles?.[0]?.display_name || user.roles?.[0]?.name || '無角色' }}
                </span>
              </td>

              <!-- Status -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  :class="{
                    'bg-blue-600 text-white dark:bg-blue-500 dark:text-white': user.status === 'active',
                    'bg-red-600 text-white dark:bg-red-500 dark:text-white': user.status === 'inactive',
                    'bg-yellow-600 text-white dark:bg-yellow-500 dark:text-white': user.status === 'suspended'
                  }"
                >
                  {{ t(`auth.status_${user.status}`) }}
                </span>
              </td>

              <!-- Last Login -->
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                {{ formatDate(user.last_login_at) }}
              </td>

              <!-- Actions -->
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex items-center space-x-2">
                  <!-- Toggle Status -->
                  <button
                    v-if="user.id !== authStore.user?.id"
                    @click="toggleStatus(user)"
                    class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 transition-colors duration-200"
                  >
                    {{ user.status === 'active' ? t('auth.deactivate') : t('auth.activate') }}
                  </button>
                  
                  <!-- Edit -->
                  <button
                    @click="editUser(user)"
                    class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 transition-colors duration-200"
                  >
                    {{ t('common.edit') }}
                  </button>
                  
                  <!-- Delete -->
                  <button
                    v-if="user.id !== authStore.user?.id"
                    @click="deleteUserConfirm(user)"
                    class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300 transition-colors duration-200"
                  >
                    刪除
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
          </table>

          <!-- No Users Found -->
          <div v-if="filteredUsers.length === 0" class="text-center py-12">
            <UsersIcon class="w-12 h-12 text-gray-400 mx-auto mb-4" />
            <p class="text-gray-500 dark:text-gray-400">{{ t('auth.no_users_found') }}</p>
          </div>
        </ClientOnly>

        <!-- Pagination Controls -->
        <div v-if="totalPages > 1" class="mt-6 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-4">
          <div class="flex-1 flex justify-between sm:hidden">
            <button
              @click="loadUsers(currentPage - 1)"
              :disabled="currentPage <= 1"
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              上一頁
            </button>
            <button
              @click="loadUsers(currentPage + 1)"
              :disabled="currentPage >= totalPages"
              class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              下一頁
            </button>
          </div>
          
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700 dark:text-gray-300">
                顯示第 <span class="font-medium">{{ ((currentPage - 1) * perPage) + 1 }}</span> 
                到 <span class="font-medium">{{ Math.min(currentPage * perPage, totalUsers) }}</span> 
                筆，共 <span class="font-medium">{{ totalUsers }}</span> 筆記錄
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="分頁導航">
                <button
                  @click="loadUsers(currentPage - 1)"
                  :disabled="currentPage <= 1"
                  class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <ChevronLeftIcon class="h-5 w-5" />
                </button>
                
                <!-- Page numbers -->
                <template v-for="page in getVisiblePages()" :key="page">
                  <button
                    v-if="typeof page === 'number'"
                    @click="loadUsers(page)"
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
                  @click="loadUsers(currentPage + 1)"
                  :disabled="currentPage >= totalPages"
                  class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <ChevronRightIcon class="h-5 w-5" />
                </button>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add User Modal - Moved outside main container -->
  <div v-if="showAddModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg-custom shadow-xl max-w-md w-full p-6">
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
        {{ t('auth.add_user') }}
      </h3>
      
      <div class="space-y-4">
        <!-- Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ t('auth.full_name') }}
          </label>
          <input
            v-model="addForm.name"
            type="text"
            required
            class="w-full px-3 py-2 text-lg border border-gray-300 dark:border-gray-500 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
          />
        </div>

        <!-- Username -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ t('auth.username') }}
          </label>
          <input
            v-model="addForm.username"
            type="text"
            required
            class="w-full px-3 py-2 text-lg border border-gray-300 dark:border-gray-500 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
          />
        </div>
        
        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ t('auth.email') }}
          </label>
          <input
            v-model="addForm.email"
            type="email"
            required
            class="w-full px-3 py-2 text-lg border border-gray-300 dark:border-gray-500 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
          />
        </div>

        <!-- Password -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ t('auth.password') }}
          </label>
          <input
            v-model="addForm.password"
            type="password"
            required
            minlength="6"
            class="w-full px-3 py-2 text-lg border border-gray-300 dark:border-gray-500 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
          />
        </div>

        <!-- Confirm Password -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ t('auth.confirm_password') }}
          </label>
          <input
            v-model="addForm.password_confirmation"
            type="password"
            required
            minlength="6"
            class="w-full px-3 py-2 text-lg border border-gray-300 dark:border-gray-500 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
          />
        </div>

        <!-- Role -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ t('auth.role') }}
          </label>
          <select
            v-model="addForm.role"
            required
            class="w-full px-3 py-2 text-lg border border-gray-300 dark:border-gray-500 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
          >
            <option value="">選擇角色</option>
            <option v-for="role in roles" :key="role.id" :value="role.name">
              {{ role.display_name }}
            </option>
          </select>
        </div>

        <!-- Status -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ t('auth.status') }}
          </label>
          <select
            v-model="addForm.status"
            class="w-full px-3 py-2 text-lg border border-gray-300 dark:border-gray-500 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
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
          class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
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
    <div class="bg-white dark:bg-gray-800 rounded-lg-custom shadow-xl max-w-md w-full p-6">
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
        {{ t('auth.edit_user') }}
      </h3>
      
      <div class="space-y-4">
        <!-- Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ t('auth.full_name') }}
          </label>
          <input
            v-model="editForm.name"
            type="text"
            class="w-full px-3 py-2 text-lg border border-gray-300 dark:border-gray-500 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
          />
        </div>
        
        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ t('auth.email') }}
          </label>
          <input
            v-model="editForm.email"
            type="email"
            class="w-full px-3 py-2 text-lg border border-gray-300 dark:border-gray-500 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
          />
        </div>

        <!-- Role -->
        <div v-if="editForm.id !== authStore.user?.id">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ t('auth.role') }}
          </label>
          <select
            v-model="editForm.role"
            class="w-full px-3 py-2 text-lg border border-gray-300 dark:border-gray-500 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
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
          class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
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
</template>

<script setup>
import {
  MagnifyingGlassIcon,
  ShieldExclamationIcon,
  UsersIcon,
  PlusIcon,
  ArrowPathIcon,
  ChevronLeftIcon,
  ChevronRightIcon
} from '@heroicons/vue/24/outline'

definePageMeta({
  middleware: 'auth'
})

const { t } = useI18n()
const authStore = useAuthStore()
const { getUsers, createUser, updateUser, deleteUser, getRoles, assignRole } = useUserManagement()

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

// Pagination state
const currentPage = ref(1)
const totalPages = ref(1)
const perPage = ref(10)
const totalUsers = ref(0)

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
    roles.value = Array.isArray(response) ? response : []
  } catch (error) {
    console.error('Failed to load roles:', error)
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
  editForm.value = { ...user }
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
      alert('請填寫所有必要欄位')
      return
    }

    if (addForm.value.password !== addForm.value.password_confirmation) {
      alert('密碼確認不相符')
      return
    }

    if (addForm.value.password.length < 6) {
      alert('密碼長度至少需要6個字元')
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
      alert('使用者建立成功')
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
      alert(`表單驗證失敗:\n${errorMessages.join('\n')}`)
    } else if (error?.message) {
      alert(`新增用戶失敗: ${error.message}`)
    } else if (error?.error) {
      alert(`系統錯誤: ${error.error}`)
    } else {
      alert('新增用戶失敗，請重試')
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
    alert('更新用戶失敗，請重試')
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
      alert('刪除用戶失敗，請重試')
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

// 頁面初始化
onMounted(() => {
  if (authStore.hasPermission('user.view') || authStore.isAdmin || authStore.isManager) {
    loadUsers()
    loadRoles()
  }
})
</script>