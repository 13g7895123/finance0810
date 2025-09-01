<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 ">追蹤中案件</h1>
        <p class="text-gray-600 mt-2">顯示來自 WP 表單的進件（可搜尋、編輯、刪除）</p>
      </div>
    </div>

    <!-- DataTable Component -->
    <DataTable
      title="追蹤中案件列表"
      :columns="tableColumns"
      :data="leads"
      :loading="loading"
      :error="loadError"
      :search-query="search"
      search-placeholder="搜尋姓名/手機/Email/LINE/網站..."
      :current-page="pagination.currentPage"
      :items-per-page="pagination.perPage"
      loading-text="載入中..."
      empty-text="沒有資料"
      @search="handleSearch"
      @refresh="loadLeads"
      @retry="loadLeads"
      @page-change="handlePageChange"
      @page-size-change="handlePageSizeChange"
    >
      <!-- Filter Controls -->
      <template #filters>
        <template v-if="authStore?.hasPermission && authStore.hasPermission('customer_management')">
          <select v-model="selectedAssignee" class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="all">全部承辦</option>
            <option value="null">未指派</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </template>
      </template>
      
      <!-- Custom Table Cells -->
      <template #cell-website="{ item }">
        <div>
          <div class="text-gray-900 ">{{ extractDomain(item.payload?.['頁面_URL'] || item.source) || '-' }}</div>
          <div class="text-xs text-gray-500 truncate max-w-[240px]">{{ item.payload?.['頁面_URL'] || item.source }}</div>
        </div>
      </template>
      
      <template #cell-channel="{ item }">
        {{ item.channel === 'wp' ? '網站表單' : (item.channel === 'lineoa' ? '官方賴' : (item.channel === 'email' ? 'Email' : (item.channel === 'phone' ? '電話' : (item.channel || '-')))) }}
      </template>
      
      <template #cell-time="{ item }">
        <div>
          <div>{{ formatDate(item.created_at) }}</div>
          <div class="text-sm text-gray-500">{{ formatTime(item.created_at) }}</div>
        </div>
      </template>
      
      <template #cell-assignee="{ item }">
        {{ item.assignee?.name || '-' }}
      </template>
      
      <template #cell-region="{ item }">
        {{ item.payload?.['房屋區域'] || item.payload?.['所在地區'] || '-' }}
      </template>
      
      <template #cell-address="{ item }">
        {{ item.payload?.['房屋地址'] || '-' }}
      </template>
      
      <template #cell-amount="{ item }">
        {{ item.payload?.['資金需求'] || '-' }}
      </template>
      
      <template #cell-purpose="{ item }">
        {{ item.payload?.['貸款需求'] || '-' }}
      </template>
      
      <template #cell-contact_time="{ item }">
        {{ item.payload?.['方便聯絡時間'] || '-' }}
      </template>
      
      <template #cell-notes="{ item }">
        {{ item.notes || item.payload?.['備註'] || item.payload?.notes || '-' }}
      </template>
      
      <!-- Actions Cell -->
      <template #cell-actions="{ item }">
        <div class="flex items-center space-x-2 justify-end">
          <button 
            @click="onEdit(item)"
            class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-50 rounded-lg transition-all duration-200 group relative"
            title="編輯"
          >
            <PencilIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              編輯
            </span>
          </button>
          <button 
            @click="onDelete(item)"
            class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-all duration-200 group relative"
            title="刪除"
          >
            <TrashIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              刪除
            </span>
          </button>
        </div>
      </template>
    </DataTable>

    <!-- Edit Modal -->
    <div v-if="editOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeEdit">
      <div class="bg-white rounded-lg p-6 w-full max-w-xl max-h-[80vh] overflow-y-auto">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">編輯進件</h3>
        <form @submit.prevent="saveEdit" class="space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm mb-1">網站（頁面URL）</label>
              <input v-model="form.page_url" class="w-full px-3 py-2 border rounded " />
            </div>
            <div>
              <label class="block text-sm mb-1">來源管道</label>
              <select v-model="form.channel" class="w-full px-3 py-2 border rounded ">
                <option v-for="opt in CHANNEL_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm mb-1">案件狀態</label>
              <select v-model="form.status" class="w-full px-3 py-2 border rounded ">
                <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm mb-1">時間</label>
              <input v-model="form.created_at" type="datetime-local" class="w-full px-3 py-2 border rounded " />
            </div>
            <div>
              <label class="block text-sm mb-1">Email</label>
              <input v-model="form.email" type="email" class="w-full px-3 py-2 border rounded " />
            </div>
            <div>
              <label class="block text-sm mb-1">LINE ID</label>
              <input v-model="form.line_id" class="w-full px-3 py-2 border rounded " />
            </div>
            <div>
              <label class="block text-sm mb-1">地區</label>
              <input v-model="form.region" class="w-full px-3 py-2 border rounded " />
            </div>
            <div>
              <label class="block text-sm mb-1">地址</label>
              <input v-model="form.address" class="w-full px-3 py-2 border rounded " />
            </div>
            <div>
              <label class="block text-sm mb-1">需求金額</label>
              <input v-model.number="form.required_amount" type="number" min="0" class="w-full px-3 py-2 border rounded " />
            </div>
            <div>
              <label class="block text-sm mb-1">諮詢項目</label>
              <input v-model="form.loan_purpose" class="w-full px-3 py-2 border rounded " />
            </div>
            <div>
              <label class="block text-sm mb-1">可聯繫時間</label>
              <input v-model="form.contact_time" class="w-full px-3 py-2 border rounded " />
            </div>
            <div>
              <label class="block text-sm mb-1">IP 位址</label>
              <input v-model="form.ip_address" class="w-full px-3 py-2 border rounded " />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm mb-1">備註</label>
              <textarea v-model="form.notes" rows="2" class="w-full px-3 py-2 border rounded "></textarea>
            </div>

            <!-- 自定義欄位（案件）顯示於表格/彈窗 -->
            <template v-if="caseFields.length">
              <div class="md:col-span-2 pt-2">
                <div class="text-sm font-semibold mb-2">自定義欄位（案件）</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div v-for="cf in caseFields" :key="cf.id">
                    <label class="block text-sm mb-1">{{ cf.label }} <span v-if="cf.is_required" class="text-red-500">*</span></label>
                    <!-- 文字/數字/小數/日期 -->
                    <input
                      v-if="['text','number','decimal','date'].includes(cf.type)"
                      :type="cf.type === 'decimal' ? 'number' : (cf.type === 'number' ? 'number' : (cf.type === 'date' ? 'date' : 'text'))"
                      :step="cf.type === 'decimal' ? 'any' : undefined"
                      class="w-full px-3 py-2 border rounded "
                      :required="cf.is_required"
                      v-model="customFieldValues[cf.key]"
                    />
                    <!-- 文字區塊 -->
                    <textarea
                      v-else-if="cf.type === 'textarea'"
                      class="w-full px-3 py-2 border rounded "
                      :required="cf.is_required"
                      v-model="customFieldValues[cf.key]"
                    ></textarea>
                    <!-- 單選 -->
                    <select
                      v-else-if="cf.type === 'select'"
                      class="w-full px-3 py-2 border rounded "
                      :required="cf.is_required"
                      v-model="customFieldValues[cf.key]"
                    >
                      <option value="">請選擇</option>
                      <option v-for="opt in (cf.options||[])" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                    <!-- 多選（強化 UI：checkbox 群組 + 全選/清空 + 已選標籤） -->
                    <div v-else-if="cf.type === 'multiselect'">
                      <div class="flex flex-wrap gap-3">
                        <label v-for="opt in (cf.options||[])" :key="opt" class="inline-flex items-center">
                          <input type="checkbox" :value="opt" v-model="customFieldValues[cf.key]" class="mr-2" />
                          <span>{{ opt }}</span>
                        </label>
                      </div>
                      <div class="mt-2 text-xs text-gray-500 space-x-3">
                        <button type="button" class="underline" @click="selectAllOptions(cf)">全選</button>
                        <button type="button" class="underline" @click="clearOptions(cf)">清空</button>
                      </div>
                      <div class="mt-2 flex flex-wrap gap-2" v-if="(customFieldValues[cf.key]||[]).length">
                        <span v-for="v in customFieldValues[cf.key]" :key="v" class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded">{{ v }}</span>
                      </div>
                    </div>
                    <!-- 是/否 -->
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
            <button type="button" class="px-4 py-2 border rounded " @click="closeEdit">取消</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded" :disabled="saving">{{ saving ? '儲存中...' : '儲存' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Convert Modal -->
  <div v-if="convertOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeConvert">
    <div class="bg-white rounded-lg p-6 w-full max-w-lg">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">送件（建立案件）</h3>
      <form @submit.prevent="doConvert" class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm mb-1">貸款金額</label>
            <input v-model.number="convertForm.loan_amount" required type="number" min="0" class="w-full px-3 py-2 border rounded " />
          </div>
          <div>
            <label class="block text-sm mb-1">貸款類型</label>
            <input v-model="convertForm.loan_type" class="w-full px-3 py-2 border rounded " />
          </div>
          <div>
            <label class="block text-sm mb-1">期數（月）</label>
            <input v-model.number="convertForm.loan_term" type="number" min="0" class="w-full px-3 py-2 border rounded " />
          </div>
          <div>
            <label class="block text-sm mb-1">利率</label>
            <input v-model.number="convertForm.interest_rate" type="number" min="0" step="0.01" class="w-full px-3 py-2 border rounded " />
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">備註</label>
            <textarea v-model="convertForm.notes" rows="2" class="w-full px-3 py-2 border rounded "></textarea>
          </div>
        </div>
        <div class="flex justify-end space-x-3 pt-2">
          <button type="button" class="px-4 py-2 border rounded " @click="closeConvert">取消</button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">送件</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import {
  PencilIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'
import DataTable from '~/components/DataTable.vue'

definePageMeta({ middleware: 'auth' })
const authStore = useAuthStore()

const { list: listLeads, updateOne: updateLead, removeOne: removeLead, convertToCase } = useLeads()
const { pagination, totalPages, startIndex, endIndex, nextPage, prevPage, updatePagination } = usePagination(10)
const { validateCaseForm } = useFormValidation()
const { success, error: showError, confirm } = useNotification()
const { PAGINATION_OPTIONS, SEARCH_CONFIG } = useConstants()
const { getUsers } = useUsers()
const { list: listCustomFields } = useCustomFields()

const users = ref([])
const CHANNEL_OPTIONS = [
  { value: 'wp', label: '網站表單' },
  { value: 'lineoa', label: '官方賴' },
  { value: 'email', label: 'email' },
  { value: 'phone', label: '電話' }
]
const STATUS_OPTIONS = [
  { value: 'pending', label: '待處理' },
  { value: 'intake', label: '已進件' },
  { value: 'approved', label: '已核准' },
  // { value: 'submitted', label: '已送件' },
  { value: 'disbursed', label: '已撥款' },
  { value: 'tracking', label: '追蹤中' },
  { value: 'blacklist', label: '黑名單' }
]

// state
const leads = ref([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const selectedAssignee = ref('all')
const loadError = ref(null)

// edit modal
const editOpen = ref(false)
const editingId = ref(null)
const form = reactive({
  page_url: '',
  channel: 'wp',
  status: 'tracking',
  created_at: '',
  assigned_to: null,
  email: null,
  line_id: '',
  region: '',
  address: '',
  required_amount: null,
  loan_purpose: '',
  contact_time: '',
  ip_address: null,
  notes: ''
})

// convert modal
const convertOpen = ref(false)
const convertLead = ref(null)
const convertForm = reactive({ loan_amount: null, loan_type: '', loan_term: null, interest_rate: null, notes: '' })

// 自定義欄位（案件）
const caseFields = ref([])
const customFieldValues = reactive({})
const FIELD_COMPONENTS = {
  text: {
    render() {},
  },
}

const visibleCaseFields = computed(() => caseFields.value.filter(f => f.is_visible))

// Table columns configuration
const tableColumns = computed(() => {
  const baseColumns = [
    {
      key: 'website',
      title: '網站',
      sortable: false,
      width: '200px'
    },
    {
      key: 'channel',
      title: '來源管道',
      sortable: false,
      width: '100px'
    },
    {
      key: 'time',
      title: '時間',
      sortable: true,
      width: '120px'
    },
    {
      key: 'assignee',
      title: '承辦業務',
      sortable: false,
      width: '100px'
    },
    {
      key: 'email',
      title: 'Email',
      sortable: false,
      width: '150px',
      formatter: (value) => value || '-'
    },
    {
      key: 'line_id',
      title: 'LINE ID',
      sortable: false,
      width: '100px',
      formatter: (value, item) => item.line_id || item.payload?.['LINE_ID'] || '-'
    },
    {
      key: 'region',
      title: '地區',
      sortable: false,
      width: '100px'
    },
    {
      key: 'address',
      title: '地址',
      sortable: false,
      width: '150px'
    },
    {
      key: 'amount',
      title: '需求金額',
      sortable: false,
      width: '100px'
    },
    {
      key: 'purpose',
      title: '諮詢項目',
      sortable: false,
      width: '120px'
    },
    {
      key: 'contact_time',
      title: '可聯繫時間',
      sortable: false,
      width: '120px'
    },
    {
      key: 'ip_address',
      title: 'IP 位址',
      sortable: false,
      width: '120px',
      formatter: (value) => value || '-'
    },
    {
      key: 'notes',
      title: '備註',
      sortable: false,
      width: '150px'
    }
  ]
  
  // Add visible custom fields
  visibleCaseFields.value.forEach(cf => {
    baseColumns.push({
      key: `custom_${cf.key}`,
      title: cf.label,
      sortable: false,
      width: '120px',
      formatter: (value, item) => formatCustomFieldValue(item.payload?.[cf.key], cf)
    })
  })
  
  // Add actions column
  baseColumns.push({
    key: 'actions',
    title: '操作',
    sortable: false,
    width: '120px'
  })
  
  return baseColumns
})

// lifecycle
const loadLeads = async () => {
  loading.value = true
  loadError.value = null
  
  try {
    // 搜尋優化：最少字符限制
    const searchValue = search.value.trim()
    if (searchValue && searchValue.length < SEARCH_CONFIG.MIN_CHARACTERS) {
      leads.value = []
      pagination.total = 0
      loading.value = false
      return
    }
    
    const { items, meta, success: ok, error } = await listLeads({
      page: pagination.currentPage,
      per_page: pagination.perPage,
      search: searchValue,
      // channel: 'wp_form',
      assigned_to: selectedAssignee.value,
      status: 'tracking'
    })
    
    if (ok) {
      leads.value = items
      updatePagination(meta)
    } else {
      loadError.value = error?.message || '載入資料失敗'
    }
  } catch (err) {
    loadError.value = '載入資料時發生錯誤'
    console.error('Load leads error:', err)
  } finally {
    loading.value = false
  }
}

// DataTable event handlers
const handleSearch = (query) => {
  search.value = query
}

const handlePageChange = (page) => {
  pagination.currentPage = page
}

const handlePageSizeChange = (size) => {
  pagination.perPage = size
  pagination.currentPage = 1
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

// 根據欄位型別返回對應的輸入元件
const getInputComponent = (cf) => ({
  props: ['modelValue','cf'],
  emits: ['update:modelValue'],
  template: `
    <div>
      <input v-if="['text','number','decimal','date'].includes(cf.type)"
             :type="cf.type === 'decimal' ? 'number' : (cf.type === 'text' ? 'text' : (cf.type === 'date' ? 'date' : 'number'))"
             step="any"
             class="w-full px-3 py-2 border rounded "
             :required="cf.is_required"
             :value="modelValue"
             @input="$emit('update:modelValue', $event.target.value)"
      />
      <textarea v-else-if="cf.type==='textarea'"
                class="w-full px-3 py-2 border rounded "
                :required="cf.is_required"
                :value="modelValue"
                @input="$emit('update:modelValue', $event.target.value)"></textarea>
      <select v-else-if="cf.type==='select'"
              class="w-full px-3 py-2 border rounded "
              :required="cf.is_required"
              :value="modelValue"
              @change="$emit('update:modelValue', $event.target.value)">
        <option value="">請選擇</option>
        <option v-for="opt in (cf.options||[])" :key="opt" :value="opt">{{ opt }}</option>
      </select>
      <select v-else-if="cf.type==='multiselect'" multiple
              class="w-full px-3 py-2 border rounded "
              :required="cf.is_required"
              @change="$emit('update:modelValue', Array.from($event.target.selectedOptions).map(o=>o.value))">
        <option v-for="opt in (cf.options||[])" :key="opt" :value="opt" :selected="(modelValue||[]).includes(opt)">{{ opt }}</option>
      </select>
      <label v-else-if="cf.type==='boolean'" class="inline-flex items-center">
        <input type="checkbox" :checked="!!modelValue" @change="$emit('update:modelValue', $event.target.checked)" class="mr-2" /> 是/否
      </label>
    </div>
  `
})

const selectAllOptions = (cf) => { customFieldValues[cf.key] = Array.isArray(cf.options) ? [...cf.options] : [] }
const clearOptions = (cf) => { customFieldValues[cf.key] = [] }

const preloadCustomFieldsFromLead = (lead) => {
  // 預設取 lead.payload[自定義欄位key] 做為初值
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

onMounted(async () => {
  const promises = [loadLeads(), loadCaseFields()];
  if (authStore?.hasPermission && authStore.hasPermission('customer_management')) {
    promises.push(loadUsers());
  }
  await Promise.all(promises);
})

// 搜尋防抖
let searchTimer
const debouncedLoadLeads = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadLeads, SEARCH_CONFIG.DEBOUNCE_DELAY)
}

watch([() => pagination.currentPage, () => pagination.perPage], loadLeads)
watch([search, selectedAssignee], debouncedLoadLeads)

// 組件銷毀時清理
onUnmounted(() => {
  clearTimeout(searchTimer)
})

// helpers
const extractDomain = (url) => {
  try { return new URL(url).hostname } catch { return url || '' }
}
const formatDate = (d) => new Date(d).toLocaleDateString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit' })
const formatTime = (d) => new Date(d).toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' })

const formatCustomFieldValue = (val, cf) => {
  if (cf.type === 'multiselect') return Array.isArray(val) ? val.join(', ') : (val || '-')
  if (cf.type === 'boolean') return val ? '是' : '否'
  return val ?? '-'
}

// 移除重複的分頁邏輯（已由 usePagination 提供）

// edit & delete
const onEdit = async (lead) => {
  console.log('Editing lead:', lead);
  editingId.value = lead.id
  Object.assign(form, {
    page_url: lead.payload?.['頁面_URL'] || lead.source || '',
    channel: lead.channel || 'wp',
    status: lead.status || 'tracking',
    created_at: lead.created_at ? new Date(lead.created_at).toISOString().slice(0,16) : new Date().toISOString().slice(0,16),
    assigned_to: lead.assigned_to || null,
    email: lead.email || null,
    line_id: lead.line_id || lead.payload?.['LINE_ID'] || '',
    region: lead.payload?.['房屋區域'] || lead.payload?.['所在地區'] || '',
    address: lead.payload?.['房屋地址'] || '',
    required_amount: lead.payload?.['資金需求'] || '',
    loan_purpose: lead.payload?.['貸款需求'] || '',
    contact_time: lead.payload?.['方便聯絡時間'] || '',
    ip_address: lead.ip_address || null,
    notes: lead.notes || lead.payload?.['備註'] || ''
  })
  // 載入自定義欄位（案件）
  await loadCaseFields()
  // 從 lead.payload 帶入自定義欄位預設值
  preloadCustomFieldsFromLead(lead)
  editOpen.value = true
}
const closeEdit = () => { editOpen.value = false; editingId.value = null }
const saveEdit = async () => {
  if (!editingId.value) return
  
  // 自定義欄位必填檢查
  const missing = []
  caseFields.value.filter(f => f.is_required).forEach(cf => {
    const val = customFieldValues[cf.key]
    const isEmpty = (
      (cf.type === 'multiselect' && (!Array.isArray(val) || val.length === 0)) ||
      (cf.type !== 'multiselect' && (val === undefined || val === null || String(val).trim() === ''))
    )
    if (isEmpty) missing.push(cf.label)
  })
  if (missing.length) {
    showError(`請填寫必填欄位：\n- ${missing.join('\n- ')}`)
    return
  }
  
  saving.value = true
  try {
    // 組合 payload：top-level + payload 物件
    const payload = {
      channel: form.channel,
      status: form.status,
      email: form.email,
      line_id: form.line_id,
      ip_address: form.ip_address,
      assigned_to: form.assigned_to, // 後端已有欄位，直接存欄位
      notes: form.notes, // 若後端未有欄位會自動併入 payload（現已改為欄位，仍保留）
      // 其他資料放在 payload 物件
      payload: {
        '頁面_URL': form.page_url,
        'LINE_ID': form.line_id,
        '房屋區域': form.region,
        '房屋地址': form.address,
        '資金需求': form.required_amount,
        '貸款需求': form.loan_purpose,
        '方便聯絡時間': form.contact_time,
        // 併入自定義欄位值
        ...customFieldValues
      }
    }

    const { error } = await updateLead(editingId.value, payload)
    
    if (!error) {
      editOpen.value = false
      await loadLeads()
      success('進件資料更新成功')
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

// convert methods
const openConvert = (lead) => {
  if (!lead.customer_id) {
    showError('此進件尚未綁定客戶，請先建立/綁定客戶後再送件')
    return
  }
  convertLead.value = lead
  Object.assign(convertForm, { loan_amount: null, loan_type: '', loan_term: null, interest_rate: null, notes: '' })
  convertOpen.value = true
}
const closeConvert = () => { convertOpen.value = false; convertLead.value = null }
const doConvert = async () => {
  if (!convertLead.value) return
  
  // 使用統一的表單驗證
  const { isValid, errors } = validateCaseForm(convertForm)
  // 最小驗證
  if (!form.channel) return showError('來源管道為必填')
  if (form.required_amount !== null && Number(form.required_amount) < 0) return showError('需求金額不能為負數')
  if (form.page_url && !/^https?:\/\//i.test(form.page_url)) return showError('請輸入有效的網站URL（需以 http:// 或 https:// 開頭）')
  if (!isValid) {
    const errorMessages = Object.values(errors).join('\n')
    showError(`表單驗證失敗：\n${errorMessages}`)
    return
  }
  
  try {
    const { error, data } = await convertToCase(convertLead.value, { ...convertForm })
    
    if (!error) {
      // 從本地 leads 陣列移除該筆
      const idx = leads.value.findIndex(l => l.id === convertLead.value.id)
      if (idx >= 0) leads.value.splice(idx, 1)
      
      // 重置表單
      Object.assign(convertForm, { loan_amount: null, loan_type: '', loan_term: null, interest_rate: null, notes: '' })
      convertOpen.value = false
      
      const caseNumber = data?.case?.case_number || ''
      success(`送件成功！案件編號：${caseNumber}`)
    } else {
      if (error.errors) {
        const errorMessages = Object.entries(error.errors)
          .map(([field, messages]) => `${messages.join(', ')}`)
          .join('\n')
        showError(`表單驗證失敗：\n${errorMessages}`)
      } else {
        showError(error?.message || '送件失敗')
      }
    }
  } catch (err) {
    showError('系統錯誤，請稍後再試')
    console.error('Convert to case error:', err)
  }
}
</script>

<style scoped>
@media (max-width: 768px) {
  table, thead, tbody, th, td, tr { display: block; }
  thead tr { position: absolute; top: -9999px; left: -9999px; }
  tr { border: 1px solid #ccc; margin-bottom: 10px; padding: 10px; }
  td { border: none; position: relative; padding-left: 50% !important; }
  td:before { content: attr(data-label); position: absolute; left: 6px; width: 45%; padding-right: 10px; white-space: nowrap; font-weight: bold; }
}
</style>