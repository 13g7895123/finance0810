<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">權限管理</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">管理用戶權限和角色設定</p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mx-auto mb-4"></div>
      <p class="text-gray-600 dark:text-gray-400">載入權限資料中...</p>
    </div>

    <!-- Permission Management -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Roles & Permissions -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white">角色權限設定</h2>
          <select v-model="selectedRole" @change="loadRolePermissions" 
                  class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
            <option value="">選擇角色</option>
            <option v-for="role in roles" :key="role.id" :value="role">
              {{ role.display_name }}
            </option>
          </select>
        </div>

        <!-- Role Permissions -->
        <div v-if="selectedRole" class="space-y-4">
          <div v-for="(categoryPermissions, category) in permissions" :key="category" 
               class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
            <h3 class="font-medium text-gray-900 dark:text-white mb-3 capitalize">
              {{ category }}
            </h3>
            <div class="space-y-2">
              <div v-for="permission in categoryPermissions" :key="permission.id" 
                   class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                  <input :id="`perm-${permission.id}`" type="checkbox" 
                         :checked="rolePermissions.includes(permission.name)"
                         @change="togglePermission(permission.name)"
                         class="w-4 h-4 text-primary-600 bg-gray-100 border-gray-300 rounded focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-800 dark:bg-gray-700 dark:border-gray-600">
                  <label :for="`perm-${permission.id}`" class="text-sm text-gray-700 dark:text-gray-300">
                    {{ permission.display_name }}
                  </label>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  {{ permission.description }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-8">
          <p class="text-gray-500 dark:text-gray-400">請選擇角色以管理權限</p>
        </div>
      </div>

      <!-- Users & Roles -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">用戶角色分配</h2>
        
        <!-- User List -->
        <div class="space-y-3">
          <div v-for="user in users" :key="user.id" 
               class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <div class="flex items-center space-x-3">
              <img :src="user.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=6366f1&color=fff`" 
                   :alt="user.name" class="w-8 h-8 rounded-full">
              <div>
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ user.name }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</div>
              </div>
            </div>
            <div class="flex flex-wrap gap-1">
              <span v-for="role in user.roles" :key="role.id" 
                    class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                    :class="{
                      'bg-purple-100 text-purple-800': role.name === 'admin' || role.name === 'executive',
                      'bg-blue-100 text-blue-800': role.name === 'manager',
                      'bg-green-100 text-green-800': role.name === 'sales'
                    }">
                {{ role.display_name }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Permission Categories -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">權限分類總覽</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="(categoryPermissions, category) in permissions" :key="category"
             class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
          <h3 class="font-medium text-gray-900 dark:text-white mb-2 capitalize">{{ category }}</h3>
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            共 {{ categoryPermissions.length }} 項權限
          </p>
          <div class="space-y-1">
            <div v-for="permission in categoryPermissions.slice(0, 3)" :key="permission.id" 
                 class="text-xs text-gray-500 dark:text-gray-400">
              • {{ permission.display_name }}
            </div>
            <div v-if="categoryPermissions.length > 3" class="text-xs text-gray-400">
              +{{ categoryPermissions.length - 3 }} 更多...
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ShieldCheckIcon } from '@heroicons/vue/24/outline'

definePageMeta({
  middleware: 'auth'
})

useHead({
  title: '權限管理 - 金融管理系統'
})

// Composables
const { getPermissions, getRolePermissions, getUserRoles } = usePermissions()
const { getUsers, getRoles } = useUserManagement()

// Reactive data
const loading = ref(true)
const permissions = ref({})
const roles = ref([])
const users = ref([])
const selectedRole = ref('')
const rolePermissions = ref([])

// Load initial data
const loadData = async () => {
  try {
    loading.value = true
    
    // Load all data in parallel
    const [permissionsData, rolesData, usersData] = await Promise.all([
      getPermissions(),
      getRoles(),
      getUsers()
    ])
    
    permissions.value = permissionsData.permissions
    roles.value = rolesData
    users.value = usersData.data || usersData
  } catch (error) {
    console.error('Failed to load permission data:', error)
  } finally {
    loading.value = false
  }
}

// Load role permissions when role is selected
const loadRolePermissions = async () => {
  if (!selectedRole.value || !selectedRole.value.id) return
  
  try {
    const data = await getRolePermissions(selectedRole.value.id)
    rolePermissions.value = data.permissions || []
  } catch (error) {
    console.error('Failed to load role permissions:', error)
    rolePermissions.value = []
  }
}

// Toggle permission for selected role
const togglePermission = async (permissionName) => {
  if (!selectedRole.value) return
  
  try {
    const hasPermission = rolePermissions.value.includes(permissionName)
    
    if (hasPermission) {
      // Remove permission
      await removePermissionFromRole(selectedRole.value.id, permissionName)
      rolePermissions.value = rolePermissions.value.filter(p => p !== permissionName)
    } else {
      // Add permission
      await assignPermissionToRole(selectedRole.value.id, permissionName)
      rolePermissions.value.push(permissionName)
    }
  } catch (error) {
    console.error('Failed to toggle permission:', error)
    // Revert the change
    await loadRolePermissions()
  }
}

// Initialize
onMounted(() => {
  loadData()
})
</script>