<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 ">黑名單案件</h1>
        <p class="text-gray-600 mt-2">顯示黑名單（blacklist）的案件，僅供檢視</p>
      </div>
    </div>

    <!-- DataTable Component -->
    <DataTable
      title="黑名單案件列表"
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
      
      <template #cell-status="{ item }">
        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
          黑名單
        </span>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import DataTable from '~/components/DataTable.vue'

definePageMeta({ middleware: 'auth' })
const authStore = useAuthStore()

const { list: listLeads } = useLeads()
const { pagination, totalPages, startIndex, endIndex, nextPage, prevPage, updatePagination } = usePagination(10)
const { PAGINATION_OPTIONS, SEARCH_CONFIG } = useConstants()
const { list: listCustomFields } = useCustomFields()

// state
const leads = ref([])
const loading = ref(false)
const search = ref('')
const loadError = ref(null)

// 自定義欄位（案件）
const caseFields = ref([])
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
    },
    {
      key: 'status',
      title: '狀態',
      sortable: false,
      width: '100px'
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
      status: 'blacklist'
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

const loadCaseFields = async () => {
  const { success, items } = await listCustomFields('case')
  if (success) caseFields.value = items
}

onMounted(async () => {
  await Promise.all([loadLeads(), loadCaseFields()]);
})

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

const formatCustomFieldValue = (val, cf) => {
  if (cf.type === 'multiselect') return Array.isArray(val) ? val.join(', ') : (val || '-')
  if (cf.type === 'boolean') return val ? '是' : '否'
  return val ?? '-'
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