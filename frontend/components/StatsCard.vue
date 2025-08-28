<template>
  <div class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow duration-200">
    <!-- 標題和圖示 -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center space-x-3">
        <div 
          class="p-3 rounded-lg"
          :class="iconBgClass"
        >
          <component 
            v-if="getIcon(icon) && typeof getIcon(icon) === 'function'"
            :is="getIcon(icon)" 
            class="w-6 h-6"
            :class="iconClass"
          />
          <ChartBarIcon v-else class="w-6 h-6" :class="iconClass" />
        </div>
        <div>
          <h3 class="text-lg font-semibold text-gray-900 ">{{ title }}</h3>
          <p class="text-sm text-gray-500 ">{{ subtitle }}</p>
        </div>
      </div>
      
      <!-- 趨勢指示器 -->
      <div v-if="trend" class="flex items-center space-x-1">
        <component 
          v-if="getTrendIcon() && typeof getTrendIcon() === 'function'"
          :is="getTrendIcon()" 
          class="w-4 h-4"
          :class="getTrendClass()"
        />
        <MinusIcon v-else class="w-4 h-4" :class="getTrendClass()" />
        <span 
          class="text-sm font-medium"
          :class="getTrendClass()"
        >
          {{ Math.abs(trend) }}%
        </span>
      </div>
    </div>

    <!-- 主要數據 -->
    <div class="mb-3">
      <div class="text-3xl font-bold text-gray-900 mb-1">
        {{ formattedValue }}
      </div>
      <div class="text-sm text-gray-500 ">
        {{ description }}
      </div>
    </div>

    <!-- 進度條 (可選) -->
    <div v-if="progress !== undefined" class="mb-4">
      <div class="flex justify-between items-center mb-2">
        <span class="text-sm text-gray-600 ">完成度</span>
        <span class="text-sm font-medium text-gray-900 ">{{ progress }}%</span>
      </div>
      <div class="w-full bg-gray-200 rounded-full h-2">
        <div 
          class="h-2 rounded-full transition-all duration-500"
          :class="getProgressClass()"
          :style="{ width: `${Math.min(progress, 100)}%` }"
        ></div>
      </div>
    </div>

    <!-- 額外資訊或行動按鈕 -->
    <div v-if="$slots.footer" class="pt-4 mt-4">
      <slot name="footer"></slot>
    </div>
    
    <!-- 預設的查看詳情連結 -->
    <div v-else-if="link" class="pt-4 mt-4">
      <NuxtLink 
        :to="link"
        class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center space-x-1"
      >
        <span>查看詳情</span>
        <ArrowRightIcon class="w-4 h-4" />
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
import { 
  ArrowRightIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  MinusIcon,
  ChartBarIcon,
  CurrencyDollarIcon,
  UserGroupIcon,
  DocumentTextIcon,
  ClockIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ChatBubbleLeftRightIcon,
  PaperAirplaneIcon,
  UsersIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  subtitle: {
    type: String,
    default: ''
  },
  value: {
    type: [Number, String],
    required: true
  },
  description: {
    type: String,
    default: ''
  },
  icon: {
    type: String,
    default: 'ChartBarIcon'
  },
  iconColor: {
    type: String,
    default: 'blue',
    validator: (value) => ['blue', 'green', 'yellow', 'red', 'purple', 'indigo', 'pink'].includes(value)
  },
  trend: {
    type: Number,
    default: null
  },
  progress: {
    type: Number,
    default: undefined
  },
  link: {
    type: String,
    default: null
  },
  format: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'currency', 'percentage', 'number'].includes(value)
  }
})

// 圖示組件映射
const iconComponents = {
  ChartBarIcon,
  CurrencyDollarIcon,
  UserGroupIcon,
  DocumentTextIcon,
  ClockIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ChatBubbleLeftRightIcon,
  PaperAirplaneIcon,
  UsersIcon
}

// 獲取圖示組件
const getIcon = (iconName) => {
  return iconComponents[iconName] || ChartBarIcon
}

// 圖示背景顏色類別
const iconBgClass = computed(() => {
  const colorMap = {
    blue: 'bg-blue-100',
    green: 'bg-green-100',
    yellow: 'bg-yellow-100',
    red: 'bg-red-100',
    purple: 'bg-purple-100',
    indigo: 'bg-indigo-100',
    pink: 'bg-pink-100'
  }
  return colorMap[props.iconColor]
})

// 圖示顏色類別
const iconClass = computed(() => {
  const colorMap = {
    blue: 'text-blue-600',
    green: 'text-green-600',
    yellow: 'text-yellow-600',
    red: 'text-red-600',
    purple: 'text-purple-600',
    indigo: 'text-indigo-600',
    pink: 'text-pink-600'
  }
  return colorMap[props.iconColor]
})

// 格式化數值
const formattedValue = computed(() => {
  const value = props.value
  
  // 如果值為 undefined 或 null，返回預設值
  if (value === undefined || value === null) {
    return '0'
  }
  
  switch (props.format) {
    case 'currency':
      return new Intl.NumberFormat('zh-TW', {
        style: 'currency',
        currency: 'TWD',
        minimumFractionDigits: 0
      }).format(Number(value) || 0)
    
    case 'percentage':
      return `${value}%`
    
    case 'number':
      return new Intl.NumberFormat('zh-TW').format(Number(value) || 0)
    
    default:
      return value.toString()
  }
})

// 獲取趨勢圖示
const getTrendIcon = () => {
  if (!props.trend) return MinusIcon
  if (props.trend > 0) return ArrowUpIcon
  if (props.trend < 0) return ArrowDownIcon
  return MinusIcon
}

// 獲取趨勢顏色類別
const getTrendClass = () => {
  if (!props.trend) return 'text-gray-500'
  if (props.trend > 0) return 'text-green-600'
  if (props.trend < 0) return 'text-red-600'
  return 'text-gray-500'
}

// 獲取進度條顏色類別
const getProgressClass = () => {
  if (props.progress >= 80) return 'bg-green-500'
  if (props.progress >= 60) return 'bg-blue-500'
  if (props.progress >= 40) return 'bg-yellow-500'
  return 'bg-red-500'
}
</script>