<template>
  <div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg-custom shadow-sm p-6">
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
        主題設定
      </h2>
      
      <!-- Theme Mode -->
      <div class="mb-8">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          顯示模式
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <button
            v-for="mode in themeModes"
            :key="mode.value"
            @click="setThemeMode(mode.value)"
            class="p-4 border rounded-lg transition-all duration-200"
            :class="[
              colorMode.preference === mode.value
                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
            ]"
          >
            <component :is="mode.icon" class="w-6 h-6 mx-auto mb-2" />
            <p class="text-sm font-medium">{{ mode.label }}</p>
          </button>
        </div>
      </div>

      <!-- Primary Color -->
      <div class="mb-8">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          主要顏色
        </h3>
        <div class="grid grid-cols-6 sm:grid-cols-8 gap-3">
          <button
            v-for="color in primaryColors"
            :key="color.name"
            @click="setPrimaryColor(color.value)"
            class="w-12 h-12 rounded-lg border-2 transition-transform duration-200 hover:scale-110"
            :style="{ backgroundColor: color.value }"
            :class="[
              themeStore.primaryColor === color.value
                ? 'border-gray-900 dark:border-white'
                : 'border-gray-300 dark:border-gray-600'
            ]"
            :title="color.name"
          />
        </div>
      </div>

      <!-- Custom Color -->
      <div class="mb-8">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          自定義顏色
        </h3>
        <div class="flex items-center space-x-4">
          <input
            v-model="customColor"
            type="color"
            class="w-12 h-12 rounded-lg border border-gray-300 dark:border-gray-600"
          />
          <button
            @click="setPrimaryColor(customColor)"
            class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors duration-200"
          >
            應用顏色
          </button>
        </div>
      </div>

      <!-- Preview -->
      <div class="mb-8">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          預覽
        </h3>
        <div class="p-6 border border-gray-200 dark:border-gray-700 rounded-lg">
          <div class="flex items-center space-x-4 mb-4">
            <button class="px-4 py-2 bg-primary-500 text-white rounded-lg">
              主要按鈕
            </button>
            <button class="px-4 py-2 border border-primary-500 text-primary-500 rounded-lg">
              次要按鈕
            </button>
          </div>
          <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
            <div 
              class="h-2 rounded-full"
              :style="{ backgroundColor: themeStore.primaryColor, width: '60%' }"
            ></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  SunIcon,
  MoonIcon,
  ComputerDesktopIcon
} from '@heroicons/vue/24/outline'

const colorMode = useColorMode()
const themeStore = useThemeStore()

const customColor = ref('#6366f1')

const themeModes = [
  { value: 'light', label: '淺色模式', icon: SunIcon },
  { value: 'dark', label: '深色模式', icon: MoonIcon },
  { value: 'system', label: '跟隨系統', icon: ComputerDesktopIcon }
]

const primaryColors = [
  { name: 'Indigo', value: '#6366f1' },
  { name: 'Blue', value: '#3b82f6' },
  { name: 'Purple', value: '#8b5cf6' },
  { name: 'Pink', value: '#ec4899' },
  { name: 'Red', value: '#ef4444' },
  { name: 'Orange', value: '#f97316' },
  { name: 'Yellow', value: '#eab308' },
  { name: 'Green', value: '#22c55e' },
  { name: 'Emerald', value: '#10b981' },
  { name: 'Teal', value: '#14b8a6' },
  { name: 'Cyan', value: '#06b6d4' },
  { name: 'Gray', value: '#6b7280' }
]

const setThemeMode = (mode) => {
  colorMode.preference = mode
}

const setPrimaryColor = (color) => {
  themeStore.setPrimaryColor(color)
}
</script>