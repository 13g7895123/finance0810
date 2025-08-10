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
              <option value="active">活躍</option>
              <option value="inactive">非活躍</option>
              <option value="potential">潛在客戶</option>
            </select>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
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
              v-for="customer in filteredCustomers" 
              :key="customer.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 w-10 h-10">
                    <img 
                      :src="customer.avatar" 
                      :alt="customer.name"
                      class="w-10 h-10 rounded-full"
                    />
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ customer.name }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                      {{ customer.company }}
                    </div>
                  </div>
                </div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900 dark:text-white">{{ customer.email }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ customer.phone }}</div>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  :class="getStatusClass(customer.status)"
                >
                  {{ getStatusText(customer.status) }}
                </span>
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                {{ formatDate(customer.lastContact) }}
              </td>
              
              <td v-if="!authStore.isSales" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                {{ customer.assignedSales }}
              </td>
              
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                  查看
                </button>
                <button 
                  v-if="authStore.hasPermission('customer_management') || customer.assignedSalesId === authStore.user?.id"
                  class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"
                >
                  編輯
                </button>
              </td>
            </tr>
          </tbody>
        </table>
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

// 搜尋和篩選
const searchQuery = ref('')
const statusFilter = ref('')

// 客戶統計
const customerStats = ref({
  total: 156,
  active: 89,
  new: 12,
  conversionRate: 65
})

// 模擬客戶數據
const customers = ref([
  {
    id: 1,
    name: '王志明',
    company: '台北資訊科技',
    email: 'wang@taipei-tech.com',
    phone: '02-1234-5678',
    status: 'active',
    lastContact: new Date('2024-08-07'),
    assignedSales: '李小姐',
    assignedSalesId: 3,
    avatar: 'https://ui-avatars.com/api/?name=王志明&background=6366f1&color=fff'
  },
  {
    id: 2,
    name: '陳美玲',
    company: '高雄建設公司',
    email: 'chen@kaohsiung-build.com',
    phone: '07-9876-5432',
    status: 'potential',
    lastContact: new Date('2024-08-06'),
    assignedSales: '陳先生',
    assignedSalesId: 4,
    avatar: 'https://ui-avatars.com/api/?name=陳美玲&background=22c55e&color=fff'
  },
  {
    id: 3,
    name: '林建國',
    company: '新竹電子',
    email: 'lin@hsinchu-electronics.com',
    phone: '03-5555-1234',
    status: 'active',
    lastContact: new Date('2024-08-05'),
    assignedSales: '李小姐',
    assignedSalesId: 3,
    avatar: 'https://ui-avatars.com/api/?name=林建國&background=f97316&color=fff'
  },
  {
    id: 4,
    name: '黃淑芬',
    company: '桃園醫療集團',
    email: 'huang@taoyuan-medical.com',
    phone: '03-3333-7890',
    status: 'inactive',
    lastContact: new Date('2024-07-28'),
    assignedSales: '陳先生',
    assignedSalesId: 4,
    avatar: 'https://ui-avatars.com/api/?name=黃淑芬&background=8b5cf6&color=fff'
  },
  {
    id: 5,
    name: '劉志強',
    company: '台中製造業',
    email: 'liu@taichung-manufacturing.com',
    phone: '04-2222-4567',
    status: 'active',
    lastContact: new Date('2024-08-08'),
    assignedSales: '李小姐',
    assignedSalesId: 3,
    avatar: 'https://ui-avatars.com/api/?name=劉志強&background=06b6d4&color=fff'
  }
])

// 過濾客戶列表
const filteredCustomers = computed(() => {
  let result = customers.value

  // 業務人員只能看到自己負責的客戶
  if (authStore.isSales) {
    result = result.filter(customer => customer.assignedSalesId === authStore.user?.id)
  }

  // 搜尋過濾
  if (searchQuery.value.trim()) {
    result = result.filter(customer =>
      customer.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      customer.company.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      customer.email.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
  }

  // 狀態過濾
  if (statusFilter.value) {
    result = result.filter(customer => customer.status === statusFilter.value)
  }

  return result
})

// 狀態樣式
const getStatusClass = (status) => {
  const classes = {
    'active': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    'inactive': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    'potential': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
  }
  return classes[status] || classes.inactive
}

// 狀態文字
const getStatusText = (status) => {
  const texts = {
    'active': '活躍',
    'inactive': '非活躍',
    'potential': '潛在客戶'
  }
  return texts[status] || '未知'
}

// 日期格式化
const formatDate = (date) => {
  return new Date(date).toLocaleDateString('zh-TW', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

// 設定頁面標題
useHead({
  title: '客戶管理 - 金融管理系統'
})
</script>