<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">待處理案件</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">
          總共 {{ filteredCases.length }} 筆待處理案件
        </p>
      </div>
      <button
        @click="showCreateModal = true"
        class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg flex items-center space-x-2 transition-colors duration-200"
      >
        <PlusIcon class="w-5 h-5" />
        <span>新增案件</span>
      </button>
    </div>

    <!-- 篩選和搜尋 -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- 搜尋框 -->
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="搜尋客戶姓名或手機號碼..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <!-- 網站篩選 -->
        <select
          v-model="selectedWebsite"
          class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">所有網站</option>
          <option value="熊好貸">熊好貸</option>
          <option value="網站A">網站A</option>
          <option value="網站B">網站B</option>
        </select>

        <!-- 地區篩選 -->
        <select
          v-model="selectedRegion"
          class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">所有地區</option>
          <option value="台北">台北</option>
          <option value="新北">新北</option>
          <option value="桃園">桃園</option>
          <option value="台中">台中</option>
          <option value="台南">台南</option>
          <option value="高雄">高雄</option>
        </select>

        <!-- 承辦業務篩選 -->
        <select
          v-model="selectedSales"
          class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">所有業務</option>
          <option v-for="sales in salesStaff" :key="sales.id" :value="sales.name">
            {{ sales.name }}
          </option>
        </select>
      </div>
    </div>

    <!-- 案件列表 -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
      <!-- 表格標題 -->
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">待處理案件列表</h3>
      </div>

      <!-- 表格內容 -->
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                序號
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                日期/時間
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                承辦業務
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                地區
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                網站
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                客戶資訊
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                進線狀態
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                追蹤狀態
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                操作
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="caseItem in filteredCases" :key="caseItem.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                {{ caseItem.id }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                <div>{{ formatDate(caseItem.createdAt) }}</div>
                <div class="text-xs">{{ formatTime(caseItem.createdAt) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <img :src="caseItem.assignedSales.avatar" :alt="caseItem.assignedSales.name" class="w-8 h-8 rounded-full mr-2" />
                  <span class="text-sm font-medium text-gray-900 dark:text-white">{{ caseItem.assignedSales.name }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300 rounded-full">
                  {{ caseItem.region }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300 rounded-full">
                  {{ caseItem.website }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ caseItem.customerName }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ caseItem.phone }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  class="px-2 py-1 text-xs font-medium rounded-full"
                  :class="getInquiryStatusClass(caseItem.inquiryStatus)"
                >
                  {{ caseItem.inquiryStatus }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-2">
                  <span 
                    class="px-2 py-1 text-xs font-medium rounded-full"
                    :class="getTrackingStatusClass(caseItem.trackingStatus)"
                  >
                    {{ caseItem.trackingStatus }}
                  </span>
                  <div v-if="caseItem.needsAttention" class="w-2 h-2 bg-red-500 rounded-full" title="需要注意"></div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                <button
                  @click="editCase(caseItem)"
                  class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                >
                  編輯
                </button>
                <button
                  @click="viewCase(caseItem)"
                  class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                >
                  查看
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 分頁 -->
      <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div class="text-sm text-gray-500 dark:text-gray-400">
          顯示 {{ startIndex + 1 }} 到 {{ endIndex }} 筆，共 {{ filteredCases.length }} 筆
        </div>
        <div class="flex space-x-2">
          <button
            @click="previousPage"
            :disabled="currentPage === 1"
            class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            上一頁
          </button>
          <button
            @click="nextPage"
            :disabled="currentPage === totalPages"
            class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            下一頁
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { 
  PlusIcon, 
  MagnifyingGlassIcon 
} from '@heroicons/vue/24/outline'

definePageMeta({
  middleware: 'auth'
})

const authStore = useAuthStore()

// 響應式數據
const searchQuery = ref('')
const selectedWebsite = ref('')
const selectedRegion = ref('')
const selectedSales = ref('')
const currentPage = ref(1)
const itemsPerPage = 10
const showCreateModal = ref(false)

// 業務人員列表
const salesStaff = ref([
  { id: 1, name: '業務員李小姐', avatar: 'https://ui-avatars.com/api/?name=李小姐&background=f97316&color=fff' },
  { id: 2, name: '業務員陳先生', avatar: 'https://ui-avatars.com/api/?name=陳先生&background=8b5cf6&color=fff' },
  { id: 3, name: '業務員王小姐', avatar: 'https://ui-avatars.com/api/?name=王小姐&background=22c55e&color=fff' }
])

// 模擬案件數據
const allCases = ref([
  {
    id: 'C001',
    customerName: '林大明',
    phone: '0912345678',
    region: '台北',
    website: '熊好貸',
    channel: '官網表單',
    assignedSales: salesStaff.value[0],
    inquiryStatus: '已聯絡',
    trackingStatus: '待追蹤',
    notes: '客戶表示有意願申請汽車貸款',
    createdAt: new Date('2024-08-08T09:30:00'),
    needsAttention: true
  },
  {
    id: 'C002',
    customerName: '張美芳',
    phone: '0923456789',
    region: '新北',
    website: '網站A',
    channel: 'LINE進件',
    assignedSales: salesStaff.value[1],
    inquiryStatus: '未聯絡',
    trackingStatus: '新進件',
    notes: '機車貸款需求',
    createdAt: new Date('2024-08-08T10:15:00'),
    needsAttention: false
  },
  {
    id: 'C003',
    customerName: '王志強',
    phone: '0934567890',
    region: '桃園',
    website: '熊好貸',
    channel: '官網表單',
    assignedSales: salesStaff.value[0],
    inquiryStatus: '已聯絡',
    trackingStatus: '資料補齊中',
    notes: '手機貸款，需補齊收入證明',
    createdAt: new Date('2024-08-08T11:45:00'),
    needsAttention: true
  },
  {
    id: 'C004',
    customerName: '劉小華',
    phone: '0945678901',
    region: '台中',
    website: '網站B',
    channel: '電話進件',
    assignedSales: salesStaff.value[2],
    inquiryStatus: '聯絡中',
    trackingStatus: '待回電',
    notes: '汽車貸款詢問',
    createdAt: new Date('2024-08-08T14:20:00'),
    needsAttention: false
  },
  {
    id: 'C005',
    customerName: '陳淑華',
    phone: '0956789012',
    region: '高雄',
    website: '熊好貸',
    channel: 'LINE進件',
    assignedSales: salesStaff.value[1],
    inquiryStatus: '未聯絡',
    trackingStatus: '新進件',
    notes: '機車貸款需求，急件',
    createdAt: new Date('2024-08-08T15:10:00'),
    needsAttention: true
  }
])

// 計算過濾後的案件
const filteredCases = computed(() => {
  let cases = allCases.value

  // 權限過濾 - 業務人員只能看到自己的案件
  if (authStore.isSales && !authStore.hasPermission('all_access')) {
    cases = cases.filter(c => c.assignedSales.name === authStore.user?.name)
  }

  // 搜尋過濾
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    cases = cases.filter(c => 
      c.customerName.toLowerCase().includes(query) ||
      c.phone.includes(query)
    )
  }

  // 網站過濾
  if (selectedWebsite.value) {
    cases = cases.filter(c => c.website === selectedWebsite.value)
  }

  // 地區過濾
  if (selectedRegion.value) {
    cases = cases.filter(c => c.region === selectedRegion.value)
  }

  // 業務過濾
  if (selectedSales.value) {
    cases = cases.filter(c => c.assignedSales.name === selectedSales.value)
  }

  return cases
})

// 分頁計算
const totalPages = computed(() => Math.ceil(filteredCases.value.length / itemsPerPage))
const startIndex = computed(() => (currentPage.value - 1) * itemsPerPage)
const endIndex = computed(() => Math.min(startIndex.value + itemsPerPage, filteredCases.value.length))

// 分頁方法
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

// 狀態樣式類別
const getInquiryStatusClass = (status) => {
  const statusMap = {
    '未聯絡': 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300',
    '聯絡中': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300',
    '已聯絡': 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300'
  }
  return statusMap[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
}

const getTrackingStatusClass = (status) => {
  const statusMap = {
    '新進件': 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
    '待追蹤': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300',
    '資料補齊中': 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-300',
    '待回電': 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300'
  }
  return statusMap[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
}

// 日期格式化
const formatDate = (date) => {
  return new Date(date).toLocaleDateString('zh-TW', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  })
}

const formatTime = (date) => {
  return new Date(date).toLocaleTimeString('zh-TW', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

// 操作方法
const editCase = (caseItem) => {
  // 編輯案件邏輯
  console.log('編輯案件:', caseItem)
}

const viewCase = (caseItem) => {
  // 查看案件詳情邏輯
  console.log('查看案件:', caseItem)
}

// 頁面標題
useHead({
  title: '待處理案件 - 金融管理系統'
})
</script>

<style scoped>
/* 表格響應式處理 */
@media (max-width: 768px) {
  table, thead, tbody, th, td, tr {
    display: block;
  }
  
  thead tr {
    position: absolute;
    top: -9999px;
    left: -9999px;
  }
  
  tr {
    border: 1px solid #ccc;
    margin-bottom: 10px;
    padding: 10px;
  }
  
  td {
    border: none;
    position: relative;
    padding-left: 50% !important;
  }
  
  td:before {
    content: attr(data-label);
    position: absolute;
    left: 6px;
    width: 45%;
    padding-right: 10px;
    white-space: nowrap;
    font-weight: bold;
  }
}
</style>