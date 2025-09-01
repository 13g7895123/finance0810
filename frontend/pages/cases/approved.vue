<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">已核准案件</h1>
        <p class="text-gray-600 mt-2">顯示已核准的案件（可搜尋、編輯、刪除）</p>
      </div>
    </div>

    <!-- 統計卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <StatsCard
        title="已核准案件"
        :value="caseStats.approved"
        description="核准通過的案件"
        icon="CheckCircleIcon"
        iconColor="green"
        :trend="8.5"
      />
      
      <StatsCard
        title="本月核准"
        :value="caseStats.thisMonth"
        description="本月新核准"
        icon="TrendingUpIcon"
        iconColor="blue"
        :trend="12.3"
      />
      
      <StatsCard
        title="平均金額"
        :value="caseStats.averageAmount"
        format="currency"
        description="平均核准金額"
        icon="CurrencyDollarIcon"
        iconColor="yellow"
        :trend="5.7"
      />
      
      <StatsCard
        title="核准率"
        :value="caseStats.approvalRate"
        format="percentage"
        description="整體核准率"
        icon="ChartBarIcon"
        iconColor="purple"
        :trend="2.1"
      />
    </div>

    <!-- 案件列表 -->
    <DataTable
      title="已核准案件列表"
      :columns="approvedTableColumns"
      :data="filteredLeads"
      :loading="loading"
      :error="loadError"
      :search-query="searchQuery"
      search-placeholder="搜尋姓名/手機/Email/LINE/網站... (至少2個字符)"
      :current-page="currentPage"
      :items-per-page="itemsPerPage"
      loading-text="載入中..."
      empty-text="沒有已核准案件"
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
        
        <!-- 核准金額範圍篩選 -->
        <select
          v-model="amountFilter"
          class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">所有金額</option>
          <option value="0-100000">10萬以下</option>
          <option value="100000-500000">10-50萬</option>
          <option value="500000-1000000">50-100萬</option>
          <option value="1000000+">100萬以上</option>
        </select>
      </template>
      
      <!-- Action Buttons -->
      <template #actions>
        <!-- 可以在這裡添加匯出等按鈕 -->
      </template>
      
      <!-- Website Cell -->
      <template #cell-website="{ item }">
        <div>
          <div class="text-sm font-medium text-gray-900">
            {{ extractDomain(item.payload?.['頁面_URL'] || item.source) || '-' }}
          </div>
          <div class="text-xs text-gray-500 truncate max-w-[240px]">
            {{ item.payload?.['頁面_URL'] || item.source }}
          </div>
        </div>
      </template>
      
      <!-- Channel Cell -->
      <template #cell-channel="{ item }">
        <span class="text-sm text-gray-900">{{ item.channel || 'wp' }}</span>
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
        <span class="text-sm text-gray-900">{{ item.assignee?.name || '未指派' }}</span>
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
        <span class="text-sm text-gray-900">{{ item.line_id || item.payload?.['LINE_ID'] || '未綁定' }}</span>
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
        <div>
          <div class="text-sm font-medium text-gray-900">
            {{ item.approved_amount ? formatCurrency(item.approved_amount) : '未設定' }}
          </div>
          <div class="text-xs text-gray-500">
            需求：{{ item.payload?.['資金需求'] || '-' }}
          </div>
        </div>
      </template>
      
      <!-- Purpose Cell -->
      <template #cell-purpose="{ item }">
        <span class="text-sm text-gray-900">
          {{ item.payload?.['貸款需求'] || '未填寫' }}
        </span>
      </template>
      
      <!-- Approval Status Cell -->
      <template #cell-approval_status="{ item }">
        <div>
          <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
            已核准
          </span>
          <div class="text-xs text-gray-500 mt-1">
            {{ item.approved_at ? formatDate(item.approved_at) : '未記錄' }}
          </div>
        </div>
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
          <button 
            @click="onEdit(item)"
            class="group relative inline-flex items-center justify-center p-2 text-gray-600 hover:text-white hover:bg-gray-600 rounded-lg transition-all duration-200"
            title="編輯"
          >
            <PencilIcon class="w-4 h-4" />
            <!-- Tooltip -->
            <div class="absolute bottom-full mb-2 hidden group-hover:block px-2 py-1 text-xs text-white bg-gray-900 rounded whitespace-nowrap z-10">
              編輯
            </div>
          </button>
          
          <!-- 撥款處理 -->
          <button 
            @click="processDisbursement(item)"
            class="group relative inline-flex items-center justify-center p-2 text-green-600 hover:text-white hover:bg-green-600 rounded-lg transition-all duration-200"
            title="撥款處理"
          >
            <CurrencyDollarIcon class="w-4 h-4" />
            <!-- Tooltip -->
            <div class="absolute bottom-full mb-2 hidden group-hover:block px-2 py-1 text-xs text-white bg-gray-900 rounded whitespace-nowrap z-10">
              撥款處理
            </div>
          </button>
          
          <!-- 下載文件 -->
          <button 
            @click="downloadDocuments(item)"
            class="group relative inline-flex items-center justify-center p-2 text-purple-600 hover:text-white hover:bg-purple-600 rounded-lg transition-all duration-200"
            title="下載文件"
          >
            <DocumentArrowDownIcon class="w-4 h-4" />
            <!-- Tooltip -->
            <div class="absolute bottom-full mb-2 hidden group-hover:block px-2 py-1 text-xs text-white bg-gray-900 rounded whitespace-nowrap z-10">
              下載文件
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

    <!-- Edit Modal -->
    <div v-if="editOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeEdit">
      <div class="bg-white rounded-lg p-6 w-full max-w-xl max-h-[80vh] overflow-y-auto">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">編輯核准案件</h3>
        <form @submit.prevent="saveEdit" class="space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">網站（頁面URL）</label>
              <input v-model="form.page_url" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">來源管道</label>
              <select v-model="form.channel" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option v-for="opt in CHANNEL_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">案件狀態</label>
              <select v-model="form.status" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">核准金額</label>
              <input v-model.number="form.approved_amount" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">Email</label>
              <input v-model="form.email" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">LINE ID</label>
              <input v-model="form.line_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">地區</label>
              <input v-model="form.region" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">地址</label>
              <input v-model="form.address" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-gray-900 mb-1">備註</label>
              <textarea v-model="form.notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>

            <!-- 自定義欄位（案件） -->
            <template v-if="caseFields.length">
              <div class="md:col-span-2 pt-2">
                <div class="text-sm font-semibold mb-2">自定義欄位（案件）</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div v-for="cf in caseFields" :key="cf.id">
                    <label class="block text-sm font-semibold text-gray-900 mb-1">{{ cf.label }} <span v-if="cf.is_required" class="text-red-500">*</span></label>
                    <!-- 各種input類型實現 -->
                    <input
                      v-if="['text','number','decimal','date'].includes(cf.type)"
                      :type="cf.type === 'decimal' ? 'number' : (cf.type === 'number' ? 'number' : (cf.type === 'date' ? 'date' : 'text'))"
                      :step="cf.type === 'decimal' ? 'any' : undefined"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      :required="cf.is_required"
                      v-model="customFieldValues[cf.key]"
                    />
                    <textarea
                      v-else-if="cf.type === 'textarea'"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      :required="cf.is_required"
                      v-model="customFieldValues[cf.key]"
                    ></textarea>
                    <select
                      v-else-if="cf.type === 'select'"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      :required="cf.is_required"
                      v-model="customFieldValues[cf.key]"
                    >
                      <option value="">請選擇</option>
                      <option v-for="opt in (cf.options||[])" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                    <div v-else-if="cf.type === 'multiselect'">
                      <div class="flex flex-wrap gap-3">
                        <label v-for="opt in (cf.options||[])" :key="opt" class="inline-flex items-center">
                          <input type="checkbox" :value="opt" v-model="customFieldValues[cf.key]" class="mr-2" />
                          <span>{{ opt }}</span>
                        </label>
                      </div>
                    </div>
                    <label v-else-if="cf.type === 'boolean'" class="inline-flex items-center space-x-2">
                      <input type="checkbox" v-model="customFieldValues[cf.key]" />
                      <span>是/否</span>
                    </label>
                  </div>
                </div>
              </div>
            </template>
          </div>
          <div class="flex justify-end space-x-3 pt-2">
            <button type="button" class="px-4 py-2 border border-gray-300 rounded-lg" @click="closeEdit">取消</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700" :disabled="saving">{{ saving ? '儲存中...' : '儲存' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- View Modal -->
    <div v-if="viewOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeView">
      <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900">核准案件詳情</h3>
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
              <p class="text-gray-900">{{ extractDomain(selectedLead.payload?.['頁面_URL'] || selectedLead.source) || '-' }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">來源管道</label>
              <p class="text-gray-900">{{ selectedLead.channel || 'wp' }}</p>
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
              <label class="block text-sm font-medium text-gray-700">核准金額</label>
              <p class="text-gray-900 font-semibold">{{ selectedLead.approved_amount ? formatCurrency(selectedLead.approved_amount) : '未設定' }}</p>
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

    <!-- Disbursement Modal -->
    <div v-if="disbursementOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeDisbursement">
      <div class="bg-white rounded-lg p-6 w-full max-w-lg">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">撥款處理</h3>
        <form @submit.prevent="processDisbursementSubmit" class="space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">撥款金額</label>
              <input v-model.number="disbursementForm.amount" required type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">撥款日期</label>
              <input v-model="disbursementForm.date" required type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-gray-900 mb-1">撥款備註</label>
              <textarea v-model="disbursementForm.notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
          </div>
          <div class="flex justify-end space-x-3 pt-2">
            <button type="button" class="px-4 py-2 border border-gray-300 rounded-lg" @click="closeDisbursement">取消</button>
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">確認撥款</button>
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
  CurrencyDollarIcon,
  DocumentArrowDownIcon,
  TrashIcon,
  CheckCircleIcon,
  TrendingUpIcon,
  ChartBarIcon
} from '@heroicons/vue/24/outline'

// 明確匯入組件
import StatsCard from '~/components/StatsCard.vue'
import DataTable from '~/components/DataTable.vue'
import { formatters } from '~/utils/tableColumns'

definePageMeta({ middleware: 'auth' })

const authStore = useAuthStore()
const { alert, success, error: showError, confirm } = useNotification()
const { list: listLeads, updateOne: updateLead, removeOne: removeLead } = useLeads()
const { getUsers } = useUsers()
const { list: listCustomFields } = useCustomFields()

// 搜尋和篩選
const searchQuery = ref('')
const selectedAssignee = ref('all')
const amountFilter = ref('')

// 載入狀態
const loading = ref(false)
const loadError = ref(null)

// 案件數據
const leads = ref([])
const users = ref([])
const caseStats = ref({
  approved: 0,
  thisMonth: 0,
  averageAmount: 0,
  approvalRate: 0
})

// 模態窗口狀態
const editOpen = ref(false)
const viewOpen = ref(false)
const disbursementOpen = ref(false)
const editingId = ref(null)
const selectedLead = ref(null)
const disbursementLead = ref(null)

// 表單提交狀態
const saving = ref(false)

// Pagination
const currentPage = ref(1)
const itemsPerPage = ref(5)

// 自定義欄位
const caseFields = ref([])
const customFieldValues = reactive({})

// 選項配置
const CHANNEL_OPTIONS = [
  { value: 'wp', label: 'wp' },
  { value: 'lineoa', label: '官方賴' },
  { value: 'email', label: 'email' },
  { value: 'phone', label: '電話' }
]

const STATUS_OPTIONS = [
  { value: 'approved', label: '已核准' },
  { value: 'disbursed', label: '已撥款' },
  { value: 'tracking', label: '追蹤中' },
  { value: 'completed', label: '已完成' }
]

// 表單數據
const form = reactive({
  page_url: '',
  channel: 'wp', // 網站表單
  status: 'approved',
  approved_amount: null,
  email: null,
  line_id: '',
  region: '',
  address: '',
  notes: ''
})

const disbursementForm = reactive({
  amount: null,
  date: '',
  notes: ''
})

// 表格配置
const approvedTableColumns = computed(() => {
  return [
    {
      key: 'website',
      title: '網站',
      sortable: false,
      width: '180px'
    },
    {
      key: 'channel',
      title: '來源管道',
      sortable: true,
      width: '100px'
    },
    {
      key: 'datetime',
      title: '時間',
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
      key: 'contact_info',
      title: '聯絡資訊',
      sortable: false,
      width: '160px'
    },
    {
      key: 'line_info',
      title: 'LINE ID',
      sortable: false,
      width: '120px'
    },
    {
      key: 'location',
      title: '地區/地址',
      sortable: false,
      width: '140px'
    },
    {
      key: 'amount',
      title: '核准金額',
      sortable: true,
      width: '140px'
    },
    {
      key: 'purpose',
      title: '諮詢項目',
      sortable: false,
      width: '120px'
    },
    {
      key: 'approval_status',
      title: '核准狀態',
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
      width: '200px'
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

  // 金額範圍過濾
  if (amountFilter.value) {
    filtered = filtered.filter(lead => {
      const amount = lead.approved_amount || 0
      if (amountFilter.value === '0-100000') return amount > 0 && amount <= 100000
      if (amountFilter.value === '100000-500000') return amount > 100000 && amount <= 500000
      if (amountFilter.value === '500000-1000000') return amount > 500000 && amount <= 1000000
      if (amountFilter.value === '1000000+') return amount > 1000000
      return true
    })
  }

  return filtered
})

// 自定義欄位
const visibleCaseFields = computed(() => caseFields.value.filter(f => f.is_visible))

// 載入數據
const loadLeads = async () => {
  loading.value = true
  loadError.value = null
  
  try {
    const { items, meta, success: ok } = await listLeads({
      page: currentPage.value,
      per_page: itemsPerPage.value,
      status: 'approved'
    })
    
    if (ok) {
      leads.value = items || []
      
      // 計算統計數據
      const total = leads.value.length
      const thisMonth = new Date()
      const monthCount = leads.value.filter(lead => {
        const leadDate = new Date(lead.created_at)
        return leadDate.getFullYear() === thisMonth.getFullYear() && 
               leadDate.getMonth() === thisMonth.getMonth()
      }).length
      
      const totalAmount = leads.value.reduce((sum, lead) => sum + (lead.approved_amount || 0), 0)
      const averageAmount = total > 0 ? Math.round(totalAmount / total) : 0
      
      caseStats.value = {
        approved: total,
        thisMonth: monthCount,
        averageAmount,
        approvalRate: 85 // 這裡可以從 API 獲取實際核准率
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

// DataTable event handlers
const handleSearch = (query) => {
  searchQuery.value = query
}

const handlePageChange = (page) => {
  currentPage.value = page
}

const handlePageSizeChange = (size) => {
  itemsPerPage.value = size
  currentPage.value = 1
}

// 搜尋防抖
let searchTimer
watch([searchQuery, selectedAssignee, amountFilter], () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    // 搜尋過濾已經在 computed 中處理
  }, 300)
})

// 模態窗口控制
const viewLead = (lead) => {
  selectedLead.value = lead
  viewOpen.value = true
}

const closeView = () => {
  viewOpen.value = false
  selectedLead.value = null
}

const onEdit = async (lead) => {
  editingId.value = lead.id
  Object.assign(form, {
    page_url: lead.payload?.['頁面_URL'] || lead.source || '',
    channel: lead.channel || 'wp',
    status: lead.status || 'approved',
    approved_amount: lead.approved_amount || null,
    email: lead.email || null,
    line_id: lead.line_id || lead.payload?.['LINE_ID'] || '',
    region: lead.payload?.['房屋區域'] || lead.payload?.['所在地區'] || '',
    address: lead.payload?.['房屋地址'] || '',
    notes: lead.notes || lead.payload?.['備註'] || ''
  })
  
  // 載入自定義欄位
  await loadCaseFields()
  preloadCustomFieldsFromLead(lead)
  editOpen.value = true
}

const closeEdit = () => {
  editOpen.value = false
  editingId.value = null
}

const saveEdit = async () => {
  if (!editingId.value) return
  
  saving.value = true
  try {
    const payload = {
      channel: form.channel,
      status: form.status,
      approved_amount: form.approved_amount,
      email: form.email,
      line_id: form.line_id,
      notes: form.notes,
      payload: {
        '頁面_URL': form.page_url,
        'LINE_ID': form.line_id,
        '房屋區域': form.region,
        '房屋地址': form.address,
        ...customFieldValues
      }
    }

    const { error } = await updateLead(editingId.value, payload)
    
    if (!error) {
      editOpen.value = false
      await loadLeads()
      success('案件資料更新成功')
    } else {
      showError(error?.message || '更新失敗')
    }
  } catch (err) {
    showError('系統錯誤，請稍後再試')
    console.error('Update lead error:', err)
  } finally {
    saving.value = false
  }
}

const onDelete = async (lead) => {
  const confirmed = await confirm(`確定刪除編號 ${lead.id} 的核准案件嗎？`)
  if (!confirmed) return
  
  try {
    const { error } = await removeLead(lead.id)
    if (!error) {
      await loadLeads()
      success(`編號 ${lead.id} 的案件已刪除`)
    } else {
      showError(error?.message || '刪除失敗')
    }
  } catch (err) {
    showError('系統錯誤，請稍後再試')
    console.error('Delete lead error:', err)
  }
}

// 撥款處理
const processDisbursement = (lead) => {
  disbursementLead.value = lead
  disbursementForm.amount = lead.approved_amount || null
  disbursementForm.date = new Date().toISOString().split('T')[0]
  disbursementForm.notes = ''
  disbursementOpen.value = true
}

const closeDisbursement = () => {
  disbursementOpen.value = false
  disbursementLead.value = null
}

const processDisbursementSubmit = async () => {
  if (!disbursementLead.value) return
  
  try {
    // 這裡應該調用撥款 API
    const payload = {
      status: 'disbursed',
      disbursed_amount: disbursementForm.amount,
      disbursed_at: disbursementForm.date,
      disbursement_notes: disbursementForm.notes
    }
    
    const { error } = await updateLead(disbursementLead.value.id, payload)
    
    if (!error) {
      disbursementOpen.value = false
      await loadLeads()
      success('撥款處理完成')
    } else {
      showError(error?.message || '撥款處理失敗')
    }
  } catch (err) {
    showError('系統錯誤，請稍後再試')
    console.error('Disbursement error:', err)
  }
}

// 下載文件
const downloadDocuments = (lead) => {
  // 這裡實現文件下載功能
  success('功能開發中...')
}

// 自定義欄位處理
const preloadCustomFieldsFromLead = (lead) => {
  const payload = lead?.payload || {}
  caseFields.value.forEach(cf => {
    const key = cf.key
    if (payload && Object.prototype.hasOwnProperty.call(payload, key)) {
      customFieldValues[key] = payload[key]
    } else if (cf.default_value) {
      customFieldValues[key] = cf.default_value
    } else {
      customFieldValues[key] = cf.type === 'multiselect' ? [] : (cf.type === 'boolean' ? false : '')
    }
  })
}

// 工具函數
const extractDomain = (url) => {
  try { return new URL(url).hostname } catch { return url || '' }
}

const formatDate = (d) => new Date(d).toLocaleDateString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit' })
const formatTime = (d) => new Date(d).toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' })

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('zh-TW', {
    style: 'currency',
    currency: 'TWD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount)
}

const formatCustomFieldValue = (val, cf) => {
  if (cf.type === 'multiselect') return Array.isArray(val) ? val.join(', ') : (val || '-')
  if (cf.type === 'boolean') return val ? '是' : '否'
  return val ?? '-'
}

// 頁面載入
onMounted(async () => {
  await Promise.all([loadUsers(), loadLeads(), loadCaseFields()])
})

// 組件銷毀時清理
onUnmounted(() => {
  clearTimeout(searchTimer)
})

// 設定頁面標題
useHead({
  title: '已核准案件 - 貸款案件管理系統'
})
</script>

<style scoped>
/* Ensure tooltips appear above everything */
.group:hover .group-hover\\:block {
  z-index: 50;
}
</style>