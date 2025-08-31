<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">WordPress網站管理</h1>
        <p class="text-gray-600 mt-2">管理和編輯WordPress網站設定，統一管理所有進件來源</p>
      </div>
      <button 
        @click="openCreateModal" 
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2"
      >
        <Icon name="heroicons:plus" class="w-5 h-5" />
        <span>新增網站</span>
      </button>
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

    <!-- Filters and Search -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <div class="flex flex-col md:flex-row gap-4">
        <div class="flex-1">
          <input 
            v-model="filters.search" 
            @input="searchWebsites"
            placeholder="搜尋網站名稱或域名..." 
            class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <select v-model="filters.status" @change="loadWebsites" class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
          <option value="">所有狀態</option>
          <option value="active">運行中</option>
          <option value="inactive">已停用</option>
          <option value="maintenance">維護中</option>
        </select>
        <select v-model="filters.type" @change="loadWebsites" class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
          <option value="">所有類型</option>
          <option value="wordpress">WordPress</option>
          <option value="other">其他</option>
        </select>
      </div>
    </div>

    <!-- Websites Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">網站列表</h2>
      </div>
      
      <div v-if="loading" class="p-8 text-center">
        <div class="animate-spin inline-block w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full"></div>
        <div class="mt-2 text-gray-500">載入中...</div>
      </div>

      <div v-else-if="websites.data && websites.data.length === 0" class="p-8 text-center text-gray-500">
        尚無網站資料
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">網站</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">域名</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">狀態</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">統計</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Webhook</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="website in websites.data" :key="website.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div>
                  <div class="text-sm font-medium text-gray-900">{{ website.name }}</div>
                  <div class="text-sm text-gray-500">{{ website.type === 'wordpress' ? 'WordPress' : '其他' }}</div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <a :href="website.url" target="_blank" class="text-blue-600 hover:text-blue-800">
                  {{ website.domain }}
                </a>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getStatusClass(website.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                  {{ getStatusText(website.status) }}
                </span>
                <div v-if="website.is_healthy" class="text-xs text-green-500 mt-1">健康</div>
                <div v-else class="text-xs text-red-500 mt-1">需關注</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                <div>進件: {{ website.statistics?.total_leads || 0 }}</div>
                <div>客戶: {{ website.statistics?.total_customers || 0 }}</div>
                <div>轉換率: {{ website.conversion_rate }}%</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="website.webhook_enabled ? 'text-green-500' : 'text-red-500'" class="text-sm">
                  {{ website.webhook_enabled ? '已啟用' : '已停用' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button 
                  @click="editWebsite(website)" 
                  class="text-blue-600 hover:text-blue-900"
                >
                  編輯
                </button>
                <button 
                  @click="deleteWebsite(website)" 
                  class="text-red-600 hover:text-red-900"
                >
                  刪除
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="websites.last_page > 1" class="bg-gray-50 px-6 py-3 border-t border-gray-200">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-700">
            顯示 {{ websites.from }} 到 {{ websites.to }} 共 {{ websites.total }} 筆
          </div>
          <div class="flex space-x-1">
            <button 
              v-for="page in getPaginationPages()" 
              :key="page"
              @click="changePage(page)"
              :class="page === websites.current_page ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
              class="px-3 py-1 border rounded"
            >
              {{ page }}
            </button>
          </div>
        </div>
      </div>
    </div>

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

// Methods
const loadWebsites = async (page = 1) => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: '15',
      ...filters.value
    })
    
    const { data } = await $fetch(`/api/websites?${params}`)
    websites.value = data
  } catch (error) {
    console.error('載入網站失敗:', error)
    useToast().add({
      title: '載入失敗',
      description: '無法載入網站列表',
      color: 'red'
    })
  } finally {
    loading.value = false
  }
}

const loadStatistics = async () => {
  try {
    const { data } = await $fetch('/api/websites-statistics')
    statistics.value = data
  } catch (error) {
    console.error('載入統計失敗:', error)
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
    if (editingWebsite.value) {
      // Update existing website
      await $fetch(`/api/websites/${editingWebsite.value.id}`, {
        method: 'PUT',
        body: form.value
      })
      useToast().add({
        title: '更新成功',
        description: '網站資料已更新',
        color: 'green'
      })
    } else {
      // Create new website
      await $fetch('/api/websites', {
        method: 'POST',
        body: form.value
      })
      useToast().add({
        title: '建立成功',
        description: '新網站已建立',
        color: 'green'
      })
    }
    
    closeModal()
    await loadWebsites()
    await loadStatistics()
    
  } catch (error) {
    console.error('儲存失敗:', error)
    useToast().add({
      title: '儲存失敗',
      description: error.data?.message || '網站儲存失敗',
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
    await $fetch(`/api/websites/${website.id}`, {
      method: 'DELETE'
    })
    
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
      description: error.data?.message || '網站刪除失敗',
      color: 'red'
    })
  }
}

const closeModal = () => {
  modalOpen.value = false
  editingWebsite.value = null
}

const changePage = (page) => {
  loadWebsites(page)
}

const getPaginationPages = () => {
  const pages = []
  const current = websites.value.current_page
  const total = websites.value.last_page
  
  for (let i = Math.max(1, current - 2); i <= Math.min(total, current + 2); i++) {
    pages.push(i)
  }
  
  return pages
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