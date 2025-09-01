<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 ">協商客戶</h1>
        <p class="text-gray-600 mt-2">顯示有銀行交涉紀錄的客戶/案件</p>
      </div>
    </div>

    <!-- DataTable Component -->
    <DataTable
      title="交涉紀錄"
      :columns="tableColumns"
      :data="records"
      :loading="loading"
      :error="loadError"
      :search-query="search"
      search-placeholder="搜尋姓名/電話/Email/銀行/內容..."
      :current-page="pagination.currentPage"
      :items-per-page="pagination.perPage"
      loading-text="載入中..."
      empty-text="沒有資料"
      @search="handleSearch"
      @refresh="load"
      @retry="load"
      @page-change="handlePageChange"
      @page-size-change="handlePageSizeChange"
    >
      <!-- Filter Controls -->
      <template #filters>
        <input v-model="bank" placeholder="銀行名稱" class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500" />
        <select v-model="status" class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">所有狀態</option>
          <option v-for="(label, value) in BANK_RECORD_STATUS_LABELS" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
        <input v-model="dateFrom" type="date" class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="聯絡日期開始" />
        <input v-model="dateTo" type="date" class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="聯絡日期結束" />
        <input v-model="nextDateFrom" type="date" class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="下次聯絡開始" />
        <input v-model="nextDateTo" type="date" class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="下次聯絡結束" />
      </template>

      <!-- Custom Actions in Header -->
      <template #actions>
        <button 
          @click="openCreate" 
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200"
        >
          新增紀錄
        </button>
      </template>
      
      <!-- Custom Table Cells -->
      <template #cell-customer="{ item }">
        <div>
          <div class="text-gray-900 ">{{ item.customer?.name || '-' }}</div>
          <div class="text-sm text-gray-500 ">{{ item.customer?.phone || '-' }}</div>
        </div>
      </template>
      
      <template #cell-bank="{ item }">
        {{ item.bank_name }}
      </template>
      
      <template #cell-contact="{ item }">
        <div>{{ item.contact_person || '-' }}</div>
        <div class="text-sm text-gray-500 ">{{ item.contact_phone || item.contact_email || '-' }}</div>
      </template>
      
      <template #cell-communication="{ item }">
        <div class="capitalize">{{ item.communication_type }}</div>
        <div class="text-sm">{{ formatDateTime(item.communication_date) }}</div>
      </template>
      
      <template #cell-summary="{ item }">
        <div class="truncate max-w-[360px]">{{ item.content }}</div>
        <div v-if="item.result" class="text-sm text-gray-500 ">結果：{{ item.result }}</div>
      </template>
      
      <!-- Actions Cell -->
      <template #cell-actions="{ item }">
        <div class="flex items-center justify-end">
          <button 
            @click="openEdit(item)"
            class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-50 rounded-lg transition-all duration-200 group relative"
            title="編輯"
          >
            <PencilIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              編輯
            </span>
          </button>
        </div>
      </template>
    </DataTable>
  </div>

  <!-- Modal -->
  <div v-if="modalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeModal">
    <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ editingId ? '編輯交涉紀錄' : '新增交涉紀錄' }}</h3>
      <form @submit.prevent="saveRecord" class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">選擇客戶</label>
            <div class="flex items-center space-x-2">
              <input v-model="customerSearch" @input="debouncedSearchCustomers" placeholder="輸入姓名/電話/Email 搜尋" class="flex-1 px-3 py-2 border rounded " />
              <span class="text-xs text-gray-500">ID: {{ form.customer_id || '-' }}</span>
            </div>
            <div v-if="customerOptions.length" class="mt-1 max-h-40 overflow-auto border rounded ">
              <div v-for="opt in customerOptions" :key="opt.id" class="px-3 py-2 hover:bg-gray-100 cursor-pointer" @click="selectCustomer(opt)">
                <div class="text-sm text-gray-900 ">{{ opt.name }} <span class="text-xs text-gray-500">(#{{ opt.id }})</span></div>
                <div class="text-xs text-gray-500 ">{{ opt.phone }} · {{ opt.email || '-' }}</div>
              </div>
            </div>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">關聯案件（可選）</label>
            <div class="flex items-center space-x-2">
              <input v-model="caseSearch" @input="debouncedSearchCases" :disabled="!form.customer_id" placeholder="輸入案件編號或留白" class="flex-1 px-3 py-2 border rounded disabled:opacity-50" />
              <span class="text-xs text-gray-500">ID: {{ form.case_id || '-' }}</span>
            </div>
            <div v-if="caseOptions.length" class="mt-1 max-h-40 overflow-auto border rounded ">
              <div v-for="opt in caseOptions" :key="opt.id" class="px-3 py-2 hover:bg-gray-100 cursor-pointer" @click="selectCase(opt)">
                <div class="text-sm text-gray-900 ">{{ opt.case_number }} <span class="text-xs text-gray-500">(#{{ opt.id }})</span></div>
                <div class="text-xs text-gray-500 ">金額：{{ opt.loan_amount ?? '-' }} · 狀態：{{ opt.status }}</div>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-sm mb-1">銀行</label>
            <input v-model="form.bank_name" required class="w-full px-3 py-2 border rounded " />
          </div>
          <div>
            <label class="block text-sm mb-1">聯絡窗口</label>
            <input v-model="form.contact_person" class="w-full px-3 py-2 border rounded " />
          </div>
          <div>
            <label class="block text-sm mb-1">電話</label>
            <input v-model="form.contact_phone" class="w-full px-3 py-2 border rounded " />
          </div>
          <div>
            <label class="block text-sm mb-1">Email</label>
            <input v-model="form.contact_email" type="email" class="w-full px-3 py-2 border rounded " />
          </div>
          <div>
            <label class="block text-sm mb-1">聯絡方式</label>
            <select v-model="form.communication_type" class="w-full px-3 py-2 border rounded ">
              <option value="phone">電話</option>
              <option value="email">Email</option>
              <option value="meeting">會議</option>
              <option value="video_call">視訊</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">聯絡時間</label>
            <input v-model="form.communication_date" type="datetime-local" class="w-full px-3 py-2 border rounded " />
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">內容</label>
            <textarea v-model="form.content" required rows="3" class="w-full px-3 py-2 border rounded "></textarea>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">結果（可選）</label>
            <textarea v-model="form.result" rows="2" class="w-full px-3 py-2 border rounded "></textarea>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">下一步（可選）</label>
            <input v-model="form.next_action" class="w-full px-3 py-2 border rounded " />
          </div>
          <div>
            <label class="block text-sm mb-1">下次聯絡（可選）</label>
            <input v-model="form.next_contact_date" type="datetime-local" class="w-full px-3 py-2 border rounded " />
          </div>
          <div>
            <label class="block text-sm mb-1">狀態</label>
            <select v-model="form.status" class="w-full px-3 py-2 border rounded ">
              <option value="pending">待處理</option>
              <option value="in_progress">進行中</option>
              <option value="completed">已完成</option>
              <option value="cancelled">取消</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end space-x-3 pt-2">
          <button type="button" class="px-4 py-2 border rounded " @click="closeModal">取消</button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded" :disabled="saving">{{ saving ? '儲存中...' : '儲存' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { PencilIcon } from '@heroicons/vue/24/outline'
import DataTable from '~/components/DataTable.vue'

definePageMeta({ middleware: 'auth' })

const { list, createOne, updateOne } = useBankRecords()
const { getCustomers } = useCustomers()
const { list: listCases } = useCases()
const { pagination, totalPages, startIndex, endIndex, nextPage, prevPage, updatePagination } = usePagination(10)
const { validateBankRecordForm } = useFormValidation()
const { success, error: showError } = useNotification()
const { BANK_RECORD_STATUSES, BANK_RECORD_STATUS_LABELS, COMMUNICATION_TYPES, PAGINATION_OPTIONS, SEARCH_CONFIG } = useConstants()

const loading = ref(false)
const records = ref([])
const search = ref('')
const bank = ref('')
const status = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const nextDateFrom = ref('')
const nextDateTo = ref('')
const loadError = ref(null)

// Table columns configuration
const tableColumns = computed(() => [
  {
    key: 'customer',
    title: '客戶',
    sortable: false,
    width: '200px'
  },
  {
    key: 'bank',
    title: '銀行',
    sortable: false,
    width: '120px'
  },
  {
    key: 'contact',
    title: '聯絡窗口',
    sortable: false,
    width: '150px'
  },
  {
    key: 'communication',
    title: '聯絡方式/日期',
    sortable: false,
    width: '150px'
  },
  {
    key: 'summary',
    title: '摘要',
    sortable: false,
    width: '300px'
  },
  {
    key: 'status',
    title: '狀態',
    sortable: false,
    width: '120px',
    formatter: (value) => value
  },
  {
    key: 'actions',
    title: '操作',
    sortable: false,
    width: '80px'
  }
])

const load = async () => {
  loading.value = true
  loadError.value = null
  
  try {
    const { items, meta, success: ok, error } = await list({
      search: search.value,
      bank_name: bank.value || undefined,
      status: status.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      next_date_from: nextDateFrom.value || undefined,
      next_date_to: nextDateTo.value || undefined,
      page: pagination.currentPage,
      per_page: pagination.perPage
    })
    
    if (ok) {
      records.value = items
      updatePagination(meta)
    } else {
      loadError.value = error?.message || '載入資料失敗'
    }
  } catch (err) {
    loadError.value = '載入資料時發生錯誤'
    console.error('Load records error:', err)
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

onMounted(load)
watch([search, bank, status, dateFrom, dateTo, nextDateFrom, nextDateTo, () => pagination.currentPage, () => pagination.perPage], load)

const formatDateTime = (d) => new Date(d).toLocaleString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })


// Modal state & methods
const modalOpen = ref(false)
const saving = ref(false)
const editingId = ref(null)
const form = reactive({
  customer_id: null,
  case_id: null,
  bank_name: '',
  contact_person: '',
  contact_phone: '',
  contact_email: '',
  communication_type: 'phone',
  communication_date: '',
  content: '',
  result: '',
  next_action: '',
  next_contact_date: '',
  status: 'pending'
})

const customerSearch = ref('')
const customerOptions = ref([])
const caseSearch = ref('')
const caseOptions = ref([])

const openCreate = () => {
  editingId.value = null
  Object.assign(form, {
    customer_id: null,
    case_id: null,
    bank_name: '',
    contact_person: '',
    contact_phone: '',
    contact_email: '',
    communication_type: 'phone',
    communication_date: '',
    content: '',
    result: '',
    next_action: '',
    next_contact_date: '',
    status: 'pending'
  })
  customerSearch.value = ''
  caseSearch.value = ''
  customerOptions.value = []
  caseOptions.value = []
  modalOpen.value = true
}

const openEdit = (r) => {
  editingId.value = r.id
  Object.assign(form, {
    customer_id: r.customer_id,
    case_id: r.case_id,
    bank_name: r.bank_name,
    contact_person: r.contact_person,
    contact_phone: r.contact_phone,
    contact_email: r.contact_email,
    communication_type: r.communication_type,
    communication_date: r.communication_date ? new Date(r.communication_date).toISOString().slice(0,16) : '',
    content: r.content,
    result: r.result || '',
    next_action: r.next_action || '',
    next_contact_date: r.next_contact_date ? new Date(r.next_contact_date).toISOString().slice(0,16) : '',
    status: r.status
  })
  customerSearch.value = ''
  caseSearch.value = ''
  customerOptions.value = []
  caseOptions.value = []
  modalOpen.value = true
}

const closeModal = () => { modalOpen.value = false }

const saveRecord = async () => {
  saving.value = true
  const payload = { ...form }
  if (!payload.communication_date) delete payload.communication_date
  if (!payload.next_contact_date) delete payload.next_contact_date
  const resp = editingId.value ? await updateOne(editingId.value, payload) : await createOne(payload)
  saving.value = false
  if (!resp.error) {
    modalOpen.value = false
    success('銀行記錄儲存成功')
    await load()
  } else {
    showError(resp.error?.message || '儲存失敗')
  }
}

let customerTimer, caseTimer
const debouncedSearchCustomers = () => { 
  clearTimeout(customerTimer)
  customerTimer = setTimeout(searchCustomers, SEARCH_CONFIG.DEBOUNCE_DELAY) 
}
const debouncedSearchCases = () => { 
  clearTimeout(caseTimer)
  caseTimer = setTimeout(searchCases, SEARCH_CONFIG.DEBOUNCE_DELAY) 
}

// 組件銷毀時清理
onUnmounted(() => {
  clearTimeout(customerTimer)
  clearTimeout(caseTimer)
})

const searchCustomers = async () => {
  if (!customerSearch.value.trim() || customerSearch.value.length < SEARCH_CONFIG.MIN_CHARACTERS) { 
    customerOptions.value = []; 
    return 
  }
  const { data, error } = await getCustomers({ search: customerSearch.value, per_page: 10 })
  if (!error) customerOptions.value = data.data || []
}
const selectCustomer = (opt) => {
  form.customer_id = opt.id
  customerOptions.value = []
  caseOptions.value = []
}

const searchCases = async () => {
  if (!form.customer_id) return
  const { items, error } = await listCases({ customer_id: form.customer_id, per_page: 10, search: caseSearch.value || undefined })
  if (!error) caseOptions.value = items
}
const selectCase = (opt) => {
  form.case_id = opt.id
  caseOptions.value = []
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