<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">網路進線</h1>
        <p class="text-gray-600 mt-2">顯示來自 WP 表單的進件（可搜尋、編輯、刪除）</p>
      </div>
    </div>

    <!-- 統計卡片 -->
    <div v-if="false" class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <StatsCard
        title="網路進線"
        :value="caseStats.pending"
        description="需要處理的進件"
        icon="DocumentTextIcon"
        iconColor="blue"
        :trend="2.1"
      />
      
      <StatsCard
        title="今日新增"
        :value="caseStats.today"
        description="今天新增的進件"
        icon="PlusCircleIcon"
        iconColor="green"
        :trend="15.2"
      />
      
      <StatsCard
        title="本週處理"
        :value="caseStats.thisWeek"
        description="本週已處理"
        icon="CheckCircleIcon"
        iconColor="purple"
        :trend="8.3"
      />
      
      <StatsCard
        title="處理率"
        :value="caseStats.processingRate"
        format="percentage"
        description="案件處理效率"
        icon="ChartBarIcon"
        iconColor="yellow"
        :trend="3.2"
      />
    </div>

    <!-- 案件列表 -->
    <DataTable
      title="網路進線列表"
      :columns="pendingTableColumns"
      :data="filteredLeads"
      :loading="loading"
      :error="loadError"
      :search-query="searchQuery"
      search-placeholder="搜尋姓名/手機/Email/LINE/網站... (至少2個字符)"
      :show-search-icon="false"
      :current-page="currentPage"
      :items-per-page="itemsPerPage"
      loading-text="載入中..."
      empty-text="沒有待處理案件"
      @search="handleSearch"
      @refresh="loadLeads"
      @retry="loadLeads"
      @page-change="handlePageChange"
      @page-size-change="handlePageSizeChange"
    >
      <!-- Filter Controls -->
      <template #filters>
        <select
          v-if="authStore.hasPermission && authStore.hasPermission('customer_management')"
          v-model="selectedAssignee"
          class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="all">全部承辦</option>
          <option value="null">未指派</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </template>
      
      <!-- Action Buttons -->
      <template #actions>
        <!-- 可以在這裡添加新增案件等按鈕 -->
      </template>
      
      <!-- Case Status Cell -->
      <template #cell-case_status="{ item }">
        <select
          :value="item.case_status || 'unassigned'"
          @change="updateCaseStatus(item, $event.target.value)"
          class="w-full px-1 py-0.5 text-xs border border-gray-300 rounded bg-white text-gray-900 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
        >
          <option v-for="option in CASE_STATUS_OPTIONS" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </template>

      <!-- Case Number Cell -->
      <template #cell-case_number="{ item }">
        <div class="text-sm font-medium text-gray-900">
          {{ generateCaseNumber(item) }}
        </div>
      </template>

      <!-- Website Cell -->
      <template #cell-website="{ item }">
        <div class="text-sm font-medium text-gray-900">
          {{ getWebsiteInfo(item.payload?.['頁面_URL'] || item.source).name }}
        </div>
      </template>
      
      <!-- Channel Cell -->
      <template #cell-channel="{ item }">
        <span class="text-sm text-gray-900">
          {{ item.channel === 'wp_form' ? '網站表單' : (item.channel === 'lineoa' ? '官方賴' : (item.channel === 'email' ? 'Email' : (item.channel === 'phone' ? '電話' : (item.channel || '-')))) }}
        </span>
      </template>
      
      <!-- DateTime Cell -->
      <template #cell-datetime="{ item }">
        <div>
          <div class="text-sm text-gray-900">{{ formatDate(item.created_at) }}</div>
          <div class="text-xs text-gray-500">{{ formatTime(item.created_at) }}</div>
        </div>
      </template>
      
      <!-- Assignee Cell -->
      <template #cell-assignee="{ item }">
        <div class="flex items-center space-x-2">
          <span class="text-sm text-gray-900">{{ item.assignee?.name || '未指派' }}</span>
          <button 
            v-if="!item.assignee && authStore.hasPermission && authStore.hasPermission('customer_management')"
            @click="openAssignModal(item)"
            class="inline-flex items-center justify-center w-6 h-6 text-blue-600 hover:text-white hover:bg-blue-600 rounded transition-colors duration-200 relative group"
            title="指派業務"
          >
            <Icon name="heroicons:user-plus" class="w-4 h-4" />
            <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-gray-800 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              指派業務
            </span>
          </button>
        </div>
      </template>
      
      <!-- Contact Info Cell -->
      <template #cell-contact_info="{ item }">
        <div>
          <div class="text-sm text-gray-900">{{ item.email || '未提供' }}</div>
          <div class="text-xs text-gray-500">{{ item.phone || '未提供' }}</div>
        </div>
      </template>
      
      <!-- LINE Info Cell -->
      <template #cell-line_info="{ item }">
        <div v-if="item.line_user_info && item.is_line_user_id" class="flex items-center space-x-2">
          <!-- LINE 頭像 -->
          <img 
            v-if="item.line_user_info.picture_url" 
            :src="item.line_user_info.picture_url" 
            :alt="item.line_user_info.display_name || 'LINE用戶'"
            class="w-6 h-6 rounded-full object-cover"
            @error="$event.target.style.display='none'"
          />
          <div v-else class="w-6 h-6 rounded-full bg-green-500 flex items-center justify-center text-white text-xs">
            L
          </div>
          <!-- LINE 名稱 -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center space-x-1">
              <span class="text-xs font-medium text-gray-900 truncate">
                {{ item.line_user_info.display_name || '未設定名稱' }}
              </span>
              <button
                @click="openLineNameModal(item)"
                class="text-blue-500 hover:text-blue-700 text-xs"
                title="編輯LINE名稱"
              >
                ✏️
              </button>
            </div>
          </div>
        </div>
        <div v-else-if="item.line_id && !item.is_line_user_id" class="flex items-center space-x-2">
          <div class="w-6 h-6 rounded-full bg-gray-400 flex items-center justify-center text-white text-xs">
            @
          </div>
          <span class="text-xs text-gray-900">{{ item.line_id }}</span>
        </div>
        <div v-else class="text-xs text-gray-400">
          未綁定
        </div>
      </template>
      
      <!-- Location Cell -->
      <template #cell-location="{ item }">
        <div>
          <div class="text-sm text-gray-900">
            {{ item.payload?.['房屋區域'] || item.payload?.['所在地區'] || '未提供' }}
          </div>
          <div class="text-xs text-gray-500 truncate max-w-[120px]">
            {{ item.payload?.['房屋地址'] || '' }}
          </div>
        </div>
      </template>
      
      <!-- Amount Cell -->
      <template #cell-amount="{ item }">
        <span class="text-sm text-gray-900">
          {{ item.payload?.['資金需求'] || '未填寫' }}
        </span>
      </template>
      
      <!-- Purpose Cell -->
      <template #cell-purpose="{ item }">
        <span class="text-sm text-gray-900">
          {{ item.payload?.['貸款需求'] || '未填寫' }}
        </span>
      </template>
      
      <!-- Custom Fields Cell -->
      <template #cell-custom_fields="{ item }">
        <div v-if="visibleCaseFields.length > 0" class="space-y-1">
          <div v-for="cf in visibleCaseFields.slice(0, 2)" :key="cf.id" class="text-xs">
            <span class="text-gray-500">{{ cf.label }}:</span>
            <span class="text-gray-900 ml-1">
              {{ formatCustomFieldValue(item.payload?.[cf.key], cf) }}
            </span>
          </div>
          <div v-if="visibleCaseFields.length > 2" class="text-xs text-gray-400">
            +{{ visibleCaseFields.length - 2 }} 個欄位
          </div>
        </div>
        <span v-else class="text-xs text-gray-400">無自定義欄位</span>
      </template>
      
      <!-- Actions Cell -->
      <template #cell-actions="{ item }">
        <div class="flex items-center space-x-2 justify-end">
          <!-- 查看 -->
          <button 
            @click="viewLead(item)"
            class="group relative inline-flex items-center justify-center p-2 text-blue-600 hover:text-white hover:bg-blue-600 rounded-lg transition-all duration-200"
            title="查看詳情"
          >
            <EyeIcon class="w-4 h-4" />
            <!-- Tooltip -->
            <div class="absolute bottom-full mb-2 hidden group-hover:block px-2 py-1 text-xs text-white bg-gray-900 rounded whitespace-nowrap z-10">
              查看詳情
            </div>
          </button>
          
          <!-- 編輯 -->
          <NuxtLink
            :to="`/cases/pending/edit/${item.id}`"
            class="group relative inline-flex items-center justify-center p-2 text-gray-600 hover:text-white hover:bg-gray-600 rounded-lg transition-all duration-200"
            title="編輯"
          >
            <PencilIcon class="w-4 h-4" />
            <!-- Tooltip -->
            <div class="absolute bottom-full mb-2 hidden group-hover:block px-2 py-1 text-xs text-white bg-gray-900 rounded whitespace-nowrap z-10">
              編輯
            </div>
          </NuxtLink>
          
          <!-- 轉送件 -->
          <button 
            v-if="item.customer_id"
            @click="openConvert(item)"
            class="group relative inline-flex items-center justify-center p-2 text-purple-600 hover:text-white hover:bg-purple-600 rounded-lg transition-all duration-200"
            title="轉送件"
          >
            <ArrowRightIcon class="w-4 h-4" />
            <!-- Tooltip -->
            <div class="absolute bottom-full mb-2 hidden group-hover:block px-2 py-1 text-xs text-white bg-gray-900 rounded whitespace-nowrap z-10">
              轉送件
            </div>
          </button>
          
          <!-- 刪除 -->
          <button 
            @click="onDelete(item)"
            class="group relative inline-flex items-center justify-center p-2 text-red-600 hover:text-white hover:bg-red-600 rounded-lg transition-all duration-200"
            title="刪除"
          >
            <TrashIcon class="w-4 h-4" />
            <!-- Tooltip -->
            <div class="absolute bottom-full mb-2 hidden group-hover:block px-2 py-1 text-xs text-white bg-gray-900 rounded whitespace-nowrap z-10">
              刪除
            </div>
          </button>
        </div>
      </template>
    </DataTable>


    <!-- View Modal -->
    <div v-if="viewOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 mt-0" @click.self="closeView">
      <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900">案件詳情</h3>
          <button 
            @click="closeView"
            class="text-gray-400 hover:text-gray-600"
          >
            ✕
          </button>
        </div>
        
        <div v-if="selectedLead" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">網站</label>
              <p class="text-gray-900">{{ getWebsiteInfo(selectedLead.payload?.['頁面_URL'] || selectedLead.source).name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">來源管道</label>
              <p class="text-gray-900">{{ selectedLead.channel || 'wp_form' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">時間</label>
              <p class="text-gray-900">{{ formatDate(selectedLead.created_at) }} {{ formatTime(selectedLead.created_at) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">承辦業務</label>
              <p class="text-gray-900">{{ selectedLead.assignee?.name || '未指派' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Email</label>
              <p class="text-gray-900">{{ selectedLead.email || '未提供' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">LINE ID</label>
              <p class="text-gray-900">{{ selectedLead.line_id || '未提供' }}</p>
            </div>
          </div>
          
          <div v-if="selectedLead.notes">
            <label class="block text-sm font-medium text-gray-700">備註</label>
            <p class="text-gray-900 whitespace-pre-wrap">{{ selectedLead.notes }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Convert Modal -->
    <div v-if="convertOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 mt-0" @click.self="closeConvert">
      <div class="bg-white rounded-lg p-6 w-full max-w-lg">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">送件（建立案件）</h3>
        <form @submit.prevent="doConvert" class="space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">貸款金額</label>
              <input v-model.number="convertForm.loan_amount" required type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">貸款類型</label>
              <input v-model="convertForm.loan_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-gray-900 mb-1">備註</label>
              <textarea v-model="convertForm.notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
          </div>
          <div class="flex justify-end space-x-3 pt-2">
            <button type="button" class="px-4 py-2 border border-gray-300 rounded-lg" @click="closeConvert">取消</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">送件</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Assign Modal -->
    <div v-if="assignOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 mt-0" @click.self="closeAssign">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">指派承辦業務</h3>
        <form @submit.prevent="doAssign" class="space-y-4">
          <div v-if="assignLead" class="mb-4">
            <div class="text-sm text-gray-900 font-semibold mb-2">案件資訊：</div>
            <div class="bg-gray-50 p-3 rounded-lg">
              <div class="text-sm text-gray-900"><span class="font-medium">案件編號：</span>{{ generateCaseNumber(assignLead) }}</div>
              <div class="text-sm text-gray-900"><span class="font-medium">Email：</span>{{ assignLead.email || '未提供' }}</div>
              <div class="text-sm text-gray-900"><span class="font-medium">LINE ID：</span>{{ assignLead.line_id || '未提供' }}</div>
            </div>
          </div>
          
          <div>
            <label class="block text-sm font-semibold text-gray-900 mb-2">選擇承辦業務 <span class="text-red-500">*</span></label>
            <select 
              v-model="assignForm.assigned_to" 
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">請選擇業務人員</option>
              <option v-for="user in users.filter(u => u.roles?.[0]?.name === 'staff')" :key="user.id" :value="user.id">
                {{ user.name || user.email }} (業務)
              </option>
            </select>
          </div>

          <div class="flex justify-end space-x-3 pt-4">
            <button 
              type="button" 
              class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50" 
              @click="closeAssign"
            >
              取消
            </button>
            <button 
              type="submit" 
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed" 
              :disabled="saving || !assignForm.assigned_to"
            >
              {{ saving ? '指派中...' : '確認指派' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- LINE Name Edit Modal -->
    <div v-if="lineNameModalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 mt-0" @click.self="closeLineNameModal">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">編輯LINE名稱</h3>
        <form @submit.prevent="saveLineNameModal" class="space-y-4">
          <div v-if="lineNameLead" class="mb-4">
            <div class="text-sm text-gray-900 font-semibold mb-2">LINE使用者資訊：</div>
            <div class="bg-gray-50 p-3 rounded-lg flex items-center space-x-3">
              <img 
                v-if="lineNameLead.line_user_info?.picture_url" 
                :src="lineNameLead.line_user_info.picture_url" 
                :alt="lineNameLead.line_user_info.display_name || 'LINE用戶'"
                class="w-10 h-10 rounded-full object-cover"
                @error="$event.target.style.display='none'"
              />
              <div v-else class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center text-white text-sm">
                L
              </div>
              <div>
                <div class="text-sm text-gray-900 font-medium">{{ lineNameLead.line_user_info?.display_name || '未設定名稱' }}</div>
                <div class="text-xs text-gray-500">案件編號：{{ generateCaseNumber(lineNameLead) }}</div>
              </div>
            </div>
          </div>
          
          <div>
            <label class="block text-sm font-semibold text-gray-900 mb-2">LINE名稱 <span class="text-red-500">*</span></label>
            <input 
              v-model="lineNameForm.display_name" 
              required
              maxlength="100"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="請輸入LINE名稱"
            />
          </div>

          <div class="flex justify-end space-x-3 pt-4">
            <button 
              type="button" 
              class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50" 
              @click="closeLineNameModal"
            >
              取消
            </button>
            <button 
              type="submit" 
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed" 
              :disabled="saving || !lineNameForm.display_name?.trim()"
            >
              {{ saving ? '儲存中...' : '儲存' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { 
  EyeIcon,
  PencilIcon,
  ArrowRightIcon,
  TrashIcon,
  DocumentTextIcon,
  PlusCircleIcon,
  CheckCircleIcon,
  ChartBarIcon
} from '@heroicons/vue/24/outline'

// 明確匯入組件
import StatsCard from '~/components/StatsCard.vue'
import DataTable from '~/components/DataTable.vue'
import { formatters } from '~/utils/tableColumns'

definePageMeta({ middleware: 'auth' })

const authStore = useAuthStore()
const { alert, success, error: showError, confirm } = useNotification()
const { list: listLeads, updateOne: updateLead, removeOne: removeLead, convertToCase } = useLeads()
const { getUsers } = useUsers()
const { list: listCustomFields } = useCustomFields()

// Point 50: Website API integration
const { get: apiGet } = useApi()

// 搜尋和篩選
const searchQuery = ref('')
const selectedAssignee = ref('all')

// 載入狀態
const loading = ref(false)
const loadError = ref(null)

// 案件數據
const leads = ref([])
const users = ref([])
const caseStats = ref({
  pending: 0,
  today: 0,
  thisWeek: 0,
  processingRate: 0
})

// Point 50: Website data
const websites = ref([])
const websiteOptions = ref([])

// 模態窗口狀態
const viewOpen = ref(false)
const convertOpen = ref(false)
const assignOpen = ref(false)
const lineNameModalOpen = ref(false)
const selectedLead = ref(null)
const convertLead = ref(null)
const assignLead = ref(null)
const lineNameLead = ref(null)

// 表單提交狀態
const saving = ref(false)

// Pagination
const currentPage = ref(1)
const itemsPerPage = ref(5) // Default to 5 items per page as requested

const totalPages = computed(() => Math.ceil(filteredLeads.value.length / itemsPerPage.value))

// 自定義欄位
const caseFields = ref([])

// LINE名稱編輯相關狀態 (已改為modal方式)

// 選項配置
const CHANNEL_OPTIONS = [
  { value: 'wp_form', label: '網站表單' },
  { value: 'lineoa', label: '官方賴' },
  { value: 'email', label: 'Email' },
  { value: 'phone', label: '電話' }
]

const STATUS_OPTIONS = [
  { value: 'pending', label: '待處理' },
  { value: 'intake', label: '已進件' },
  { value: 'approved', label: '已核准' },
  { value: 'disbursed', label: '已撥款' },
  { value: 'tracking', label: '追蹤中' },
  { value: 'blacklist', label: '黑名單' }
]

const CASE_STATUS_OPTIONS = [
  { value: 'unassigned', label: '未指派' },
  { value: 'valid_customer', label: '有效客' },
  { value: 'invalid_customer', label: '無效客' },
  { value: 'customer_service', label: '客服' },
  { value: 'blacklist', label: '黑名單' },
  { value: 'approved_disbursed', label: '核准撥款' },
  { value: 'conditional', label: '附條件' },
  { value: 'declined', label: '婉拒' },
  { value: 'follow_up', label: '追蹤管理' }
]


const convertForm = reactive({
  loan_amount: null,
  loan_type: '',
  notes: ''
})

const assignForm = reactive({
  assigned_to: null
})

const lineNameForm = reactive({
  display_name: ''
})

// 表格配置
// 可連絡時間欄位可隱藏設置：若未來需要顯示可連絡時間，可在此調整 columns 配置
const pendingTableColumns = computed(() => {
  return [
    {
      key: 'case_status',
      title: '案件狀態',
      sortable: true,
      width: '150px'
    },
    {
      key: 'case_number',
      title: '案件編號',
      sortable: true,
      width: '140px'
    },
    {
      key: 'datetime',
      title: '時間', // 可選：可連絡時間（可隱藏）
      sortable: true,
      width: '140px'
    },
    {
      key: 'assignee',
      title: '承辦業務',
      sortable: true,
      width: '100px'
    },
    {
      key: 'channel',
      title: '來源管道',
      sortable: true,
      width: '100px'
    },
    {
      key: 'line_info',
      title: 'LINE資訊',
      sortable: false,
      width: '160px'
    },
    {
      key: 'purpose',
      title: '諮詢項目',
      sortable: false,
      width: '120px'
    },
    {
      key: 'website',
      title: '網站',
      sortable: false,
      width: '140px'
    },
    {
      key: 'contact_info',
      title: '聯絡資訊',
      sortable: false,
      width: '160px'
    },
    {
      key: 'location',
      title: '地區/地址',
      sortable: false,
      width: '160px'
    },
    {
      key: 'amount',
      title: '需求金額',
      sortable: false,
      width: '120px'
    },
    {
      key: 'custom_fields',
      title: '自定義欄位',
      sortable: false,
      width: '140px'
    },
    {
      key: 'actions',
      title: '操作',
      sortable: false,
      width: '160px'
    }
  ]
})

// 過濾數據
const filteredLeads = computed(() => {
  let filtered = leads.value

  // 搜尋過濾
  if (searchQuery.value && searchQuery.value.length >= 2) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(lead => {
      return (
        lead.email?.toLowerCase().includes(query) ||
        lead.line_id?.toLowerCase().includes(query) ||
        lead.payload?.['頁面_URL']?.toLowerCase().includes(query) ||
        lead.source?.toLowerCase().includes(query) ||
        lead.assignee?.name?.toLowerCase().includes(query)
      )
    })
  }

  // 承辦業務過濾
  if (selectedAssignee.value && selectedAssignee.value !== 'all') {
    if (selectedAssignee.value === 'null') {
      filtered = filtered.filter(lead => !lead.assigned_to)
    } else {
      filtered = filtered.filter(lead => lead.assigned_to === parseInt(selectedAssignee.value))
    }
  }

  return filtered
})

// 自定義欄位
const visibleCaseFields = computed(() => caseFields.value.filter(f => f.is_visible))

// 載入數據 - 改為客戶端分頁實現
const loadLeads = async () => {
  loading.value = true
  loadError.value = null
  
  try {
    // 獲取所有待處理案件，不使用分頁參數
    const { items, meta, success: ok } = await listLeads({
      status: 'pending',
      per_page: 1000  // 獲取大量數據以支持客戶端分頁
    })
    
    if (ok) {
      leads.value = items || []
      
      // 計算統計數據
      const total = leads.value.length
      const today = new Date()
      const todayCount = leads.value.filter(lead => {
        const leadDate = new Date(lead.created_at)
        return leadDate.toDateString() === today.toDateString()
      }).length
      
      const thisWeekCount = leads.value.filter(lead => {
        const leadDate = new Date(lead.created_at)
        const weekStart = new Date(today)
        weekStart.setDate(today.getDate() - today.getDay())
        return leadDate >= weekStart
      }).length
      
      caseStats.value = {
        pending: total,
        today: todayCount,
        thisWeek: thisWeekCount,
        processingRate: total > 0 ? Math.round((thisWeekCount / total) * 100) : 0
      }
    }
  } catch (err) {
    loadError.value = '載入案件數據失敗'
    console.error('Load leads error:', err)
  } finally {
    loading.value = false
  }
}

const loadUsers = async () => {
  try {
    const { success: ok, users: list } = await getUsers({ per_page: 250 })
    if (ok && Array.isArray(list)) users.value = list
  } catch (e) {
    console.warn('Load users failed:', e)
  }
}

const loadCaseFields = async () => {
  const { success, items } = await listCustomFields('case')
  if (success) caseFields.value = items
}

// Point 50: Load websites for dropdown options from website management system
const loadWebsites = async () => {
  try {
    const { data, error } = await apiGet('/websites/options')
    if (!error && Array.isArray(data)) {
      websites.value = data
      websiteOptions.value = data.map(website => ({
        value: website.domain,
        label: website.name, // 網站名稱 from management system
        website: website
      }))

    } else {
      console.warn('Failed to load websites from management system:', error)
    }
  } catch (e) {
    console.warn('Load websites failed:', e)
  }
}

// DataTable event handlers
const handleSearch = (query) => {
  searchQuery.value = query
  currentPage.value = 1 // Reset to first page when searching
}

const handlePageChange = (page) => {
  currentPage.value = page
}

const handlePageSizeChange = (size) => {
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

// 搜尋防抖
let searchTimer
watch([searchQuery, selectedAssignee], () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    // Reset to first page when search or filter changes
    currentPage.value = 1
    // 搜尋過濾已經在 computed 中處理
  }, 300)
})

// 客戶端分頁不需要在頁面變化時重新載入數據

// 使用 DataTable 內建的分頁功能，不需要手動分頁

// 模態窗口控制
const viewLead = (lead) => {
  selectedLead.value = lead
  viewOpen.value = true
}

const closeView = () => {
  viewOpen.value = false
  selectedLead.value = null
}


const onDelete = async (lead) => {
  const confirmed = await confirm(`確定刪除編號 ${lead.id} 的進件嗎？`)
  if (!confirmed) return
  
  try {
    const { error } = await removeLead(lead.id)
    if (!error) {
      await loadLeads()
      success(`編號 ${lead.id} 的進件已刪除`)
    } else {
      showError(error?.message || '刪除失敗')
    }
  } catch (err) {
    showError('系統錯誤，請稍後再試')
    console.error('Delete lead error:', err)
  }
}

// 轉送件
const openConvert = (lead) => {
  if (!lead.customer_id) {
    showError('此進件尚未綁定客戶，請先建立/綁定客戶後再送件')
    return
  }
  convertLead.value = lead
  Object.assign(convertForm, { loan_amount: null, loan_type: '', notes: '' })
  convertOpen.value = true
}

const closeConvert = () => {
  convertOpen.value = false
  convertLead.value = null
}

const doConvert = async () => {
  if (!convertLead.value) return
  
  try {
    const { error } = await convertToCase(convertLead.value.id, convertForm)
    if (!error) {
      convertOpen.value = false
      await loadLeads()
      success('案件轉換成功')
    } else {
      showError(error?.message || '轉換失敗')
    }
  } catch (err) {
    showError('系統錯誤，請稍後再試')
    console.error('Convert lead error:', err)
  }
}

// 指派業務
const openAssignModal = (lead) => {
  assignLead.value = lead
  assignForm.assigned_to = lead.assigned_to || null
  assignOpen.value = true
}

const closeAssign = () => {
  assignOpen.value = false
  assignLead.value = null
  assignForm.assigned_to = null
}

const doAssign = async () => {
  if (!assignLead.value || !assignForm.assigned_to) {
    showError('請選擇承辦業務')
    return
  }
  
  saving.value = true
  try {
    const { error } = await updateLead(assignLead.value.id, {
      assigned_to: assignForm.assigned_to
    })
    
    if (!error) {
      assignOpen.value = false
      await loadLeads()
      const assignedUser = users.value.find(u => u.id === assignForm.assigned_to)
      success(`已成功指派給 ${assignedUser?.name || '未知業務'}`)
    } else {
      showError(error?.message || '指派失敗')
    }
  } catch (err) {
    showError('系統錯誤，請稍後再試')
    console.error('Assign lead error:', err)
  } finally {
    saving.value = false
  }
}

// LINE名稱編輯方法
const openLineNameModal = (lead) => {
  lineNameLead.value = lead
  lineNameForm.display_name = lead.line_user_info?.display_name || ''
  lineNameModalOpen.value = true
}

const closeLineNameModal = () => {
  lineNameModalOpen.value = false
  lineNameLead.value = null
  lineNameForm.display_name = ''
}

const saveLineNameModal = async () => {
  if (!lineNameLead.value || !lineNameForm.display_name?.trim()) {
    showError('名稱不能為空')
    return
  }

  saving.value = true
  try {
    const { $api } = useNuxtApp()

    const { data, error } = await $api.put(`/leads/${lineNameLead.value.id}/line-name`, {
      editable_name: lineNameForm.display_name.trim()
    })

    if (!error && data?.success) {
      if (lineNameLead.value.line_user_info) {
        lineNameLead.value.line_user_info.display_name = lineNameForm.display_name.trim()
        lineNameLead.value.line_user_info.editable_name = lineNameForm.display_name.trim()
      }

      lineNameModalOpen.value = false
      await loadLeads()
      success(`LINE用戶名稱已更新：${data.old_name} → ${data.new_name}`)
    } else {
      showError(data?.message || error?.message || '更新失敗')
    }
  } catch (error) {
    showError('更新失敗，請稍後再試')
    console.error('Save LINE name error:', error)
  } finally {
    saving.value = false
  }
}

// 更新案件狀態
const updateCaseStatus = async (item, newStatus) => {
  try {
    const { patch } = useApi()

    // 直接更新lead的case_status，不需要先轉換為案件
    const { data, error } = await patch(`/leads/${item.id}/case-status`, {
      case_status: newStatus
    })

    if (error) {
      showError('更新案件狀態失敗')
      return
    }

    // 更新本地數據
    item.case_status = newStatus

    const statusLabel = CASE_STATUS_OPTIONS.find(opt => opt.value === newStatus)?.label
    success(`案件狀態已更新為：${statusLabel}`)

  } catch (error) {
    showError('更新狀態失敗，請稍後再試')
    console.error('Update case status error:', error)
  }
}


// 工具函數
const extractDomain = (url) => {
  try { return new URL(url).hostname } catch { return url || '' }
}

// Point 51: Generate case number - CASE年份末兩碼+月日+三碼流水號
const generateCaseNumber = (item) => {
  const date = new Date(item.created_at)
  const year = date.getFullYear().toString().slice(-2) // 年份末兩碼
  const month = String(date.getMonth() + 1).padStart(2, '0') // 月份
  const day = String(date.getDate()).padStart(2, '0') // 日期
  const serial = String(item.id).padStart(3, '0') // 三碼流水號使用item.id
  
  return `CASE${year}${month}${day}${serial}`
}

// Point 50: Get website info from domain or URL - Enhanced matching against website management
const getWebsiteInfo = (url) => {
  if (!url) return { name: '-', domain: '', website: null }

  const domain = extractDomain(url)

  // Enhanced matching logic to find website from management system
  const website = websites.value.find(w => {
    // Exact domain match
    if (w.domain === domain) return true

    // Domain without www prefix match
    const cleanDomain = domain.replace(/^www\./, '')
    const cleanWebsiteDomain = w.domain.replace(/^www\./, '')
    if (cleanDomain === cleanWebsiteDomain) return true

    // Check if URL contains the website domain
    if (url.includes(w.domain)) return true

    // Check if domain contains the website domain (for subdomains)
    if (domain.includes(w.domain) || w.domain.includes(domain)) return true

    return false
  })

  if (website) {
    // Return website name from management system
    return {
      name: website.name, // This comes from 網站管理 "網站名稱"
      domain: website.domain,
      website: website
    }
  }

  // Fallback if no match found in website management
  return {
    name: domain || url,
    domain: domain || url,
    website: null
  }
}

// 角色標籤轉換
const getRoleLabel = (role) => {
  const roleLabels = {
    'admin': '管理員',
    'sales': '業務',
    'manager': '主管',
    'executive': '高層',
    'staff': '員工'
  }
  return roleLabels[role] || role
}

const formatDate = (d) => new Date(d).toLocaleDateString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit' })
const formatTime = (d) => new Date(d).toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' })

const formatCustomFieldValue = (val, cf) => {
  if (cf.type === 'multiselect') return Array.isArray(val) ? val.join(', ') : (val || '-')
  if (cf.type === 'boolean') return val ? '是' : '否'
  return val ?? '-'
}

// 頁面載入
onMounted(async () => {
  await Promise.all([loadUsers(), loadLeads(), loadCaseFields(), loadWebsites()])
})

// 組件銷毀時清理
onUnmounted(() => {
  clearTimeout(searchTimer)
})

// 設定頁面標題
useHead({
  title: '待處理案件 - 貸款案件管理系統'
})
</script>

<style scoped>
/* Ensure tooltips appear above everything */
.group:hover .group-hover\\:block {
  z-index: 50;
}
</style>