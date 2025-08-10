<template>
  <div>
    <!-- Desktop Sidebar -->
    <aside
      ref="sidebar"
      class="fixed top-0 left-0 h-full bg-[#2c2c2c] shadow-lg transition-all duration-300 z-40 hidden lg:block flex flex-col"
      :class="[
        sidebarCollapsed ? 'w-20' : ''
      ]"
      :style="sidebarCollapsed ? {} : { width: sidebarWidth + 'px' }"
    >
      <!-- Logo/Brand -->
      <div class="h-16 flex items-center justify-center border-b border-gray-600">
        <div v-if="!sidebarCollapsed" class="text-xl font-bold text-white">
          金融管理系統
        </div>
        <div v-else class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
          <span class="text-white font-bold text-sm">金</span>
        </div>
      </div>

      <!-- Navigation Menu -->
      <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
        <SidebarMenuItem
          v-for="item in filteredMenuItems"
          :key="item.name"
          :item="item"
          :collapsed="sidebarCollapsed"
        />
      </nav>

      <!-- User Info & Logout -->
      <div class="p-4 border-t border-gray-600">
        <div v-if="!sidebarCollapsed && isClient && authStore.user" class="mb-3 text-center">
          <div class="text-sm text-white">{{ authStore.user.name }}</div>
          <div class="text-xs text-white opacity-80">{{ getRoleDisplayName(authStore.user.role) }}</div>
        </div>
        <button
          @click="handleLogout"
          class="w-full flex items-center px-3 py-2 text-red-200 bg-red-900/20 hover:bg-red-600 hover:text-white rounded-lg transition-all duration-200 border border-red-700/50 font-medium"
          :class="{ 'justify-center': sidebarCollapsed }"
        >
          <ArrowRightOnRectangleIcon class="w-5 h-5" />
          <span v-if="!sidebarCollapsed" class="ml-3">登出</span>
        </button>
      </div>

      <!-- Resize Handle -->
      <div
        v-if="!sidebarCollapsed"
        ref="resizeHandle"
        class="absolute right-0 top-0 bottom-0 w-1 bg-gray-600 hover:bg-gray-500 cursor-col-resize opacity-0 hover:opacity-100 transition-opacity duration-200"
        @mousedown="startResize"
      ></div>
    </aside>

    <!-- Mobile Sidebar Overlay -->
    <div
      v-if="sidebarMobileOpen"
      class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
      @click="closeMobileSidebar"
    />

    <!-- Mobile Sidebar -->
    <aside
      class="fixed top-0 left-0 h-full w-70 bg-[#2c2c2c] shadow-lg transition-transform duration-300 z-50 lg:hidden flex flex-col"
      :class="[
        sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full'
      ]"
    >
      <!-- Logo/Brand -->
      <div class="h-16 flex items-center justify-between px-4 border-b border-gray-600">
        <div class="text-xl font-bold text-white">
          金融管理系統
        </div>
        <button
          @click="closeMobileSidebar"
          class="p-2 hover:bg-gray-700 rounded-lg text-white hover:text-white"
        >
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>

      <!-- Navigation Menu -->
      <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
        <SidebarMenuItem
          v-for="item in filteredMenuItems"
          :key="item.name"
          :item="item"
          :collapsed="false"
          @click="closeMobileSidebar"
        />
      </nav>

      <!-- User Info & Logout -->
      <div class="p-4 border-t border-gray-600">
        <div v-if="isClient && authStore.user" class="mb-3 text-center">
          <div class="text-sm text-white">{{ authStore.user.name }}</div>
          <div class="text-xs text-white opacity-80">{{ getRoleDisplayName(authStore.user.role) }}</div>
        </div>
        <button
          @click="handleLogout"
          class="w-full flex items-center px-3 py-2 text-red-200 bg-red-900/20 hover:bg-red-600 hover:text-white rounded-lg transition-all duration-200 border border-red-700/50 font-medium"
        >
          <ArrowRightOnRectangleIcon class="w-5 h-5" />
          <span class="ml-3">登出</span>
        </button>
      </div>
    </aside>
  </div>
</template>

<script setup>
import { 
  ChevronLeftIcon,
  XMarkIcon,
  ArrowRightOnRectangleIcon
} from '@heroicons/vue/24/outline'

// Explicitly import the SidebarMenuItem component
import SidebarMenuItem from './SidebarMenuItem.vue'

const sidebarStore = useSidebarStore()
const { sidebarCollapsed, sidebarMobileOpen } = storeToRefs(sidebarStore)
const { toggleSidebar, closeMobileSidebar } = sidebarStore

const settingsStore = useSettingsStore()
const { sidebarMenuItems } = storeToRefs(settingsStore)

const authStore = useAuthStore()

// 客戶端狀態標記
const isClient = ref(false)
const sidebar = ref(null)
const resizeHandle = ref(null)
const sidebarWidth = ref(280) // 預設寬度
const isDragging = ref(false)

onMounted(() => {
  isClient.value = true
  // 從 localStorage 載入儲存的寬度
  const savedWidth = localStorage.getItem('sidebar-width')
  if (savedWidth) {
    sidebarWidth.value = parseInt(savedWidth)
  }
})

// 權限過濾選單項目
const filteredMenuItems = computed(() => {
  // 在 SSR 階段或用戶未登入時，返回空陣列
  if (!isClient.value || !authStore.isLoggedIn || !authStore.user) {
    return []
  }
  
  return sidebarMenuItems.value.filter(item => {
    // 檢查項目是否有權限要求
    if (item.permissions && item.permissions.length > 0) {
      return item.permissions.some(permission => authStore.hasPermission(permission))
    }
    return true
  }).map(item => {
    // 如果有子項目，也要進行權限過濾
    if (item.children) {
      const filteredChildren = item.children.filter(child => {
        if (child.permissions && child.permissions.length > 0) {
          return child.permissions.some(permission => authStore.hasPermission(permission))
        }
        return true
      })
      return {
        ...item,
        children: filteredChildren
      }
    }
    return item
  }).filter(item => {
    // 移除沒有子項目的父項目（如果所有子項目都被過濾掉）
    if (item.children && item.children.length === 0 && !item.href) {
      return false
    }
    return true
  })
})

// 角色顯示名稱
const getRoleDisplayName = (role) => {
  const roleMap = {
    [authStore.roles.DEALER_EXECUTIVE]: '經銷商/公司高層',
    [authStore.roles.ADMIN_MANAGER]: '行政人員/主管',
    [authStore.roles.SALES_STAFF]: '業務人員'
  }
  return roleMap[role] || role
}

// 登出處理
const handleLogout = () => {
  authStore.logout()
}

// 拖拽調整寬度功能
const startResize = (e) => {
  isDragging.value = true
  document.addEventListener('mousemove', handleResize)
  document.addEventListener('mouseup', stopResize)
  document.body.style.cursor = 'col-resize'
  document.body.style.userSelect = 'none'
  e.preventDefault()
}

const handleResize = (e) => {
  if (!isDragging.value) return
  
  const newWidth = Math.max(200, Math.min(400, e.clientX)) // 限制寬度在200-400px之間
  sidebarWidth.value = newWidth
}

const stopResize = () => {
  isDragging.value = false
  document.removeEventListener('mousemove', handleResize)
  document.removeEventListener('mouseup', stopResize)
  document.body.style.cursor = ''
  document.body.style.userSelect = ''
  
  // 儲存寬度到 localStorage
  localStorage.setItem('sidebar-width', sidebarWidth.value.toString())
}
</script>

<style scoped>
.w-70 {
  width: 280px;
}
</style>