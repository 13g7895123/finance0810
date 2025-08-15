<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">協商客戶</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">顯示有銀行交涉紀錄的客戶/案件</p>
      </div>
      <div class="flex items-center space-x-3">
        <input v-model="search" placeholder="搜尋姓名/電話/Email/銀行/內容" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" />
        <input v-model="bank" placeholder="銀行名稱" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" />
        <select v-model="status" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700">
          <option value="">所有狀態</option>
          <option v-for="(label, value) in BANK_RECORD_STATUS_LABELS" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
        <input v-model="dateFrom" type="date" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" placeholder="聯絡日期開始" />
        <input v-model="dateTo" type="date" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" placeholder="聯絡日期結束" />
        <input v-model="nextDateFrom" type="date" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" placeholder="下次聯絡開始" />
        <input v-model="nextDateTo" type="date" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" placeholder="下次聯絡結束" />
        <select v-model="pagination.perPage" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700">
          <option v-for="option in PAGINATION_OPTIONS" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">交涉紀錄</h3>
        <div class="flex items-center space-x-3">
          <button class="px-3 py-1 border rounded text-sm dark:bg-gray-800 dark:border-gray-600" @click="openCreate">新增紀錄</button>
          <div class="text-sm text-gray-500 dark:text-gray-400">
            第 <span class="font-medium">{{ startIndex + 1 }}</span> -
            <span class="font-medium">{{ Math.min(endIndex, pagination.total) }}</span>
            筆，共 <span class="font-medium">{{ pagination.total }}</span> 筆
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">客戶</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">銀行</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">聯絡窗口</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">聯絡方式/日期</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">摘要</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">狀態</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading">
              <td colspan="6" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">載入中...</td>
            </tr>
            <tr v-for="r in records" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4">
                <div class="text-gray-900 dark:text-white">{{ r.customer?.name || '-' }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ r.customer?.phone || '-' }}</div>
              </td>
              <td class="px-6 py-4">{{ r.bank_name }}</td>
              <td class="px-6 py-4">
                <div>{{ r.contact_person || '-' }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ r.contact_phone || r.contact_email || '-' }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="capitalize">{{ r.communication_type }}</div>
                <div class="text-sm">{{ formatDateTime(r.communication_date) }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="truncate max-w-[360px]">{{ r.content }}</div>
                <div v-if="r.result" class="text-sm text-gray-500 dark:text-gray-400">結果：{{ r.result }}</div>
              </td>
              <td class="px-6 py-4 capitalize">
                {{ r.status }}
                <button class="ml-3 px-2 py-0.5 border rounded text-xs dark:bg-gray-800 dark:border-gray-600" @click="openEdit(r)">編輯</button>
              </td>
            </tr>
            <tr v-if="!loading && records.length === 0">
              <td colspan="6" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">沒有資料</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div class="flex space-x-2">
          <button @click="prevPage" :disabled="pagination.currentPage === 1" class="px-3 py-1 border rounded text-sm disabled:opacity-50 dark:bg-gray-800 dark:border-gray-600">上一頁</button>
          <button @click="nextPage" :disabled="pagination.currentPage === totalPages" class="px-3 py-1 border rounded text-sm disabled:opacity-50 dark:bg-gray-800 dark:border-gray-600">下一頁</button>
        </div>
        <div class="text-sm text-gray-500 dark:text-gray-400">第 {{ pagination.currentPage }} / {{ totalPages }} 頁</div>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <div v-if="modalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeModal">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-2xl">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ editingId ? '編輯交涉紀錄' : '新增交涉紀錄' }}</h3>
      <form @submit.prevent="saveRecord" class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">選擇客戶</label>
            <div class="flex items-center space-x-2">
              <input v-model="customerSearch" @input="debouncedSearchCustomers" placeholder="輸入姓名/電話/Email 搜尋" class="flex-1 px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
              <span class="text-xs text-gray-500">ID: {{ form.customer_id || '-' }}</span>
            </div>
            <div v-if="customerOptions.length" class="mt-1 max-h-40 overflow-auto border rounded dark:bg-gray-900 dark:border-gray-700">
              <div v-for="opt in customerOptions" :key="opt.id" class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" @click="selectCustomer(opt)">
                <div class="text-sm text-gray-900 dark:text-white">{{ opt.name }} <span class="text-xs text-gray-500">(#{{ opt.id }})</span></div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ opt.phone }} · {{ opt.email || '-' }}</div>
              </div>
            </div>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">關聯案件（可選）</label>
            <div class="flex items-center space-x-2">
              <input v-model="caseSearch" @input="debouncedSearchCases" :disabled="!form.customer_id" placeholder="輸入案件編號或留白" class="flex-1 px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700 disabled:opacity-50" />
              <span class="text-xs text-gray-500">ID: {{ form.case_id || '-' }}</span>
            </div>
            <div v-if="caseOptions.length" class="mt-1 max-h-40 overflow-auto border rounded dark:bg-gray-900 dark:border-gray-700">
              <div v-for="opt in caseOptions" :key="opt.id" class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" @click="selectCase(opt)">
                <div class="text-sm text-gray-900 dark:text-white">{{ opt.case_number }} <span class="text-xs text-gray-500">(#{{ opt.id }})</span></div>
                <div class="text-xs text-gray-500 dark:text-gray-400">金額：{{ opt.loan_amount ?? '-' }} · 狀態：{{ opt.status }}</div>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-sm mb-1">銀行</label>
            <input v-model="form.bank_name" required class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
          </div>
          <div>
            <label class="block text-sm mb-1">聯絡窗口</label>
            <input v-model="form.contact_person" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
          </div>
          <div>
            <label class="block text-sm mb-1">電話</label>
            <input v-model="form.contact_phone" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
          </div>
          <div>
            <label class="block text-sm mb-1">Email</label>
            <input v-model="form.contact_email" type="email" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
          </div>
          <div>
            <label class="block text-sm mb-1">聯絡方式</label>
            <select v-model="form.communication_type" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700">
              <option value="phone">電話</option>
              <option value="email">Email</option>
              <option value="meeting">會議</option>
              <option value="video_call">視訊</option>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">聯絡時間</label>
            <input v-model="form.communication_date" type="datetime-local" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">內容</label>
            <textarea v-model="form.content" required rows="3" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700"></textarea>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">結果（可選）</label>
            <textarea v-model="form.result" rows="2" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700"></textarea>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">下一步（可選）</label>
            <input v-model="form.next_action" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
          </div>
          <div>
            <label class="block text-sm mb-1">下次聯絡（可選）</label>
            <input v-model="form.next_contact_date" type="datetime-local" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
          </div>
          <div>
            <label class="block text-sm mb-1">狀態</label>
            <select v-model="form.status" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700">
              <option value="pending">待處理</option>
              <option value="in_progress">進行中</option>
              <option value="completed">已完成</option>
              <option value="cancelled">取消</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end space-x-3 pt-2">
          <button type="button" class="px-4 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" @click="closeModal">取消</button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded" :disabled="saving">{{ saving ? '儲存中...' : '儲存' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
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

const load = async () => {
  loading.value = true
  const { items, meta, success } = await list({
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
  if (success) {
    records.value = items
    updatePagination(meta)
  }
  loading.value = false
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
