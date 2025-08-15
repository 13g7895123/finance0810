<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">待處理案件</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">顯示來自 WP 表單的進件（可搜尋、編輯、刪除）</p>
      </div>
      <div class="flex items-center space-x-3">
        <input
          v-model="search"
          type="text"
          placeholder="搜尋姓名/手機/Email/LINE/網站... (至少2個字符)"
          class="px-3 py-2 border rounded-lg dark:bg-gray-800 dark:border-gray-700"
        />
        <select v-model="pagination.perPage" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700">
          <option v-for="option in PAGINATION_OPTIONS" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">WP 進件列表</h3>
        <div class="text-sm text-gray-500 dark:text-gray-400">
          第
          <span class="font-medium">{{ startIndex + 1 }}</span>
          -
          <span class="font-medium">{{ Math.min(endIndex, pagination.total) }}</span>
          筆，共 <span class="font-medium">{{ pagination.total }}</span> 筆
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">序號</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">日期 / 時間</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">網站</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">客戶資訊</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">地區</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">操作</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading">
              <td colspan="6" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">載入中...</td>
            </tr>
            <tr v-for="lead in leads" :key="lead.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-900 dark:text-white">{{ lead.id }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 dark:text-gray-300">
                <div>{{ formatDate(lead.created_at) }}</div>
                <div class="text-sm">{{ formatTime(lead.created_at) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 dark:text-gray-300">
                <div class="text-gray-900 dark:text-white">{{ extractDomain(lead.source) || '-' }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[240px]">{{ lead.source }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-gray-900 dark:text-white">{{ lead.name || '-' }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                  <span v-if="lead.phone">{{ lead.phone }}</span>
                  <span v-if="lead.email"> · {{ lead.email }}</span>
                  <span v-if="lead.line_id"> · LINE: {{ lead.line_id }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700 dark:text-gray-300">
                {{ lead.payload?.['所在地區'] || '-' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-base font-medium space-x-3">
                <button @click="onEdit(lead)" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">編輯</button>
                <button @click="openConvert(lead)" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">轉送件</button>
                <button @click="onDelete(lead)" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">刪除</button>
              </td>
            </tr>
            <tr v-if="!loading && leads.length === 0">
              <td colspan="6" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">沒有資料</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div class="flex space-x-2">
          <button @click="prevPage" :disabled="pagination.currentPage === 1" class="px-3 py-1 border rounded text-sm disabled:opacity-50 dark:bg-gray-800 dark:border-gray-600">上一頁</button>
          <button @click="nextPage" :disabled="pagination.currentPage === totalPages" class="px-3 py-1 border rounded text-sm disabled:opacity-50 dark:bg-gray-800 dark:border-gray-600">下一頁</button>
        </div>
        <div class="text-sm text-gray-500 dark:text-gray-400">第 {{ pagination.currentPage }} / {{ totalPages }} 頁</div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="editOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeEdit">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-xl">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">編輯進件</h3>
        <form @submit.prevent="saveEdit" class="space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm mb-1">姓名</label>
              <input v-model="form.name" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
            </div>
            <div>
              <label class="block text-sm mb-1">手機</label>
              <input v-model="form.phone" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
            </div>
            <div>
              <label class="block text-sm mb-1">Email</label>
              <input v-model="form.email" type="email" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
            </div>
            <div>
              <label class="block text-sm mb-1">LINE ID</label>
              <input v-model="form.line_id" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm mb-1">網站來源</label>
              <input v-model="form.source" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
            </div>
          </div>
          <div class="flex justify-end space-x-3 pt-2">
            <button type="button" class="px-4 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" @click="closeEdit">取消</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded" :disabled="saving">{{ saving ? '儲存中...' : '儲存' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Convert Modal -->
  <div v-if="convertOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeConvert">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-lg">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">送件（建立案件）</h3>
      <form @submit.prevent="doConvert" class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm mb-1">貸款金額</label>
            <input v-model.number="convertForm.loan_amount" required type="number" min="0" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
          </div>
          <div>
            <label class="block text-sm mb-1">貸款類型</label>
            <input v-model="convertForm.loan_type" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
          </div>
          <div>
            <label class="block text-sm mb-1">期數（月）</label>
            <input v-model.number="convertForm.loan_term" type="number" min="0" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
          </div>
          <div>
            <label class="block text-sm mb-1">利率</label>
            <input v-model.number="convertForm.interest_rate" type="number" min="0" step="0.01" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm mb-1">備註</label>
            <textarea v-model="convertForm.notes" rows="2" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700"></textarea>
          </div>
        </div>
        <div class="flex justify-end space-x-3 pt-2">
          <button type="button" class="px-4 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" @click="closeConvert">取消</button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">送件</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
definePageMeta({ middleware: 'auth' })

const { list: listLeads, updateOne: updateLead, removeOne: removeLead, convertToCase } = useLeads()
const { pagination, totalPages, startIndex, endIndex, nextPage, prevPage, updatePagination } = usePagination(10)
const { validateCaseForm } = useFormValidation()
const { success, error: showError, confirm } = useNotification()
const { PAGINATION_OPTIONS, SEARCH_CONFIG } = useConstants()

// state
const leads = ref([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')

// edit modal
const editOpen = ref(false)
const editingId = ref(null)
const form = reactive({ name: '', phone: '', email: '', line_id: '', source: '', is_suspected_blacklist: false, suspected_reason: '' })

// convert modal
const convertOpen = ref(false)
const convertLead = ref(null)
const convertForm = reactive({ loan_amount: null, loan_type: '', loan_term: null, interest_rate: null, notes: '' })

// lifecycle
const loadLeads = async () => {
  loading.value = true
  
  // 搜尋優化：最少字符限制
  const searchValue = search.value.trim()
  if (searchValue && searchValue.length < SEARCH_CONFIG.MIN_CHARACTERS) {
    leads.value = []
    pagination.total = 0
    loading.value = false
    return
  }
  
  const { items, meta, success: apiSuccess } = await listLeads({
    page: pagination.currentPage,
    per_page: pagination.perPage,
    search: searchValue,
    channel: 'wp_form'
  })
  if (apiSuccess) {
    leads.value = items
    updatePagination(meta)
  }
  loading.value = false
}

onMounted(loadLeads)

// 搜尋防抖
let searchTimer
const debouncedLoadLeads = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadLeads, SEARCH_CONFIG.DEBOUNCE_DELAY)
}

watch([() => pagination.currentPage, () => pagination.perPage], loadLeads)
watch(search, debouncedLoadLeads)

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

// 移除重複的分頁邏輯（已由 usePagination 提供）

// edit & delete
const onEdit = (lead) => {
  editingId.value = lead.id
  Object.assign(form, {
    name: lead.name || '',
    phone: lead.phone || '',
    email: lead.email || '',
    line_id: lead.line_id || '',
    source: lead.source || '',
    is_suspected_blacklist: !!lead.is_suspected_blacklist,
    suspected_reason: lead.suspected_reason || ''
  })
  editOpen.value = true
}
const closeEdit = () => { editOpen.value = false; editingId.value = null }
const saveEdit = async () => {
  if (!editingId.value) return
  
  saving.value = true
  try {
    const { error } = await updateLead(editingId.value, { ...form })
    
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
