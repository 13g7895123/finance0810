<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">進行中案件</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">查看 submitted / approved 的案件</p>
      </div>
      <div class="flex items-center space-x-3">
        <input v-model="search" placeholder="搜尋案件編號/客戶/電話/Email" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" />
        <input v-model="minAmount" type="number" placeholder="最小金額" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700 w-24" />
        <input v-model="maxAmount" type="number" placeholder="最大金額" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700 w-24" />
        <input v-model="loanType" placeholder="貸款類型" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" />
        <select v-model="pagination.perPage" class="px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700">
          <option v-for="option in PAGINATION_OPTIONS" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">案件列表</h3>
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
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">案件編號</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">客戶</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">金額</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">狀態</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">日期</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">操作</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading">
              <td colspan="6" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">載入中...</td>
            </tr>
            <tr v-for="item in cases" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4">{{ item.case_number }}</td>
              <td class="px-6 py-4">
                <div class="text-gray-900 dark:text-white">{{ item.customer?.name || '-' }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ item.customer?.phone || '' }}</div>
              </td>
              <td class="px-6 py-4">{{ item.loan_amount ?? '-' }}</td>
              <td class="px-6 py-4 capitalize">{{ item.status }}</td>
              <td class="px-6 py-4">
                <div v-if="item.submitted_at">送件：{{ formatDate(item.submitted_at) }}</div>
                <div v-if="item.approved_at">核準：{{ formatDate(item.approved_at) }}</div>
              </td>
              <td class="px-6 py-4 space-x-2">
                <button class="px-3 py-1 border rounded text-sm" @click="markApproved(item)">核准</button>
                <button class="px-3 py-1 border rounded text-sm" @click="markRejected(item)">婉拒</button>
                <button class="px-3 py-1 border rounded text-sm" @click="markDisbursed(item)">撥款</button>
              </td>
            </tr>
            <tr v-if="!loading && cases.length === 0">
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
</template>

<script setup>
definePageMeta({ middleware: 'auth' })

const { list: listCases, updateOne: updateCase } = useCases()
const { pagination, totalPages, startIndex, endIndex, nextPage, prevPage, updatePagination } = usePagination(10)
const { validateCaseForm } = useFormValidation()
const { success, error: showError, confirm } = useNotification()
const { CASE_STATUSES, canTransitionCaseStatus, getCaseStatusLabel, PAGINATION_OPTIONS } = useConstants()

const loading = ref(false)
const cases = ref([])
const search = ref('')
const minAmount = ref('')
const maxAmount = ref('')
const loanType = ref('')

const load = async () => {
  loading.value = true
  const { items, meta, success } = await listCases({
    status: [CASE_STATUSES.SUBMITTED, CASE_STATUSES.APPROVED],
    search: search.value,
    min_amount: minAmount.value || undefined,
    max_amount: maxAmount.value || undefined,
    loan_type: loanType.value || undefined,
    page: pagination.currentPage,
    per_page: pagination.perPage
  })
  if (success) {
    cases.value = items
    updatePagination(meta)
  }
  loading.value = false
}

onMounted(load)
watch([() => pagination.currentPage, () => pagination.perPage, search, minAmount, maxAmount, loanType], load)

const formatDate = (d) => new Date(d).toLocaleDateString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit' })

const markApproved = async (item) => {
  // 檢查當前狀態是否允許轉為 approved
  if (!canTransitionCaseStatus(item.status, CASE_STATUSES.APPROVED)) {
    showError('只有送件狀態的案件才能核准')
    return
  }
  
  const approvedAmount = prompt('請輸入核准金額（數字）:')
  if (!approvedAmount || isNaN(Number(approvedAmount))) {
    showError('請輸入有效的核准金額')
    return
  }
  
  const confirmed = await confirm(`將案件 ${item.case_number} 標記為核准，核准金額 ${approvedAmount}？`)
  if (!confirmed) return
  
  const { error } = await updateCase(item.id, { 
    status: CASE_STATUSES.APPROVED,
    approved_amount: Number(approvedAmount)
  })
  
  if (!error) {
    success(`案件 ${item.case_number} 已核准，金額 ${approvedAmount}`)
    await load()
  } else {
    showError(error.message || '更新失敗')
  }
}
const markRejected = async (item) => {
  // 檢查當前狀態是否允許轉為 rejected
  if (!canTransitionCaseStatus(item.status, CASE_STATUSES.REJECTED)) {
    showError('只有送件或核准狀態的案件才能婉拒')
    return
  }
  
  const reason = prompt('請輸入婉拒原因（必填）:')
  if (!reason || reason.trim() === '') {
    showError('婉拒原因為必填項目')
    return
  }
  
  const confirmed = await confirm(`確定要婉拒案件 ${item.case_number}？`)
  if (!confirmed) return
  
  const { error } = await updateCase(item.id, { 
    status: CASE_STATUSES.REJECTED, 
    rejection_reason: reason.trim() 
  })
  
  if (!error) {
    success(`案件 ${item.case_number} 已婉拒`)
    await load()
  } else {
    showError(error.message || '更新失敗')
  }
}
const markDisbursed = async (item) => {
  // 檢查當前狀態是否允許轉為 disbursed
  if (!canTransitionCaseStatus(item.status, CASE_STATUSES.DISBURSED)) {
    showError('只有核准狀態的案件才能撥款')
    return
  }
  
  const amount = prompt('請輸入撥款金額（數字）:', item.approved_amount || '')
  if (!amount || isNaN(Number(amount))) {
    showError('請輸入有效的撥款金額')
    return
  }
  
  const confirmed = await confirm(`確定撥款 ${amount} 元給案件 ${item.case_number}？`)
  if (!confirmed) return
  
  const { error } = await updateCase(item.id, { 
    status: CASE_STATUSES.DISBURSED, 
    disbursed_amount: Number(amount) 
  })
  
  if (!error) {
    success(`案件 ${item.case_number} 已撥款 ${amount} 元`)
    await load()
  } else {
    showError(error.message || '更新失敗')
  }
}
</script>
