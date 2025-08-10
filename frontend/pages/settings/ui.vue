<template>
  <div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg-custom shadow-sm p-6">
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
        介面設定
      </h2>
      
      <!-- Footbar Setting -->
      <div class="mb-8">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          頁尾設定
        </h3>
        <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
          <div>
            <h4 class="font-medium text-gray-900 dark:text-white">顯示頁尾</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">在頁面底部顯示頁尾資訊</p>
          </div>
          <button
            @click="toggleFootbar"
            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            :class="[
              showFootbar 
                ? 'bg-primary-500' 
                : 'bg-gray-200 dark:bg-gray-700'
            ]"
          >
            <span
              class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
              :class="[
                showFootbar ? 'translate-x-6' : 'translate-x-1'
              ]"
            />
          </button>
        </div>
      </div>

      <!-- Sidebar Menu Configuration -->
      <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            側邊選單設定
          </h3>
          <button
            @click="addMenuItem"
            class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors duration-200"
          >
            新增選單項目
          </button>
        </div>
        
        <div class="space-y-4">
          <div
            v-for="(item, index) in localMenuItems"
            :key="index"
            class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
          >
            <div class="flex items-center justify-between mb-3">
              <input
                v-model="item.name"
                type="text"
                placeholder="選單名稱"
                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent dark:bg-gray-700 dark:text-white mr-3"
              />
              <select
                v-model="item.icon"
                class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent dark:bg-gray-700 dark:text-white mr-3"
              >
                <option value="ChartBarIcon">圖表</option>
                <option value="CogIcon">設定</option>
                <option value="QuestionMarkCircleIcon">幫助</option>
                <option value="UsersIcon">用戶</option>
                <option value="DocumentIcon">文件</option>
              </select>
              <button
                @click="removeMenuItem(index)"
                class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors duration-200"
              >
                <TrashIcon class="w-5 h-5" />
              </button>
            </div>
            
            <!-- Children Items -->
            <div class="ml-4 space-y-2">
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">子選單項目</span>
                <button
                  @click="addChildItem(index)"
                  class="text-sm text-primary-500 hover:text-primary-600 transition-colors duration-200"
                >
                  + 新增子項目
                </button>
              </div>
              <div
                v-for="(child, childIndex) in item.children"
                :key="childIndex"
                class="flex items-center space-x-2"
              >
                <input
                  v-model="child.name"
                  type="text"
                  placeholder="子選單名稱"
                  class="flex-1 px-3 py-1 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-primary-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                />
                <input
                  v-model="child.href"
                  type="text"
                  placeholder="/path"
                  class="flex-1 px-3 py-1 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-primary-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                />
                <button
                  @click="removeChildItem(index, childIndex)"
                  class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors duration-200"
                >
                  <XMarkIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <div class="flex justify-end space-x-3 mt-6">
          <button
            @click="resetMenuItems"
            class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
          >
            重置為預設
          </button>
          <button
            @click="saveMenuItems"
            class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors duration-200"
          >
            儲存設定
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const settingsStore = useSettingsStore()
const { showFootbar, sidebarMenuItems } = storeToRefs(settingsStore)
const { toggleFootbar, updateMenuItems } = settingsStore

// Local copy for editing
const localMenuItems = ref(JSON.parse(JSON.stringify(sidebarMenuItems.value)))

const addMenuItem = () => {
  localMenuItems.value.push({
    name: '新選單項目',
    icon: 'ChartBarIcon',
    children: [
      { name: '子項目 1', href: '/new-item' }
    ]
  })
}

const removeMenuItem = (index) => {
  localMenuItems.value.splice(index, 1)
}

const addChildItem = (parentIndex) => {
  localMenuItems.value[parentIndex].children.push({
    name: '新子項目',
    href: '/new-child'
  })
}

const removeChildItem = (parentIndex, childIndex) => {
  localMenuItems.value[parentIndex].children.splice(childIndex, 1)
}

const saveMenuItems = () => {
  updateMenuItems(localMenuItems.value)
  // Show success message
  console.log('選單設定已儲存')
}

const resetMenuItems = () => {
  const defaultItems = [
    {
      name: 'Dashboards',
      icon: 'ChartBarIcon',
      children: [
        { name: '分析概覽', href: '/dashboard/analytics' },
        { name: 'CRM', href: '/dashboard/crm' },
        { name: 'eCommerce', href: '/dashboard/ecommerce' }
      ]
    },
    {
      name: 'Settings',
      icon: 'CogIcon',
      children: [
        { name: '一般設定', href: '/settings/general' },
        { name: '主題設定', href: '/settings/theme' },
        { name: '介面設定', href: '/settings/ui' },
        { name: '用戶管理', href: '/settings/users' }
      ]
    },
    {
      name: 'Help Center',
      icon: 'QuestionMarkCircleIcon',
      children: [
        { name: 'FAQ', href: '/help/faq' },
        { name: '聯絡支援', href: '/help/support' },
        { name: '文件', href: '/help/docs' }
      ]
    }
  ]
  
  localMenuItems.value = JSON.parse(JSON.stringify(defaultItems))
  updateMenuItems(defaultItems)
}
</script>