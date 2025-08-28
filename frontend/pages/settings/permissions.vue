<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 ">權限管理</h1>
        <p class="text-gray-600 mt-2">管理用戶權限和角色設定</p>
      </div>
      
      <!-- Quick Actions -->
      <div class="flex items-center space-x-3">
        <button
          @click="showQuickAssign = true"
          class="inline-flex items-center px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors duration-200"
        >
          <UserPlusIcon class="w-4 h-4 mr-2" />
          快速分配
        </button>
        <button
          @click="viewMode = viewMode === 'roles' ? 'matrix' : 'roles'"
          class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200"
        >
          <component :is="viewMode === 'roles' ? TableCellsIcon : Squares2X2Icon" class="w-4 h-4 mr-2" />
          {{ viewMode === 'roles' ? '矩陣視圖' : '角色視圖' }}
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mx-auto mb-4"></div>
      <p class="text-gray-600 ">載入權限資料中...</p>
    </div>

    <div v-else>
      <!-- Role-based View -->
      <div v-if="viewMode === 'roles'" class="space-y-6">
        <!-- Role Selector with Visual Cards -->
        <div class="bg-white rounded-lg shadow-sm p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-gray-900 ">選擇管理角色</h2>
            <div class="text-sm text-gray-500 ">
              共 {{ roles.length }} 個角色
            </div>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div
              v-for="role in roles"
              :key="role.id"
              @click="selectRole(role)"
              class="relative p-4 border-2 rounded-lg cursor-pointer transition-all duration-200 hover:shadow-md"
              :class="{
                'border-primary-500 bg-primary-50': selectedRole?.id === role.id,
                'border-gray-200 hover:border-gray-300': selectedRole?.id !== role.id
              }"
            >
              <div class="flex items-center space-x-3 mb-3">
                <div 
                  class="p-2 rounded-lg"
                  :class="getRoleIconBg(role.name)"
                >
                  <component 
                    :is="getRoleIcon(role.name)" 
                    class="w-5 h-5"
                    :class="getRoleIconColor(role.name)"
                  />
                </div>
                <div>
                  <h3 class="font-semibold text-gray-900 ">{{ role.display_name }}</h3>
                </div>
              </div>
              
              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-xs text-gray-500 ">用戶數</span>
                  <span class="text-xs font-medium text-gray-900 ">{{ getUsersInRole(role.id).length }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-xs text-gray-500 ">權限數</span>
                  <span class="text-xs font-medium text-gray-900 ">{{ getRolePermissionCount(role.id) }}</span>
                </div>
              </div>
              
              <div v-if="selectedRole?.id === role.id" class="absolute top-2 right-2">
                <CheckCircleIcon class="w-5 h-5 text-primary-500" />
              </div>
            </div>
          </div>
        </div>

        <!-- Selected Role Management -->
        <div v-if="selectedRole" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
          <!-- Permission Management - Main Section -->
          <div class="xl:col-span-2 bg-white rounded-lg shadow-sm">
            <div class="p-6">
              <div class="flex items-center justify-between mb-6">
                <div class="flex items-center space-x-3">
                  <div 
                    class="p-2 rounded-lg"
                    :class="getRoleIconBg(selectedRole.name)"
                  >
                    <component 
                      :is="getRoleIcon(selectedRole.name)" 
                      class="w-6 h-6"
                      :class="getRoleIconColor(selectedRole.name)"
                    />
                  </div>
                  <div>
                    <h2 class="text-xl font-semibold text-gray-900 ">{{ selectedRole.display_name }} 權限設定</h2>
                    <p class="text-sm text-gray-500 ">管理此角色的系統權限</p>
                  </div>
                </div>
                
                <div class="flex items-center space-x-2">
                  <button
                    @click="toggleAllPermissions"
                    class="text-sm text-primary-600 hover:text-primary-800 "
                  >
                    {{ allPermissionsSelected ? '取消全選' : '全選' }}
                  </button>
                </div>
              </div>

              <!-- Permission Categories -->
              <div class="space-y-4 max-h-96 overflow-y-auto">
                <div v-for="(categoryPermissions, category) in permissions" :key="category" 
                     class="border border-gray-200 rounded-lg">
                  <div 
                    @click="toggleCategory(category)"
                    class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50 transition-colors duration-200"
                  >
                    <div class="flex items-center space-x-3">
                      <component 
                        :is="Array.isArray(expandedCategories) && expandedCategories.includes(category) ? ChevronDownIcon : ChevronRightIcon" 
                        class="w-4 h-4 text-gray-400" 
                      />
                      <h3 class="font-medium text-gray-900 capitalize">{{ getCategoryDisplayName(category) }}</h3>
                      <span class="inline-flex px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">
                        {{ getCategoryPermissionCount(category) }}
                      </span>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                      <div class="text-xs text-gray-500 ">
                        {{ getCategorySelectedCount(category) }}/{{ categoryPermissions.length }} 已選
                      </div>
                      <input 
                        type="checkbox"
                        :checked="isCategoryFullySelected(category)"
                        :indeterminate="isCategoryPartiallySelected(category)"
                        @click.stop="toggleCategoryPermissions(category)"
                        class="w-4 h-4 text-primary-600 bg-gray-100 border-gray-300 rounded focus:ring-primary-500"
                      >
                    </div>
                  </div>
                  
                  <div v-if="Array.isArray(expandedCategories) && expandedCategories.includes(category)" class="border-t border-gray-200 ">
                    <div class="p-4 space-y-3">
                      <div v-for="permission in categoryPermissions" :key="permission.id" 
                           class="flex items-start space-x-3 p-3 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                        <input 
                          :id="`perm-${permission.id}`" 
                          type="checkbox" 
                          :checked="Array.isArray(rolePermissions) ? rolePermissions.includes(permission.name) : false"
                          @change="togglePermission(permission.name)"
                          class="w-4 h-4 mt-1 text-primary-600 bg-gray-100 border-gray-300 rounded focus:ring-primary-500"
                        >
                        <div class="flex-1 min-w-0">
                          <label :for="`perm-${permission.id}`" class="block text-sm font-medium text-gray-900 cursor-pointer">
                            {{ permission.display_name }}
                          </label>
                          <p class="text-xs text-gray-500 mt-1">{{ permission.description }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Role Users & Summary -->
          <div class="space-y-6">
            <!-- Role Summary -->
            <div class="bg-white rounded-lg shadow-sm p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">角色統計</h3>
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600 ">用戶數量</span>
                  <span class="text-lg font-semibold text-gray-900 ">{{ getUsersInRole(selectedRole.id).length }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600 ">權限數量</span>
                  <span class="text-lg font-semibold text-gray-900 ">{{ rolePermissions.length }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600 ">完整度</span>
                  <span class="text-lg font-semibold text-primary-600 ">
                    {{ Math.round((rolePermissions.length / getTotalPermissionCount()) * 100) }}%
                  </span>
                </div>
              </div>
            </div>

            <!-- Users in Role -->
            <div class="bg-white rounded-lg shadow-sm p-6">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 ">此角色的用戶</h3>
                <button
                  @click="showUserAssignment = true"
                  class="text-sm text-primary-600 hover:text-primary-800 flex items-center space-x-1"
                >
                  <UserPlusIcon class="w-4 h-4" />
                  <span>分配用戶</span>
                </button>
              </div>
              
              <div class="space-y-3 max-h-64 overflow-y-auto">
                <div v-for="user in getUsersInRole(selectedRole.id)" :key="user.id" 
                     class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                  <img :src="user.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=6366f1&color=fff`" 
                       :alt="user.name" class="w-8 h-8 rounded-full">
                  <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-900 truncate">{{ user.name }}</div>
                    <div class="text-xs text-gray-500 truncate">{{ user.email }}</div>
                  </div>
                  <button
                    @click="handleRemoveUserFromRole(user.id, selectedRole.id)"
                    class="text-red-600 hover:text-red-800 "
                  >
                    <XMarkIcon class="w-4 h-4" />
                  </button>
                </div>
                
                <div v-if="getUsersInRole(selectedRole.id).length === 0" class="text-center py-8">
                  <UsersIcon class="w-8 h-8 text-gray-400 mx-auto mb-2" />
                  <p class="text-sm text-gray-500 ">此角色暫無用戶</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Matrix View -->
      <div v-else class="bg-white rounded-lg shadow-sm">
        <div class="p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-gray-900 ">權限矩陣視圖</h2>
            <div class="flex items-center space-x-3">
              <input
                v-model="matrixSearch"
                type="text"
                placeholder="搜尋權限..."
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm"
              >
            </div>
          </div>
          
          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead>
                <tr class="border-b border-gray-200 ">
                  <th class="text-left py-3 px-4 font-medium text-gray-900 sticky left-0 bg-white z-10">
                    權限
                  </th>
                  <th v-for="role in roles" :key="role.id" 
                      class="text-center py-3 px-2 font-medium text-gray-900 min-w-24">
                    <div class="flex flex-col items-center space-y-1">
                      <component 
                        :is="getRoleIcon(role.name)" 
                        class="w-4 h-4"
                        :class="getRoleIconColor(role.name)"
                      />
                      <span class="text-xs">{{ role.display_name }}</span>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(categoryPermissions, category) in filteredMatrixPermissions" :key="category">
                  <tr class="bg-gray-50 ">
                    <td colspan="100%" class="py-2 px-4 font-medium text-gray-900 text-sm capitalize">
                      {{ getCategoryDisplayName(category) }}
                    </td>
                  </tr>
                  <tr v-for="permission in categoryPermissions" :key="permission.id" 
                      class="border-b border-gray-100 hover:bg-gray-50 ">
                    <td class="py-3 px-4 sticky left-0 bg-white ">
                      <div>
                        <div class="text-sm font-medium text-gray-900 ">{{ permission.display_name }}</div>
                        <div class="text-xs text-gray-500 ">{{ permission.description }}</div>
                      </div>
                    </td>
                    <td v-for="role in roles" :key="role.id" class="text-center py-3 px-2">
                      <input 
                        type="checkbox"
                        :checked="isPermissionInRole(permission.name, role.id)"
                        @change="toggleRolePermission(role.id, permission.name)"
                        class="w-4 h-4 text-primary-600 bg-gray-100 border-gray-300 rounded focus:ring-primary-500"
                      >
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick User Assignment Modal -->
    <div v-if="showQuickAssign" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-96 overflow-hidden">
        <div class="p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 ">快速用戶角色分配</h3>
            <button @click="showQuickAssign = false" class="text-gray-400 hover:text-gray-600 ">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          
          <div class="grid grid-cols-2 gap-4 overflow-y-auto max-h-80">
            <!-- Users Column -->
            <div>
              <h4 class="text-sm font-medium text-gray-900 mb-3">用戶</h4>
              <div class="space-y-2">
                <div v-for="user in users" :key="user.id" 
                     class="flex items-center space-x-3 p-2 rounded hover:bg-gray-50 ">
                  <img :src="user.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=6366f1&color=fff`" 
                       :alt="user.name" class="w-6 h-6 rounded-full">
                  <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-900 truncate">{{ user.name }}</div>
                  </div>
                  <select 
                    v-model="quickAssignRoles[user.id]"
                    @change="assignQuickRole(user.id, quickAssignRoles[user.id])"
                    class="text-xs border border-gray-300 rounded "
                  >
                    <option value="">選擇角色</option>
                    <option v-for="role in roles" :key="role.id" :value="role.id">
                      {{ role.display_name }}
                    </option>
                  </select>
                </div>
              </div>
            </div>
            
            <!-- Roles Column -->
            <div>
              <h4 class="text-sm font-medium text-gray-900 mb-3">角色分佈</h4>
              <div class="space-y-2">
                <div v-for="role in roles" :key="role.id" 
                     class="flex items-center justify-between p-2 bg-gray-50 rounded">
                  <div class="flex items-center space-x-2">
                    <component 
                      :is="getRoleIcon(role.name)" 
                      class="w-4 h-4"
                      :class="getRoleIconColor(role.name)"
                    />
                    <span class="text-sm font-medium text-gray-900 ">{{ role.display_name }}</span>
                  </div>
                  <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full">
                    {{ getUsersInRole(role.id).length }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- User Assignment Modal -->
    <div v-if="showUserAssignment" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div class="p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 ">分配用戶到角色</h3>
            <button @click="showUserAssignment = false" class="text-gray-400 hover:text-gray-600 ">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          
          <div class="space-y-3 max-h-64 overflow-y-auto">
            <div v-for="user in getAvailableUsersForRole(selectedRole?.id)" :key="user.id" 
                 class="flex items-center space-x-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 ">
              <img :src="user.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=6366f1&color=fff`" 
                   :alt="user.name" class="w-8 h-8 rounded-full">
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-gray-900 ">{{ user.name }}</div>
                <div class="text-xs text-gray-500 ">{{ user.email }}</div>
              </div>
              <button
                @click="assignUserToRole(user.id, selectedRole.id)"
                class="text-primary-600 hover:text-primary-800 "
              >
                <PlusIcon class="w-4 h-4" />
              </button>
            </div>
            
            <div v-if="getAvailableUsersForRole(selectedRole?.id).length === 0" class="text-center py-8">
              <p class="text-sm text-gray-500 ">所有用戶已分配此角色</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { nextTick } from 'vue'
import {
  ShieldCheckIcon,
  UserPlusIcon,
  TableCellsIcon,
  Squares2X2Icon,
  CheckCircleIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  UsersIcon,
  XMarkIcon,
  PlusIcon,
  ShieldExclamationIcon,
  CogIcon,
  UserIcon,
  BuildingOfficeIcon
} from '@heroicons/vue/24/outline'

definePageMeta({
  middleware: 'auth'
})

useHead({
  title: '權限管理 - 金融管理系統'
})

// Composables
const { alert, error: showError } = useNotification()
const { getPermissions, getRolePermissions, assignPermissionToRole, removePermissionFromRole } = usePermissions()
const { getUsers, getRoles, assignRole, removeUserFromRole } = useUserManagement()

// Reactive data
const loading = ref(true)
const permissions = ref({})
const roles = ref([])
const users = ref([])
const selectedRole = ref(null)
const rolePermissions = ref([])
const rolePermissionsCache = ref({})

// UI State
const viewMode = ref('roles') // 'roles' or 'matrix'
const showQuickAssign = ref(false)
const showUserAssignment = ref(false)
const expandedCategories = ref([])
const matrixSearch = ref('')
const quickAssignRoles = ref({})

// Load initial data
const loadData = async () => {
  try {
    loading.value = true
    
    console.log('Loading permissions management data...')
    
    // Load all data in parallel with individual error handling
    const [permissionsData, rolesData, usersData] = await Promise.allSettled([
      getPermissions(),
      getRoles(),
      getUsers()
    ])
    
    // Handle permissions data
    if (permissionsData.status === 'fulfilled') {
      permissions.value = permissionsData.value.permissions || {}
      console.log('Permissions loaded:', Object.keys(permissions.value).length, 'categories')
    } else {
      console.error('Failed to load permissions:', permissionsData.reason)
      permissions.value = {}
    }
    
    // Handle roles data
    if (rolesData.status === 'fulfilled') {
      roles.value = rolesData.value || []
      console.log('Roles loaded:', roles.value.length, 'roles')
    } else {
      console.error('Failed to load roles:', rolesData.reason)
      roles.value = []
    }
    
    // Handle users data
    if (usersData.status === 'fulfilled') {
      users.value = usersData.value.data || usersData.value || []
      console.log('Users loaded:', users.value.length, 'users')
    } else {
      console.error('Failed to load users:', usersData.reason)
      users.value = []
    }
    
    // Expand first category by default
    if (Object.keys(permissions.value).length > 0) {
      expandedCategories.value = [Object.keys(permissions.value)[0]]
    } else {
      expandedCategories.value = []
    }
    
    // Load permissions for all roles to cache
    if (roles.value.length > 0) {
      await loadAllRolePermissions()
    }
    
    console.log('Permissions management data loaded successfully')
  } catch (error) {
    console.error('Critical error loading permission data:', error)
    await showError('載入權限資料時發生錯誤，請重新整理頁面或聯繫系統管理員')
  } finally {
    loading.value = false
  }
}

// Load permissions for all roles
const loadAllRolePermissions = async () => {
  try {
    const permissionPromises = roles.value.map(async (role) => {
      try {
        const data = await getRolePermissions(role.id)
        return { roleId: role.id, permissions: data.permissions || [] }
      } catch (error) {
        console.error(`Failed to load permissions for role ${role.id}:`, error)
        return { roleId: role.id, permissions: [] }
      }
    })
    
    const results = await Promise.all(permissionPromises)
    results.forEach(({ roleId, permissions }) => {
      rolePermissionsCache.value[roleId] = permissions
    })
    
    // Set current role permissions if role is selected
    if (selectedRole.value) {
      const cachedPerms = rolePermissionsCache.value[selectedRole.value.id]
      rolePermissions.value = Array.isArray(cachedPerms) ? cachedPerms : []
    }
  } catch (error) {
    console.error('Failed to load role permissions:', error)
  }
}

// Role selection and management
const selectRole = async (role) => {
  selectedRole.value = role
  const cachedPerms = rolePermissionsCache.value[role.id]
  rolePermissions.value = Array.isArray(cachedPerms) ? cachedPerms : []
  
  // Load fresh permissions if not cached
  if (!rolePermissionsCache.value[role.id]) {
    await loadRolePermissions()
  }
}

// Load role permissions when role is selected
const loadRolePermissions = async () => {
  if (!selectedRole.value || !selectedRole.value.id) return
  
  try {
    const data = await getRolePermissions(selectedRole.value.id)
    const perms = data.permissions || []
    rolePermissions.value = Array.isArray(perms) ? perms : []
    rolePermissionsCache.value[selectedRole.value.id] = rolePermissions.value
  } catch (error) {
    console.error('Failed to load role permissions:', error)
    rolePermissions.value = []
  }
}

// Permission management
const togglePermission = async (permissionName) => {
  if (!selectedRole.value) {
    console.warn('No role selected for permission toggle')
    return
  }
  
  try {
    const rolePerms = Array.isArray(rolePermissions.value) ? rolePermissions.value : []
    const hasPermission = rolePerms.includes(permissionName)
    
    if (hasPermission) {
      // Remove permission
      console.log(`Removing permission ${permissionName} from role ${selectedRole.value.id}`)
      await removePermissionFromRole(selectedRole.value.id, permissionName)
      rolePermissions.value = rolePerms.filter(p => p !== permissionName)
    } else {
      // Add permission
      console.log(`Adding permission ${permissionName} to role ${selectedRole.value.id}`)
      await assignPermissionToRole(selectedRole.value.id, permissionName)
      if (!rolePerms.includes(permissionName)) {
        rolePermissions.value = [...rolePerms, permissionName]
      }
    }
    
    // Update cache
    rolePermissionsCache.value[selectedRole.value.id] = [...rolePermissions.value]
    console.log(`Permission ${permissionName} toggled successfully for role ${selectedRole.value.display_name}`)
  } catch (error) {
    console.error('Failed to toggle permission:', error)
    // Show user-friendly error message
    await showError(`權限設定失敗: ${error.message || '請檢查網路連線或聯繫系統管理員'}`)
    // Revert the change by reloading
    await loadRolePermissions()
  }
}

// Category management
const toggleCategory = (category) => {
  if (!Array.isArray(expandedCategories.value)) {
    expandedCategories.value = []
  }
  const index = expandedCategories.value.indexOf(category)
  if (index > -1) {
    expandedCategories.value.splice(index, 1)
  } else {
    expandedCategories.value.push(category)
  }
}

const toggleCategoryPermissions = async (category) => {
  if (!selectedRole.value || !permissions.value[category]) return
  
  const categoryPermissions = permissions.value[category]
  const isFullySelected = isCategoryFullySelected(category)
  
  try {
    if (isFullySelected) {
      // Remove all permissions in category
      for (const permission of categoryPermissions) {
        const rolePerms = Array.isArray(rolePermissions.value) ? rolePermissions.value : []
        if (rolePerms.includes(permission.name)) {
          await removePermissionFromRole(selectedRole.value.id, permission.name)
          rolePermissions.value = rolePerms.filter(p => p !== permission.name)
        }
      }
    } else {
      // Add all permissions in category
      const currentPerms = Array.isArray(rolePermissions.value) ? rolePermissions.value : []
      for (const permission of categoryPermissions) {
        if (!currentPerms.includes(permission.name)) {
          await assignPermissionToRole(selectedRole.value.id, permission.name)
          currentPerms.push(permission.name)
        }
      }
      rolePermissions.value = currentPerms
    }
    
    // Update cache
    rolePermissionsCache.value[selectedRole.value.id] = rolePermissions.value
  } catch (error) {
    console.error('Failed to toggle category permissions:', error)
    await loadRolePermissions()
  }
}

const toggleAllPermissions = async () => {
  if (!selectedRole.value) return
  
  const allPermissions = getAllPermissionNames()
  const isAllSelected = allPermissionsSelected.value
  
  try {
    if (isAllSelected) {
      // Remove all permissions
      const currentPerms = Array.isArray(rolePermissions.value) ? rolePermissions.value : []
      for (const permissionName of currentPerms) {
        await removePermissionFromRole(selectedRole.value.id, permissionName)
      }
      rolePermissions.value = []
    } else {
      // Add all permissions
      const currentPerms = Array.isArray(rolePermissions.value) ? rolePermissions.value : []
      for (const permissionName of allPermissions) {
        if (!currentPerms.includes(permissionName)) {
          await assignPermissionToRole(selectedRole.value.id, permissionName)
          currentPerms.push(permissionName)
        }
      }
      rolePermissions.value = currentPerms
    }
    
    // Update cache
    rolePermissionsCache.value[selectedRole.value.id] = rolePermissions.value
  } catch (error) {
    console.error('Failed to toggle all permissions:', error)
    await loadRolePermissions()
  }
}

// User-Role management (basic versions - detailed versions are below)

const assignQuickRole = async (userId, roleId) => {
  if (!roleId) return
  await assignUserToRole(userId, roleId)
}

// Matrix view
const toggleRolePermission = async (roleId, permissionName) => {
  const role = roles.value.find(r => r.id === roleId)
  if (!role) {
    console.warn('Role not found for matrix toggle:', roleId)
    return
  }
  
  const oldSelectedRole = selectedRole.value
  const oldRolePermissions = [...rolePermissions.value]
  
  try {
    // Temporarily switch to the target role
    selectedRole.value = role
    const cachedPerms = rolePermissionsCache.value[roleId]
    rolePermissions.value = Array.isArray(cachedPerms) ? cachedPerms : []
    
    // Toggle the permission for this role
    await togglePermission(permissionName)
    
    // Update the cache for this specific role
    rolePermissionsCache.value[roleId] = [...rolePermissions.value]
  } catch (error) {
    console.error('Failed to toggle role permission in matrix view:', error)
  } finally {
    // Restore previous selection
    selectedRole.value = oldSelectedRole
    if (oldSelectedRole) {
      const cachedPerms = rolePermissionsCache.value[oldSelectedRole.id]
      rolePermissions.value = Array.isArray(cachedPerms) ? cachedPerms : oldRolePermissions
    } else {
      rolePermissions.value = Array.isArray(oldRolePermissions) ? oldRolePermissions : []
    }
  }
}

// Utility functions
const getRoleIcon = (roleName) => {
  const iconMap = {
    admin: ShieldExclamationIcon,
    executive: BuildingOfficeIcon,
    manager: CogIcon,
    staff: UserIcon,
    sales: UserIcon
  }
  return iconMap[roleName] || UserIcon
}

const getRoleIconColor = (roleName) => {
  const colorMap = {
    admin: 'text-purple-600',
    executive: 'text-purple-600',
    manager: 'text-blue-600',
    staff: 'text-green-600',
    sales: 'text-green-600'
  }
  return colorMap[roleName] || 'text-gray-600'
}

const getRoleIconBg = (roleName) => {
  const bgMap = {
    admin: 'bg-purple-100',
    executive: 'bg-purple-100',
    manager: 'bg-blue-100',
    staff: 'bg-green-100',
    sales: 'bg-green-100'
  }
  return bgMap[roleName] || 'bg-gray-100'
}

const getCategoryDisplayName = (category) => {
  const nameMap = {
    users: '用戶管理',
    customers: '客戶管理',
    reports: '報表管理',
    chat: '聊天功能',
    system: '系統設定',
    finance: '財務管理'
  }
  return nameMap[category] || category
}

const getUsersInRole = (roleId) => {
  return users.value.filter(user => 
    user.roles && user.roles.some(role => role.id === roleId)
  )
}

const getAvailableUsersForRole = (roleId) => {
  if (!roleId) return []
  return users.value.filter(user => 
    !user.roles || !user.roles.some(role => role.id === roleId)
  )
}

const getRolePermissionCount = (roleId) => {
  return rolePermissionsCache.value[roleId]?.length || 0
}

const getCategoryPermissionCount = (category) => {
  return permissions.value[category]?.length || 0
}

const getCategorySelectedCount = (category) => {
  if (!permissions.value[category] || !selectedRole.value) return 0
  const rolePerms = Array.isArray(rolePermissions.value) ? rolePermissions.value : []
  return permissions.value[category].filter(p => 
    rolePerms.includes(p.name)
  ).length
}

const isCategoryFullySelected = (category) => {
  if (!permissions.value[category] || !selectedRole.value) return false
  const rolePerms = Array.isArray(rolePermissions.value) ? rolePermissions.value : []
  return permissions.value[category].every(p => 
    rolePerms.includes(p.name)
  )
}

const isCategoryPartiallySelected = (category) => {
  if (!permissions.value[category] || !selectedRole.value) return false
  const selectedCount = getCategorySelectedCount(category)
  const totalCount = getCategoryPermissionCount(category)
  return selectedCount > 0 && selectedCount < totalCount
}

// Handle indeterminate state for checkboxes (simplified approach)
const setCategoryCheckboxState = () => {
  // This function is now handled via Vue's reactive :indeterminate binding
  // No DOM manipulation needed
}

const isPermissionInRole = (permissionName, roleId) => {
  return rolePermissionsCache.value[roleId]?.includes(permissionName) || false
}

const getAllPermissionNames = () => {
  const allNames = []
  Object.values(permissions.value).forEach(categoryPerms => {
    categoryPerms.forEach(perm => allNames.push(perm.name))
  })
  return allNames
}

const getTotalPermissionCount = () => {
  return getAllPermissionNames().length
}

// Additional utility functions for user-role management

const assignUserToRole = async (userId, roleId) => {
  try {
    const role = roles.value.find(r => r.id === roleId)
    if (role) {
      await assignRole(userId, role.name)
      // Refresh users data
      await loadData()
      showUserAssignment.value = false
    }
  } catch (error) {
    console.error('Failed to assign user to role:', error)
    await showError(`分配用戶到角色失敗: ${error.message}`)
  }
}

const handleRemoveUserFromRole = async (userId, roleId) => {
  try {
    await removeUserFromRole(userId, roleId)
    // Refresh users data
    await loadData()
  } catch (error) {
    console.error('Failed to remove user from role:', error)
    await showError(`移除用戶角色失敗: ${error.message}`)
  }
}

// Computed properties
const allPermissionsSelected = computed(() => {
  if (!selectedRole.value) return false
  const totalCount = getTotalPermissionCount()
  const rolePerms = Array.isArray(rolePermissions.value) ? rolePermissions.value : []
  return rolePerms.length === totalCount
})

const filteredMatrixPermissions = computed(() => {
  if (!matrixSearch.value) return permissions.value
  
  const filtered = {}
  Object.entries(permissions.value).forEach(([category, perms]) => {
    const matchingPerms = perms.filter(perm => 
      perm.display_name.toLowerCase().includes(matrixSearch.value.toLowerCase()) ||
      perm.description.toLowerCase().includes(matrixSearch.value.toLowerCase())
    )
    if (matchingPerms.length > 0) {
      filtered[category] = matchingPerms
    }
  })
  return filtered
})

// Initialize
onMounted(async () => {
  await loadData()
  // Set up indeterminate checkbox states
  setCategoryCheckboxState()
})

// Watch for permission changes to update checkbox states
watch([rolePermissions, permissions], () => {
  setCategoryCheckboxState()
})
</script>