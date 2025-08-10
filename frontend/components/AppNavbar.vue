<template>
  <header class="h-16 bg-white border-b border-gray-200 px-6 flex items-center justify-between">
    <!-- Left side - Mobile hamburger or Breadcrumb -->
    <div class="flex items-center space-x-4">
      <!-- Mobile hamburger button -->
      <button
        @click="toggleMobileSidebar"
        class="lg:hidden p-2 hover:bg-gray-100 rounded-lg"
      >
        <Bars3Icon class="w-6 h-6" />
      </button>

      <!-- Breadcrumb (Desktop only) -->
      <div class="hidden lg:flex items-center space-x-4">
        <!-- Current Page Title -->
        <h1 class="text-xl font-bold text-gray-900">
          {{ currentPageTitle }}
        </h1>
        
        <!-- Separator -->
        <div class="h-6 w-px bg-gray-300"></div>
        
        <!-- Breadcrumb Navigation -->
        <nav class="flex" aria-label="Breadcrumb">
          <ol class="flex items-center space-x-2 text-sm">
            <li>
              <NuxtLink
                to="/"
                class="text-gray-500 hover:text-primary-500 transition-colors duration-200"
              >
                首頁
              </NuxtLink>
            </li>
            <li v-for="(item, index) in breadcrumbItems" :key="index" class="flex items-center">
              <ChevronRightIcon class="w-4 h-4 mx-2 text-gray-400" />
              <NuxtLink
                v-if="item.href && index < breadcrumbItems.length - 1"
                :to="item.href"
                class="text-gray-500 hover:text-primary-500 transition-colors duration-200"
              >
                {{ item.name }}
              </NuxtLink>
              <span
                v-else
                class="text-gray-700 font-medium"
              >
                {{ item.name }}
              </span>
            </li>
          </ol>
        </nav>
      </div>
    </div>

    <!-- Right side icons -->
    <div class="flex items-center space-x-3">
      <!-- Search (Hidden) -->
      <!-- <button
        @click="toggleSearch"
        class="p-2 hover:bg-gray-100 rounded-lg transition-colors duration-200 relative"
      >
        <MagnifyingGlassIcon class="w-5 h-5" />
      </button> -->

      <!-- Language -->
      <div class="relative">
        <button
          @click="toggleLanguage"
          class="p-2 hover:bg-gray-700 rounded-lg transition-colors duration-200 group"
        >
          <GlobeAltIcon class="w-5 h-5 text-gray-700 group-hover:text-white" />
        </button>
        
        <!-- Language Dropdown -->
        <transition
          enter-active-class="transition ease-out duration-100"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-75"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <div
            v-if="showLanguage"
            class="absolute right-0 top-full mt-2 w-48 bg-gray-800 rounded-lg shadow-lg border border-gray-600 py-2 z-50"
          >
            <button
              v-for="lang in languages"
              :key="lang.code"
              @click="selectLanguage(lang)"
              class="w-full text-left px-4 py-2 hover:bg-gray-700 transition-colors duration-200 text-white"
            >
              <span class="text-lg mr-2">{{ lang.flag }}</span>
              {{ lang.name }}
            </button>
          </div>
        </transition>
      </div>


      <!-- Notifications -->
      <div class="relative">
        <button
          @click="toggleNotifications"
          class="p-2 hover:bg-gray-700 rounded-lg transition-colors duration-200 relative group"
        >
          <BellIcon class="w-5 h-5 text-gray-700 group-hover:text-white" />
          <span 
            v-if="unreadCount > 0"
            class="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 rounded-full flex items-center justify-center text-xs text-white font-bold"
          >
            {{ unreadCount > 99 ? '99+' : unreadCount }}
          </span>
        </button>

        <!-- Notifications Dropdown -->
        <transition
          enter-active-class="transition ease-out duration-100"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-75"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <div
            v-if="showNotifications"
            class="absolute right-0 top-full mt-2 w-96 bg-gray-800 rounded-lg shadow-lg border border-gray-600 z-50"
          >
            <div class="p-4 border-b border-gray-600 flex items-center justify-between">
              <h3 class="font-semibold text-white">通知</h3>
              <div class="flex items-center space-x-2">
                <button
                  @click="markAllAsRead"
                  class="text-xs text-primary-500 hover:text-primary-600 transition-colors duration-200"
                >
                  全部標記已讀
                </button>
              </div>
            </div>
            <div class="max-h-80 overflow-y-auto">
              <div
                v-for="notification in recentNotifications"
                :key="notification.id"
                class="p-4 border-b border-gray-600 hover:bg-gray-700 transition-colors duration-200 cursor-pointer"
                :class="{ 'bg-gray-700': !notification.read }"
                @click="markAsRead(notification.id)"
              >
                <div class="flex items-start space-x-3">
                  <div 
                    class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                    :class="{
                      'bg-red-100 text-red-600  ': notification.priority === 'high',
                      'bg-yellow-100 text-yellow-600  ': notification.priority === 'medium',
                      'bg-blue-100 text-blue-600  ': notification.priority === 'low'
                    }"
                  >
                    <component :is="getNotificationIcon(notification.icon)" class="w-4 h-4" />
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white">
                      {{ notification.title }}
                    </p>
                    <p class="text-sm text-gray-300 mt-1">{{ notification.message }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ notificationsStore.getTimeAgo(notification.time) }}</p>
                  </div>
                  <div class="flex-shrink-0">
                    <div
                      v-if="!notification.read"
                      class="w-2 h-2 bg-primary-500 rounded-full"
                    ></div>
                  </div>
                </div>
              </div>
              <div
                v-if="recentNotifications.length === 0"
                class="p-8 text-center text-gray-400"
              >
                暫無通知
              </div>
            </div>
            <div class="p-3 border-t border-gray-600">
              <button
                @click="clearReadNotifications"
                class="w-full text-center text-sm text-gray-400 hover:text-gray-200 transition-colors duration-200"
              >
                清除所有
              </button>
            </div>
          </div>
        </transition>
      </div>

      <!-- User Avatar -->
      <div class="relative">
        <button
          @click="toggleUserMenu"
          class="flex items-center space-x-2 p-1 hover:bg-gray-100  rounded-lg transition-colors duration-200"
        >
          <img
            src="https://ui-avatars.com/api/?name=Admin+User&background=6366f1&color=fff"
            alt="User Avatar"
            class="w-8 h-8 rounded-full"
          />
          <ChevronDownIcon class="w-4 h-4 hidden sm:block" />
        </button>

        <!-- User Dropdown -->
        <transition
          enter-active-class="transition ease-out duration-100"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-75"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <div
            v-if="showUserMenu"
            class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50"
          >
            <NuxtLink
              to="/profile"
              class="block px-4 py-2 text-sm text-gray-700  hover:bg-gray-100  transition-colors duration-200"
              @click="closeUserMenu"
            >
              個人資料
            </NuxtLink>
            <NuxtLink
              to="/settings"
              class="block px-4 py-2 text-sm text-gray-700  hover:bg-gray-100  transition-colors duration-200"
              @click="closeUserMenu"
            >
              設定
            </NuxtLink>
            <hr class="my-1 border-gray-200 " />
            <button
              @click="logout"
              class="w-full text-left px-4 py-2 text-sm text-gray-700  hover:bg-gray-100  transition-colors duration-200"
            >
              登出
            </button>
          </div>
        </transition>
      </div>
    </div>

    <!-- Search Modal -->
    <transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="showSearch"
        class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-start justify-center pt-20"
        @click="closeSearch"
      >
        <div
          class="bg-white  rounded-lg shadow-xl w-full max-w-lg mx-4"
          @click.stop
        >
          <div class="p-4">
            <input
              ref="searchInput"
              v-model="searchQuery"
              type="text"
              placeholder="搜尋..."
              class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent  "
            />
          </div>
        </div>
      </div>
    </transition>
  </header>
</template>

<script setup>
import {
  Bars3Icon,
  MagnifyingGlassIcon,
  GlobeAltIcon,
  BellIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  ExclamationCircleIcon,
  UserPlusIcon,
  DocumentTextIcon,
  ShieldExclamationIcon,
  InformationCircleIcon,
  WrenchScrewdriverIcon
} from '@heroicons/vue/24/outline'

// Removed i18n usage to prevent warnings
// const { t, locale, locales } = useI18n()
const route = useRoute()
const sidebarStore = useSidebarStore()
const { toggleMobileSidebar } = sidebarStore

const notificationsStore = useNotificationsStore()
const { recentNotifications, unreadCount, markAsRead, markAllAsRead, clearReadNotifications } = notificationsStore

// Breadcrumb logic
const pageTitle = computed(() => {
  const titles = {
    '/': '儀表板',
    '/dashboard': '儀表板',
    '/dashboard/analytics': '數據分析',
    '/cases/pending': '待處理案件',
    '/cases/submittable': '可送件案件',
    '/cases/progress': '進行中案件',
    '/cases/completed': '已完成案件',
    '/cases/negotiated': '協商客戶',
    '/sales/customers': '客戶資料',
    '/sales/tracking': '追蹤管理',
    '/sales/applications': '進件資料',
    '/sales/reports': '銷售報表',
    '/sales/statistics': '業績統計',
    '/settings': '設定',
    '/settings/websites': '網站名稱管理',
    '/settings/system': '系統設定',
    '/settings/users': '用戶管理',
    '/settings/permissions': '權限管理',
    '/settings/line': 'LINE 整合',
    '/reports/website-performance': '每日網站績效',
    '/reports/applications': '進件統計',
    '/reports/disbursement': '撥款統計',
    '/reports/sales': '銷售分析',
    '/reports/customers': '客戶分析',
    '/reports/accounting': '會計報表',
    '/chat': '聊天室',
    '/bank-records': '銀行交涉紀錄'
  }
  return titles[route.path] || '頁面'
})

const currentPageTitle = computed(() => pageTitle.value)

const breadcrumbItems = computed(() => {
  const pathSegments = route.path.split('/').filter(segment => segment)
  const items = []
  
  let currentPath = ''
  for (let i = 0; i < pathSegments.length; i++) {
    currentPath += '/' + pathSegments[i]
    
    const segmentTitles = {
      '/dashboard': '儀表板',
      '/dashboard/analytics': '數據分析',
      '/cases': '案件管理',
      '/cases/pending': '待處理案件',
      '/cases/submittable': '可送件案件',
      '/cases/progress': '進行中案件',
      '/cases/completed': '已完成案件',
      '/cases/negotiated': '協商客戶',
      '/sales': '業務管理',
      '/sales/customers': '客戶資料',
      '/sales/tracking': '追蹤管理',
      '/sales/applications': '進件資料',
      '/sales/reports': '銷售報表',
      '/sales/statistics': '業績統計',
      '/settings': '設定',
      '/settings/websites': '網站名稱管理',
      '/settings/system': '系統設定',
      '/settings/users': '用戶管理',
      '/settings/permissions': '權限管理',
      '/settings/line': 'LINE 整合',
      '/reports': '統計報表',
      '/reports/website-performance': '每日網站績效',
      '/reports/applications': '進件統計',
      '/reports/disbursement': '撥款統計',
      '/reports/sales': '銷售分析',
      '/reports/customers': '客戶分析',
      '/reports/accounting': '會計報表',
      '/chat': '聊天室',
      '/bank-records': '銀行交涉紀錄'
    }
    
    const title = segmentTitles[currentPath] || pathSegments[i]
    
    items.push({
      name: title,
      href: currentPath
    })
  }
  
  return items
})

const showSearch = ref(false)
const showLanguage = ref(false)
const showNotifications = ref(false)
const showUserMenu = ref(false)
const searchQuery = ref('')
const searchInput = ref(null)

const languages = computed(() => [
  { code: 'zh-TW', name: '繁體中文', flag: '🇹🇼' },
  { code: 'en', name: 'English', flag: '🇺🇸' }
])

const toggleSearch = () => {
  showSearch.value = !showSearch.value
  if (showSearch.value) {
    nextTick(() => {
      searchInput.value?.focus()
    })
  }
}

const closeSearch = () => {
  showSearch.value = false
  searchQuery.value = ''
}

const toggleLanguage = () => {
  showLanguage.value = !showLanguage.value
  showNotifications.value = false
  showUserMenu.value = false
}

const selectLanguage = (lang) => {
  // Language switching functionality disabled for now
  console.log('Language selected:', lang.code)
  showLanguage.value = false
}


const toggleNotifications = () => {
  showNotifications.value = !showNotifications.value
  showLanguage.value = false
  showUserMenu.value = false
}

const toggleUserMenu = () => {
  showUserMenu.value = !showUserMenu.value
  showLanguage.value = false
  showNotifications.value = false
}

const closeUserMenu = () => {
  showUserMenu.value = false
}

const authStore = useAuthStore()

const logout = () => {
  authStore.logout()
  showUserMenu.value = false
}

const getNotificationIcon = (iconName) => {
  const iconComponents = {
    ExclamationCircleIcon,
    UserPlusIcon,
    DocumentTextIcon,
    ShieldExclamationIcon,
    InformationCircleIcon,
    WrenchScrewdriverIcon
  }
  return iconComponents[iconName] || InformationCircleIcon
}

// Close dropdowns when clicking outside
onMounted(() => {
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.relative')) {
      showLanguage.value = false
      showNotifications.value = false
      showUserMenu.value = false
    }
  })
  
  // Start real-time notifications simulation
  notificationsStore.simulateRealTimeNotifications()
})
</script>