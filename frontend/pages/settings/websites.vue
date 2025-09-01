<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">WordPress網站管理</h1>
        <p class="text-gray-600 mt-2">管理和編輯WordPress網站設定，統一管理所有進件來源</p>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
      <div class="bg-white rounded-lg shadow-sm border p-4">
        <div class="text-sm text-gray-500">總網站數</div>
        <div class="text-2xl font-bold text-gray-900">{{ statistics.summary?.total_websites || 0 }}</div>
      </div>
      <div class="bg-white rounded-lg shadow-sm border p-4">
        <div class="text-sm text-gray-500">運行中</div>
        <div class="text-2xl font-bold text-green-600">{{ statistics.summary?.active_websites || 0 }}</div>
      </div>
      <div class="bg-white rounded-lg shadow-sm border p-4">
        <div class="text-sm text-gray-500">WordPress</div>
        <div class="text-2xl font-bold text-blue-600">{{ statistics.summary?.wordpress_websites || 0 }}</div>
      </div>
      <div class="bg-white rounded-lg shadow-sm border p-4">
        <div class="text-sm text-gray-500">Webhook啟用</div>
        <div class="text-2xl font-bold text-purple-600">{{ statistics.summary?.webhook_enabled || 0 }}</div>
      </div>
      <div class="bg-white rounded-lg shadow-sm border p-4">
        <div class="text-sm text-gray-500">健康網站</div>
        <div class="text-2xl font-bold text-green-500">{{ statistics.summary?.healthy_websites || 0 }}</div>
      </div>
    </div>

    <!-- Websites DataTable -->
    <DataTable
      title="網站列表"
      :columns="websiteColumns"
      :data="websiteData"
      :loading="loading"
      :error="error"
      :search-query="filters.search"
      search-placeholder="搜尋網站名稱..."
      :show-search-icon="false"
      :current-page="currentPage"
      :items-per-page="itemsPerPage"
      loading-text="載入中..."
      empty-text="尚無網站資料"
      @search="handleSearch"
      @refresh="loadWebsites"
      @retry="loadWebsites"
      @page-change="handlePageChange"
      @page-size-change="handlePageSizeChange"
    >
      <!-- Filter Controls -->
      <template #filters>
        <select v-model="filters.status" @change="loadWebsites" class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">所有狀態</option>
          <option value="active">運行中</option>
          <option value="inactive">已停用</option>
          <option value="maintenance">維護中</option>
        </select>
        <select v-model="filters.type" @change="loadWebsites" class="px-4 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">所有類型</option>
          <option value="wordpress">WordPress</option>
          <option value="other">其他</option>
        </select>
      </template>
      
      <!-- Action Buttons -->
      <template #actions>
        <button 
          @click="openCreateModal" 
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center space-x-2"
        >
          <PlusIcon class="w-5 h-5" />
          <span>新增網站</span>
        </button>
      </template>
      
      <!-- Website Cell -->
      <template #cell-website="{ item }">
        <div>
          <a :href="item.url" target="_blank" class="text-sm font-medium text-blue-600 hover:text-blue-800">
            {{ item.name }}
          </a>
          <div class="text-sm text-gray-500">{{ item.type === 'wordpress' ? 'WordPress' : '其他' }}</div>
        </div>
      </template>
      
      <!-- Status Cell -->
      <template #cell-status="{ item }">
        <div>
          <span :class="getStatusClass(item.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
            {{ getStatusText(item.status) }}
          </span>
          <div v-if="item.is_healthy" class="text-xs text-green-500 mt-1">健康</div>
          <div v-else class="text-xs text-red-500 mt-1">需關注</div>
        </div>
      </template>
      
      <!-- Statistics Cell -->
      <template #cell-statistics="{ item }">
        <div class="text-sm text-gray-900">
          <div>進件: {{ item.statistics?.total_leads || 0 }}</div>
          <div>客戶: {{ item.statistics?.total_customers || 0 }}</div>
          <div>轉換率: {{ item.conversion_rate }}%</div>
        </div>
      </template>
      
      <!-- Webhook Cell -->
      <template #cell-webhook="{ item }">
        <span :class="item.webhook_enabled ? 'text-green-500' : 'text-red-500'" class="text-sm">
          {{ item.webhook_enabled ? '已啟用' : '已停用' }}
        </span>
      </template>
      
      <!-- Actions Cell -->
      <template #cell-actions="{ item }">
        <div class="flex items-center space-x-2 justify-end">
          <button 
            @click="editWebsite(item)" 
            class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-50 rounded-lg transition-all duration-200 group relative"
            title="編輯網站"
          >
            <PencilIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              編輯
            </span>
          </button>
          
          <button 
            @click="deleteWebsite(item)" 
            class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-all duration-200 group relative"
            title="刪除網站"
          >
            <TrashIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              刪除
            </span>
          </button>
        </div>
      </template>
    </DataTable>

    <!-- Create/Edit Modal -->
    <div v-if="modalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeModal">
      <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
          {{ editingWebsite ? '編輯網站' : '新增網站' }}
        </h3>
        
        <form @submit.prevent="saveWebsite" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">網站名稱 *</label>
              <input 
                v-model="form.name" 
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="例如：熊好貸"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">域名 *</label>
              <input 
                v-model="form.domain" 
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="例如：example.com"
              />
            </div>
            
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">完整網址 *</label>
              <input 
                v-model="form.url" 
                type="url"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="https://example.com"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">狀態</label>
              <select v-model="form.status" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="active">運行中</option>
                <option value="inactive">已停用</option>
                <option value="maintenance">維護中</option>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">類型</label>
              <select v-model="form.type" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="wordpress">WordPress</option>
                <option value="other">其他</option>
              </select>
            </div>
            
            <div class="md:col-span-2">
              <label class="flex items-center space-x-2">
                <input type="checkbox" v-model="form.webhook_enabled" class="rounded" />
                <span class="text-sm font-medium text-gray-700">啟用Webhook</span>
              </label>
            </div>
            
            <div v-if="form.webhook_enabled" class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Webhook網址</label>
              <input 
                v-model="form.webhook_url" 
                type="url"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="https://example.com/webhook"
              />
            </div>
            
            <div v-if="form.webhook_enabled" class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Webhook密鑰</label>
              <input 
                v-model="form.webhook_secret" 
                type="password"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="webhook密鑰"
              />
            </div>
            
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">備註</label>
              <textarea 
                v-model="form.notes" 
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="網站相關備註..."
              ></textarea>
            </div>
          </div>
          
          <div class="flex justify-end space-x-3 pt-4">
            <button 
              type="button" 
              @click="closeModal"
              class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
            >
              取消
            </button>
            <button 
              type="submit" 
              :disabled="saving"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
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
import { ref, onMounted, computed } from 'vue'
import DataTable from '~/components/DataTable.vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

definePageMeta({
  middleware: 'role'
})

useHead({
  title: 'WordPress網站管理 - 金融管理系統'
})

// Reactive data
const websites = ref({ data: [], current_page: 1, last_page: 1, total: 0, from: 0, to: 0 })
const statistics = ref({})
const loading = ref(false)
const saving = ref(false)
const modalOpen = ref(false)
const editingWebsite = ref(null)
const error = ref('')

// Pagination
const currentPage = ref(1)
const itemsPerPage = ref(15)

// Filters
const filters = ref({
  search: '',
  status: '',
  type: ''
})

// Form data
const form = ref({
  name: '',
  domain: '',
  url: '',
  status: 'active',
  type: 'wordpress',
  webhook_enabled: true,
  webhook_url: '',
  webhook_secret: '',
  notes: ''
})

// Initialize API composable
const { get, post, put, del } = useApi()

// DataTable columns
const websiteColumns = [
  {
    key: 'website',
    title: '網站',
    sortable: true,
    width: '200px'
  },
  {
    key: 'status',
    title: '狀態',
    sortable: true,
    width: '120px'
  },
  {
    key: 'statistics',
    title: '統計',
    sortable: false,
    width: '150px'
  },
  {
    key: 'webhook',
    title: 'Webhook',
    sortable: true,
    width: '100px'
  },
  {
    key: 'actions',
    title: '操作',
    sortable: false,
    width: '120px'
  }
]

// Computed properties
const websiteData = computed(() => {
  return websites.value.data || []
})

// Methods
const loadWebsites = async (page = currentPage.value) => {
  loading.value = true
  error.value = ''
  try {
    const params = {
      page: page.toString(),
      per_page: itemsPerPage.value.toString(),
      ...filters.value
    }
    
    const { data, error: apiError } = await get('/websites', params)
    if (apiError) {
      console.error('載入網站失敗:', apiError)
      error.value = apiError.message || '無法載入網站列表'
      useToast().add({
        title: '載入失敗',
        description: error.value,
        color: 'red'
      })
      return
    }
    // 後端返回分頁對象，保持完整的分頁信息
    websites.value = data || { data: [], current_page: 1, last_page: 1, total: 0, from: 0, to: 0 }
    currentPage.value = websites.value.current_page
  } catch (err) {
    console.error('載入網站失敗:', err)
    error.value = '無法載入網站列表'
    useToast().add({
      title: '載入失敗',
      description: error.value,
      color: 'red'
    })
  } finally {
    loading.value = false
  }
}

const loadStatistics = async () => {
  try {
    const { data, error } = await get('/websites-statistics')
    if (error) {
      console.error('載入統計失敗:', error)
      useToast().add({
        title: '載入統計失敗',
        description: error.message || '無法載入統計資料',
        color: 'red'
      })
      return
    }
    statistics.value = data
  } catch (error) {
    console.error('載入統計失敗:', error)
    useToast().add({
      title: '載入統計失敗',
      description: '無法載入統計資料',
      color: 'red'
    })
  }
}

const searchWebsites = debounce(() => {
  loadWebsites()
}, 300)

const openCreateModal = () => {
  editingWebsite.value = null
  form.value = {
    name: '',
    domain: '',
    url: '',
    status: 'active',
    type: 'wordpress',
    webhook_enabled: true,
    webhook_url: '',
    webhook_secret: '',
    notes: ''
  }
  modalOpen.value = true
}

const editWebsite = (website) => {
  editingWebsite.value = website
  form.value = {
    name: website.name,
    domain: website.domain,
    url: website.url,
    status: website.status,
    type: website.type,
    webhook_enabled: website.webhook_enabled,
    webhook_url: website.webhook_url || '',
    webhook_secret: website.webhook_secret || '',
    notes: website.notes || ''
  }
  modalOpen.value = true
}

const saveWebsite = async () => {
  saving.value = true
  try {
    let result
    if (editingWebsite.value) {
      // Update existing website
      result = await put(`/websites/${editingWebsite.value.id}`, form.value)
    } else {
      // Create new website
      result = await post('/websites', form.value)
    }
    
    if (result.error) {
      console.error('儲存失敗:', result.error)
      useToast().add({
        title: '儲存失敗',
        description: result.error.message || '網站儲存失敗',
        color: 'red'
      })
      return
    }
    
    useToast().add({
      title: editingWebsite.value ? '更新成功' : '建立成功',
      description: editingWebsite.value ? '網站資料已更新' : '新網站已建立',
      color: 'green'
    })
    
    closeModal()
    await loadWebsites()
    await loadStatistics()
    
  } catch (error) {
    console.error('儲存失敗:', error)
    useToast().add({
      title: '儲存失敗',
      description: '網站儲存失敗',
      color: 'red'
    })
  } finally {
    saving.value = false
  }
}

const deleteWebsite = async (website) => {
  if (!confirm(`確定要刪除網站「${website.name}」嗎？`)) {
    return
  }
  
  try {
    const result = await del(`/websites/${website.id}`)
    
    if (result.error) {
      console.error('刪除失敗:', result.error)
      useToast().add({
        title: '刪除失敗',
        description: result.error.message || '網站刪除失敗',
        color: 'red'
      })
      return
    }
    
    useToast().add({
      title: '刪除成功',
      description: '網站已刪除',
      color: 'green'
    })
    
    await loadWebsites()
    await loadStatistics()
    
  } catch (error) {
    console.error('刪除失敗:', error)
    useToast().add({
      title: '刪除失敗',
      description: '網站刪除失敗',
      color: 'red'
    })
  }
}

const closeModal = () => {
  modalOpen.value = false
  editingWebsite.value = null
}

// DataTable event handlers
const handleSearch = (query) => {
  filters.value.search = query
  // Search will be handled by searchWebsites debounce
}

const handlePageChange = (page) => {
  currentPage.value = page
  loadWebsites(page)
}

const handlePageSizeChange = (size) => {
  itemsPerPage.value = size
  currentPage.value = 1
  loadWebsites(1)
}

const getStatusClass = (status) => {
  const classes = {
    'active': 'bg-green-100 text-green-800',
    'inactive': 'bg-red-100 text-red-800',
    'maintenance': 'bg-yellow-100 text-yellow-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const getStatusText = (status) => {
  const texts = {
    'active': '運行中',
    'inactive': '已停用',
    'maintenance': '維護中'
  }
  return texts[status] || status
}

// Debounce utility
function debounce(func, wait) {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

// Lifecycle
onMounted(() => {
  loadWebsites()
  loadStatistics()
})
</script>