<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">可送件案件</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">顯示可送件的客戶（無案件，且狀態為有意願/已聯絡）</p>
      </div>
      <div class="flex items-center space-x-3">
        <input 
          v-model="search" 
          placeholder="搜尋客戶/電話/Email (至少2個字符)" 
          class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700"
        />
        <select v-model="pagination.perPage" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700">
          <option v-for="option in PAGINATION_OPTIONS" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <button @click="load" class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700" :disabled="loading">
          {{ loading ? '載入中...' : '重新載入' }}
        </button>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">客戶清單</h3>
        <div class="text-sm text-gray-500 dark:text-gray-400">
          第 <span class="font-medium">{{ startIndex + 1 }}</span> -
          <span class="font-medium">{{ Math.min(endIndex, pagination.total) }}</span>
          筆，共 <span class="font-medium">{{ pagination.total }}</span> 筆
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">客戶</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">聯絡方式</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">來源/網站</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">動作</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading">
              <td colspan="4" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">載入中...</td>
            </tr>
            <tr v-for="c in customers" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4">
                <div class="text-gray-900 dark:text-white">{{ c.name }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ c.region || '-' }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="text-sm text-gray-900 dark:text-white">{{ c.phone }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ c.email || '-' }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="text-sm text-gray-900 dark:text-white">{{ c.channel || '-' }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ c.website_source || '-' }}</div>
              </td>
              <td class="px-6 py-4">
                <button class="px-3 py-1 border rounded text-sm" @click="openSubmit(c)">送件</button>
              </td>
            </tr>
            <tr v-if="!loading && customers.length === 0">
              <td colspan="4" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">沒有資料</td>
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

    <!-- Submit Modal -->
    <div v-if="submitOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeSubmit">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-lg">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">建立案件（送件）</h3>
        <form @submit.prevent="doSubmit" class="space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm mb-1">貸款金額</label>
              <input v-model.number="form.loan_amount" required type="number" min="0" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
            </div>
            <div>
              <label class="block text-sm mb-1">貸款類型</label>
              <input v-model="form.loan_type" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
            </div>
            <div>
              <label class="block text-sm mb-1">期數（月）</label>
              <input v-model.number="form.loan_term" type="number" min="0" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
            </div>
            <div>
              <label class="block text-sm mb-1">利率</label>
              <input v-model.number="form.interest_rate" type="number" min="0" step="0.01" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm mb-1">備註</label>
              <textarea v-model="form.notes" rows="2" class="w-full px-3 py-2 border rounded dark:bg-gray-900 dark:border-gray-700"></textarea>
            </div>
          </div>
          <div class="flex justify-end space-x-3 pt-2">
            <button type="button" class="px-4 py-2 border rounded dark:bg-gray-900 dark:border-gray-700" @click="closeSubmit">取消</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded" :disabled="submitting">{{ submitting ? '送出中...' : '送出' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({ middleware: 'auth' })

const { list: listSubmittable, submitCase } = useSubmittable()
const { pagination, totalPages, startIndex, endIndex, nextPage, prevPage, updatePagination } = usePagination(10)
const { validateCaseForm } = useFormValidation()
const { success, error: showError } = useNotification()
const { PAGINATION_OPTIONS, SEARCH_CONFIG } = useConstants()

const loading = ref(false)
const submitting = ref(false)
const search = ref('')
const customers = ref([])

const submitOpen = ref(false)
const selectedCustomer = ref(null)
const form = reactive({ loan_amount: null, loan_type: '', loan_term: null, interest_rate: null, notes: '' })

const load = async () => {
  loading.value = true
  // 搜尋優化：最少字符限制
  const searchValue = search.value.trim()
  if (searchValue && searchValue.length < SEARCH_CONFIG.MIN_CHARACTERS) {
    customers.value = []
    pagination.total = 0
    loading.value = false
    return
  }

  const { items, meta, success: apiSuccess } = await listSubmittable({ 
    search: searchValue,
    page: pagination.currentPage,
    per_page: pagination.perPage
  })
  if (apiSuccess) {
    customers.value = items
    updatePagination(meta)
  }
  loading.value = false
}

onMounted(load)

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

const openSubmit = (c) => { selectedCustomer.value = c; submitOpen.value = true }
const closeSubmit = () => { 
  submitOpen.value = false
  selectedCustomer.value = null
  // 重置表單
  Object.assign(form, { loan_amount: null, loan_type: '', loan_term: null, interest_rate: null, notes: '' })
}
const doSubmit = async () => {
  if (!selectedCustomer.value) return
  
  // 使用統一的表單驗證
  const { isValid, errors } = validateCaseForm(form)
  if (!isValid) {
    const errorMessages = Object.values(errors).join('\n')
    showError(`表單驗證失敗：\n${errorMessages}`)
    return
  }
  
  submitting.value = true
  try {
    const { error, data } = await submitCase(selectedCustomer.value.id, { ...form })
    
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
