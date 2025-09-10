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
            @click="openFieldMappingModal(item)" 
            class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-all duration-200 group relative"
            title="欄位對應設定"
          >
            <AdjustmentsHorizontalIcon class="w-4 h-4" />
            <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
              欄位對應
            </span>
          </button>
          
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

    <!-- Point 61: Field Mapping Modal -->
    <div v-if="fieldMappingModalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeFieldMappingModal">
      <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-lg font-semibold text-gray-900">
            {{ currentWebsite?.name }} - 表單欄位對應設定
          </h3>
          <button @click="closeFieldMappingModal" class="text-gray-500 hover:text-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <!-- Loading State -->
        <div v-if="loadingFieldMappings" class="flex items-center justify-center py-12">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <span class="ml-2 text-gray-600">載入欄位對應設定...</span>
        </div>

        <!-- Field Mappings Content -->
        <div v-else>
          <!-- Actions Bar -->
          <div class="flex items-center justify-between mb-6 p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center space-x-3">
              <button 
                @click="createDefaultMappings"
                :disabled="fieldMappings.length > 0 || savingFieldMappings"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                建立預設對應
              </button>
              
              <button 
                @click="testFieldMappings"
                :disabled="fieldMappings.length === 0 || savingFieldMappings"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                測試對應
              </button>
            </div>
            
            <div class="text-sm text-gray-600">
              已設定 {{ fieldMappings.length }} 個欄位對應
            </div>
          </div>

          <!-- No Mappings State -->
          <div v-if="fieldMappings.length === 0" class="text-center py-12">
            <AdjustmentsHorizontalIcon class="w-12 h-12 mx-auto text-gray-400 mb-4" />
            <h4 class="text-lg font-medium text-gray-900 mb-2">尚未設定欄位對應</h4>
            <p class="text-gray-600 mb-4">請建立預設對應或手動添加欄位對應設定</p>
            <button 
              @click="addFieldMapping"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
              添加欄位對應
            </button>
          </div>

          <!-- Field Mappings Table -->
          <div v-else class="space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="text-md font-medium text-gray-900">欄位對應設定</h4>
              <button 
                @click="addFieldMapping"
                class="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"
              >
                <PlusIcon class="w-4 h-4 inline mr-1" />
                添加對應
              </button>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">
                      系統欄位
                      <button 
                        @click="openAddSystemFieldModal"
                        class="ml-2 text-blue-600 hover:text-blue-800"
                        title="新增系統欄位"
                      >
                        <PlusIcon class="w-4 h-4" />
                      </button>
                    </th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">WordPress欄位名稱</th>
                    <th class="px-4 py-3 text-center text-sm font-medium text-gray-500">操作</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="(mapping, index) in fieldMappings" :key="index" class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                      <select 
                        v-model="mapping.system_field" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      >
                        <option value="">請選擇系統欄位</option>
                        <option 
                          v-for="(info, field) in systemFields" 
                          :key="field" 
                          :value="field"
                        >
                          {{ info.label }} ({{ field }})
                        </option>
                      </select>
                    </td>
                    <td class="px-4 py-3">
                      <input 
                        v-model="mapping.wp_field_name" 
                        @input="mapping.display_name = mapping.wp_field_name"
                        placeholder="例如：姓名"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      />
                    </td>
                    <td class="px-4 py-3 text-center">
                      <button 
                        @click="removeFieldMapping(index)"
                        class="p-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded"
                      >
                        <TrashIcon class="w-4 h-4" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end pt-4 border-t">
              <div class="flex space-x-3">
                <button 
                  @click="closeFieldMappingModal" 
                  type="button"
                  class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                >
                  取消
                </button>
                <button 
                  @click="saveFieldMappings" 
                  :disabled="savingFieldMappings || !isValidMappings"
                  class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {{ savingFieldMappings ? '儲存中...' : '儲存設定' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Point 62: Add System Field Modal -->
    <div v-if="addSystemFieldModalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeAddSystemFieldModal">
      <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-lg font-semibold text-gray-900">
            新增系統欄位
          </h3>
          <button @click="closeAddSystemFieldModal" class="text-gray-500 hover:text-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <form @submit.prevent="addCustomSystemField" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">欄位代碼</label>
            <input 
              v-model="newSystemField.key" 
              type="text"
              placeholder="例如：custom_field_1"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              required
            />
            <p class="text-xs text-gray-500 mt-1">請使用英文字母、數字和底線，系統內部識別用</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">欄位名稱</label>
            <input 
              v-model="newSystemField.label" 
              type="text"
              placeholder="例如：客戶職業"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              required
            />
            <p class="text-xs text-gray-500 mt-1">在欄位選擇時顯示的名稱</p>
          </div>


          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">描述（選填）</label>
            <textarea 
              v-model="newSystemField.description" 
              rows="3"
              placeholder="說明此欄位的用途"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            ></textarea>
          </div>


          <div class="flex justify-end pt-4 border-t space-x-3">
            <button 
              @click="closeAddSystemFieldModal" 
              type="button"
              class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
            >
              取消
            </button>
            <button 
              type="submit"
              :disabled="savingSystemField || !newSystemField.key || !newSystemField.label"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ savingSystemField ? '新增中...' : '新增欄位' }}
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
import { PlusIcon, PencilIcon, TrashIcon, AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline'

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

// Point 61: Field Mapping data
const fieldMappingModalOpen = ref(false)
const currentWebsite = ref(null)
const fieldMappings = ref([])
const systemFields = ref({})
const fieldTypes = ref({})
const loadingFieldMappings = ref(false)
const savingFieldMappings = ref(false)

// Point 62: Add System Field data
const addSystemFieldModalOpen = ref(false)
const savingSystemField = ref(false)
const newSystemField = ref({
  key: '',
  label: '',
  description: ''
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

// Point 61: Computed properties for field mappings
const isValidMappings = computed(() => {
  if (fieldMappings.value.length === 0) return false
  
  return fieldMappings.value.every(mapping => 
    mapping.system_field && 
    mapping.wp_field_name
  )
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

// Point 61: Field Mapping Methods
const openFieldMappingModal = async (website) => {
  currentWebsite.value = website
  fieldMappingModalOpen.value = true
  await loadFieldMappings(website.id)
}

const closeFieldMappingModal = () => {
  fieldMappingModalOpen.value = false
  currentWebsite.value = null
  fieldMappings.value = []
}

const loadFieldMappings = async (websiteId) => {
  loadingFieldMappings.value = true
  try {
    const { data, error } = await get(`/websites/${websiteId}/field-mappings`)
    
    if (error) {
      console.error('載入欄位對應失敗:', error)
      useToast().add({
        title: '載入失敗',
        description: error.message || '無法載入欄位對應設定',
        color: 'red'
      })
      return
    }
    
    fieldMappings.value = data.data || []
    systemFields.value = data.system_fields || {}
    fieldTypes.value = data.field_types || {}
    
  } catch (err) {
    console.error('載入欄位對應失敗:', err)
    useToast().add({
      title: '載入失敗',
      description: '無法載入欄位對應設定',
      color: 'red'
    })
  } finally {
    loadingFieldMappings.value = false
  }
}

const createDefaultMappings = async () => {
  if (!currentWebsite.value) return
  
  try {
    const { data, error } = await post(`/websites/${currentWebsite.value.id}/field-mappings/defaults`)
    
    if (error) {
      console.error('建立預設對應失敗:', error)
      useToast().add({
        title: '建立失敗',
        description: error.message || '無法建立預設欄位對應',
        color: 'red'
      })
      return
    }
    
    fieldMappings.value = data.data || []
    
    useToast().add({
      title: '建立成功',
      description: '預設欄位對應已建立',
      color: 'green'
    })
    
  } catch (err) {
    console.error('建立預設對應失敗:', err)
    useToast().add({
      title: '建立失敗',
      description: '無法建立預設欄位對應',
      color: 'red'
    })
  }
}

const addFieldMapping = () => {
  fieldMappings.value.push({
    system_field: '',
    wp_field_name: '',
    display_name: '', // 會自動同步wp_field_name的值
    sort_order: fieldMappings.value.length * 10
  })
}

const removeFieldMapping = (index) => {
  fieldMappings.value.splice(index, 1)
}

const saveFieldMappings = async () => {
  if (!currentWebsite.value || !isValidMappings.value) return
  
  savingFieldMappings.value = true
  try {
    const { data, error } = await post(`/websites/${currentWebsite.value.id}/field-mappings`, {
      mappings: fieldMappings.value
    })
    
    if (error) {
      console.error('儲存欄位對應失敗:', error)
      useToast().add({
        title: '儲存失敗',
        description: error.message || '欄位對應設定儲存失敗',
        color: 'red'
      })
      return
    }
    
    useToast().add({
      title: '儲存成功',
      description: '欄位對應設定已更新',
      color: 'green'
    })
    
    closeFieldMappingModal()
    
  } catch (err) {
    console.error('儲存欄位對應失敗:', err)
    useToast().add({
      title: '儲存失敗',
      description: '欄位對應設定儲存失敗',
      color: 'red'
    })
  } finally {
    savingFieldMappings.value = false
  }
}

const testFieldMappings = async () => {
  if (!currentWebsite.value) return
  
  // 建立測試資料
  const testData = {
    '姓名': '測試客戶',
    '手機號碼': '0912345678',
    'Email': 'test@example.com',
    'LINE_ID': 'test_line_id',
    '方便聯絡時間': '上午9:00-12:00',
    '資金需求': '50萬以下',
    '頁面 URL': 'https://example.com/contact/'
  }
  
  try {
    const { data, error } = await post(`/websites/${currentWebsite.value.id}/field-mappings/test`, {
      test_data: testData
    })
    
    if (error) {
      console.error('測試欄位對應失敗:', error)
      useToast().add({
        title: '測試失敗',
        description: error.message || '欄位對應測試失敗',
        color: 'red'
      })
      return
    }
    
    // 顯示測試結果
    const message = `測試完成！對應了 ${data.mapped_fields_count} 個欄位，${data.unmapped_fields_count} 個欄位未對應`
    
    useToast().add({
      title: '測試完成',
      description: message,
      color: 'green'
    })
    
    console.log('測試結果:', data)
    
  } catch (err) {
    console.error('測試欄位對應失敗:', err)
    useToast().add({
      title: '測試失敗',
      description: '欄位對應測試失敗',
      color: 'red'
    })
  }
}

// Point 62: Add System Field Methods
const openAddSystemFieldModal = () => {
  // 重置表單
  newSystemField.value = {
    key: '',
    label: '',
    description: ''
  }
  addSystemFieldModalOpen.value = true
}

const closeAddSystemFieldModal = () => {
  addSystemFieldModalOpen.value = false
}

const addCustomSystemField = async () => {
  if (!newSystemField.value.key || !newSystemField.value.label) return
  
  savingSystemField.value = true
  try {
    const { data, error } = await post('/field-mappings/system-fields', newSystemField.value)
    
    if (error) {
      console.error('新增系統欄位失敗:', error)
      useToast().add({
        title: '新增失敗',
        description: error.message || '系統欄位新增失敗',
        color: 'red'
      })
      return
    }
    
    // 更新系統欄位清單
    systemFields.value[newSystemField.value.key] = {
      label: newSystemField.value.label,
      description: newSystemField.value.description
    }
    
    useToast().add({
      title: '新增成功',
      description: `系統欄位「${newSystemField.value.label}」已新增`,
      color: 'green'
    })
    
    closeAddSystemFieldModal()
    
  } catch (err) {
    console.error('新增系統欄位失敗:', err)
    useToast().add({
      title: '新增失敗',
      description: '系統欄位新增失敗',
      color: 'red'
    })
  } finally {
    savingSystemField.value = false
  }
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