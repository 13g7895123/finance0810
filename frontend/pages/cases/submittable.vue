<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 ">可送件案件</h1>
        <p class="text-gray-600 mt-2">顯示可送件的客戶（無案件，且狀態為有意願/已聯絡）</p>
      </div>
      <div class="flex items-center space-x-3">
        <input 
          v-model="search" 
          placeholder="搜尋客戶/電話/Email (至少2個字符)" 
          class="px-3 py-2 border rounded "
        />
        <select v-model="pagination.perPage" class="px-3 py-2 border rounded ">
          <option v-for="option in PAGINATION_OPTIONS" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <button @click="load" class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700" :disabled="loading">
          {{ loading ? '載入中...' : '重新載入' }}
        </button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900 ">客戶清單</h3>
        <div class="text-sm text-gray-500 ">
          第 <span class="font-medium">{{ startIndex + 1 }}</span> -
          <span class="font-medium">{{ Math.min(endIndex, pagination.total) }}</span>
          筆，共 <span class="font-medium">{{ pagination.total }}</span> 筆
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 ">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">網站</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">來源管道</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">時間</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">承辦業務</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">LINE ID</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">地區</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">地址</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">需求金額</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">諮詢項目</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">可聯繫時間</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP 位址</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">備註</th>
              <!-- 自定義欄位（可見）動態欄位 -->
              <th v-for="cf in visibleCaseFields" :key="cf.id" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ cf.label }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">狀態</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200 ">
            <tr v-if="loading">
              <td colspan="15" class="px-6 py-6 text-center text-gray-500 ">載入中...</td>
            </tr>
            <tr v-for="lead in leads" :key="lead.id" class="hover:bg-gray-50 ">
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">
                <div class="text-gray-900 ">{{ extractDomain(lead.payload?.['頁面_URL'] || lead.source) || '-' }}</div>
                <div class="text-sm text-gray-500 ">{{ lead.payload?.['頁面_URL'] || lead.source }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">{{ lead.channel || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">
                <div>{{ formatDate(lead.created_at) }}</div>
                <div class="text-sm">{{ formatTime(lead.created_at) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">{{ lead.assignee?.name || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">{{ lead.email || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">{{ lead.line_id || lead.payload?.['LINE_ID'] || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">{{ lead.payload?.['房屋區域'] || lead.payload?.['所在地區'] || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">{{ lead.payload?.['房屋地址'] || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">{{ lead.payload?.['資金需求'] || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">{{ lead.payload?.['貸款需求'] || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">{{ lead.payload?.['方便聯絡時間'] || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">{{ lead.ip_address || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">{{ lead.notes || lead.payload?.['備註'] || '-' }}</td>
             <!-- 動態自定義欄位顯示（is_visible=true） -->
             <td v-for="cf in visibleCaseFields" :key="cf.id" class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">
               {{ formatCustomFieldValue(lead.payload?.[cf.key], cf) }}
             </td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 ">
                <span :class="['px-2 py-1 rounded text-xs', getStatusClass(lead.status)]">{{ LEAD_STATUS_LABELS[lead.status] || lead.status }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-base font-medium space-x-3">
                <button class="py-1 border rounded text-sm text-blue-600" @click="goPrev(lead)">上一步</button>
                <button class="py-1 border rounded text-sm text-blue-600" @click="goNext(lead)">下一步</button>
              </td>
            </tr>
            <tr v-if="!loading && leads.length === 0">
              <td colspan="15" class="px-6 py-6 text-center text-gray-500 ">沒有資料</td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
        <div class="flex space-x-2">
          <button @click="prevPage" :disabled="pagination.currentPage === 1" class="px-3 py-1 border rounded text-sm disabled:opacity-50 ">上一頁</button>
          <button @click="nextPage" :disabled="pagination.currentPage === totalPages" class="px-3 py-1 border rounded text-sm disabled:opacity-50 ">下一頁</button>
        </div>
        <div class="text-sm text-gray-500 ">第 {{ pagination.currentPage }} / {{ totalPages }} 頁</div>
      </div>
    </div>

  </div>
</template>

<script setup>
definePageMeta({ middleware: 'auth' })

const { listSubmittable, updateOne: updateLead } = useLeads()
const { pagination, totalPages, startIndex, endIndex, nextPage, prevPage, updatePagination } = usePagination(10)
const { validateCaseForm } = useFormValidation()
const { success, error: showError } = useNotification()
const { PAGINATION_OPTIONS, SEARCH_CONFIG, LEAD_STATUS_LABELS } = useConstants()
const { list: listCustomFields } = useCustomFields()
const auth = useAuthStore()

const loading = ref(false)
const submitting = ref(false) // 不再使用送件功能，保留狀態占位
const search = ref('')
const leads = ref([])

// 移除送件 modal 功能
const submitOpen = ref(false) // 保留占位避免大改，實際不顯示
const selectedLead = ref(null)
const form = reactive({ loan_amount: null, loan_type: '', loan_term: null, interest_rate: null, notes: '' })

// 自定義欄位（案件）
const caseFields = ref([])
const loadCaseFields = async () => {
  const { success, items } = await listCustomFields('case')
  if (success) caseFields.value = items
}

const load = async () => {
  loading.value = true
  // 搜尋優化：最少字符限制
  const searchValue = search.value.trim()
  if (searchValue && searchValue.length < SEARCH_CONFIG.MIN_CHARACTERS) {
    leads.value = []
    pagination.total = 0
    loading.value = false
    return
  }

  const { items, meta, success: apiSuccess } = await listSubmittable({ 
    search: searchValue,
    page: pagination.currentPage,
    per_page: pagination.perPage,
    assigned_to: auth.user?.value?.id || ''
  })
  if (apiSuccess) {
    leads.value = items
    updatePagination(meta)
  }
  loading.value = false
}

onMounted(async () => { await Promise.all([load(), loadCaseFields()]) })

// 搜尋防抖
let searchTimer
const debouncedLoad = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, SEARCH_CONFIG.DEBOUNCE_DELAY)
}

watch([() => pagination.currentPage, () => pagination.perPage], load)
watch(search, debouncedLoad)

// 組件銷毀時清理
onUnmounted(() => {
  clearTimeout(searchTimer)
})

// 上一步：依序回退狀態（approved -> intake -> pending）
const goPrev = async (lead) => {
  try {
    let prev = 'intake'
    if (lead.status === 'approved') prev = 'intake'
    else if (lead.status === 'intake') prev = 'pending'
    else return // 非預期狀態不處理

    const payload = { status: prev }
    if (prev === 'pending') payload.assigned_to = null

    const { error } = await updateLead(lead.id, payload)
    if (!error) {
      success('已回退狀態')
      await load()
    } else {
      showError(error.message || '回退失敗')
    }
  } catch (e) {
    console.error(e)
    showError('系統錯誤，請稍後再試')
  }
}
// 下一步：依序前進狀態（intake -> approved -> submitted）
const goNext = async (lead) => {
  try {
    let next = null
    if (lead.status === 'intake') next = 'approved'
    else if (lead.status === 'approved') next = 'submitted'
    else return

    const { error } = await updateLead(lead.id, { status: next })
    if (!error) { success('已前進狀態'); await load() } else { showError(error.message || '操作失敗') }
  } catch (e) { console.error(e); showError('系統錯誤，請稍後再試') }
}

// 工具函式（與其他頁一致）
const extractDomain = (url) => {
  if (!url) return ''
  try { return new URL(url).hostname.replace('www.', '') } catch { return url }
}
const formatDate = (d) => new Date(d).toLocaleDateString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit' })
const formatTime = (d) => new Date(d).toLocaleTimeString('zh-TW', { hour: '2-digit', minute: '2-digit' })
const visibleCaseFields = computed(() => caseFields.value.filter(f => f.is_visible))

const formatCustomFieldValue = (val, cf) => {
  if (cf.type === 'multiselect') return Array.isArray(val) ? val.join(', ') : (val || '-')
  if (cf.type === 'boolean') return val ? '是' : '否'
  return val ?? '-'
}

const getStatusClass = (status) => {
  const base = 'px-2 py-1 rounded text-xs '
  switch (status) {
    case 'pending': return base + 'bg-gray-100 text-gray-700'
    case 'intake': return base + 'bg-amber-100 text-amber-700'
    case 'approved': return base + 'bg-green-100 text-green-700'
    case 'submitted': return base + 'bg-blue-100 text-blue-700'
    case 'disbursed': return base + 'bg-purple-100 text-purple-700'
    default: return base + 'bg-gray-100 text-gray-700'
  }
}

const closeSubmit = () => { 
  submitOpen.value = false
  selectedLead.value = null
  // 重置表單
  Object.assign(form, { loan_amount: null, loan_type: '', loan_term: null, interest_rate: null, notes: '' })
}
const doSubmit = async () => {
  if (!selectedLead.value) return
  
  // 使用統一的表單驗證
  const { isValid, errors } = validateCaseForm(form)
  if (!isValid) {
    const errorMessages = Object.values(errors).join('\n')
    showError(`表單驗證失敗：\n${errorMessages}`)
    return
  }
  
  submitting.value = true
  try {
    const { error, data } = await convertToCase(selectedLead.value, { ...form })
    
    if (!error) {
      submitOpen.value = false
      // 重置表單
      Object.assign(form, { loan_amount: null, loan_type: '', loan_term: null, interest_rate: null, notes: '' })
      await load()
      
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
    console.error('Submit case error:', err)
  } finally {
    submitting.value = false
  }
}
</script>
