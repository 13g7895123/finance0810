<template>
  <div class="relative">
    <!-- 使用 NuxtLink 包裝所有帶 href 的項目 -->
    <NuxtLink
      v-if="item.href"
      :to="item.href"
      class="w-full flex items-center px-3 py-2 text-white hover:bg-gray-700 hover:text-white rounded-lg transition-all duration-200 group"
      :class="{ 'justify-center': collapsed }"
      @click="handleNavigation"
    >
      <!-- Icon -->
      <component 
        v-if="getIcon(item.icon) && typeof getIcon(item.icon) === 'function'"
        :is="getIcon(item.icon)" 
        class="w-5 h-5 text-white group-hover:text-white transition-colors duration-200" 
      />
      <ChartBarIcon v-else class="w-5 h-5 text-white group-hover:text-white transition-colors duration-200" />
      
      <!-- Text (desktop) -->
      <div v-if="!collapsed" class="flex items-center justify-between flex-1 ml-3">
        <div class="flex items-center space-x-2">
          <span class="font-medium">{{ item.name }}</span>
          <!-- Badge -->
          <span 
            v-if="badge > 0" 
            :class="['inline-flex items-center justify-center w-5 h-5 text-xs font-bold rounded-full', badgeClasses]"
          >
            {{ badge > 99 ? '99+' : badge }}
          </span>
        </div>
      </div>
    </NuxtLink>
    
    <!-- 只有子項目才使用 button -->
    <button
      v-else
      @click="toggleItem"
      class="w-full flex items-center px-3 py-2 text-white hover:bg-gray-700 hover:text-white rounded-lg transition-all duration-200 group"
      :class="{ 'justify-center': collapsed }"
    >
      <!-- Icon -->
      <component 
        v-if="getIcon(item.icon) && typeof getIcon(item.icon) === 'function'"
        :is="getIcon(item.icon)" 
        class="w-5 h-5 text-white group-hover:text-white transition-colors duration-200" 
      />
      <ChartBarIcon v-else class="w-5 h-5 text-white group-hover:text-white transition-colors duration-200" />
      
      <!-- Text and Arrow (desktop) -->
      <div v-if="!collapsed" class="flex items-center justify-between flex-1 ml-3">
        <div class="flex items-center space-x-2">
          <span class="font-medium">{{ item.name }}</span>
          <!-- Badge -->
          <span 
            v-if="badge > 0" 
            :class="['inline-flex items-center justify-center w-5 h-5 text-xs font-bold rounded-full', badgeClasses]"
          >
            {{ badge > 99 ? '99+' : badge }}
          </span>
        </div>
        <ChevronDownIcon 
          v-if="item.children"
          class="w-4 h-4 transition-transform duration-200"
          :class="{ 'rotate-180': isExpanded }"
        />
      </div>
    </button>

    <!-- Tooltip for collapsed state -->
    <div
      v-if="collapsed"
      class="absolute left-full top-0 ml-2 px-3 py-2 bg-gray-900 text-white text-sm rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-50 whitespace-nowrap"
    >
      <div class="flex items-center space-x-2">
        <span>{{ item.name }}</span>
        <span
          v-if="badge > 0"
          :class="['inline-flex items-center justify-center w-4 h-4 text-xs font-bold rounded-full', badgeClasses]"
        >
          {{ badge > 99 ? '99+' : badge }}
        </span>
      </div>
    </div>

    <!-- Submenu -->
    <transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 max-h-0"
      enter-to-class="opacity-100 max-h-96"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="opacity-100 max-h-96"
      leave-to-class="opacity-0 max-h-0"
    >
      <div v-if="isExpanded && !collapsed && item.children" class="ml-8 mt-2 space-y-1 overflow-hidden">
        <NuxtLink
          v-for="child in item.children"
          :key="child.name"
          :to="child.href"
          class="flex items-center justify-between px-3 py-2 text-sm text-white opacity-80 hover:text-white hover:opacity-100 hover:bg-gray-700 rounded-lg transition-all duration-200"
        >
          <span>{{ child.name }}</span>
          <span
            v-if="getChildBadgeCount(child) > 0"
            :class="['inline-flex items-center justify-center w-5 h-5 text-xs font-bold rounded-full ml-2', getChildBadgeClasses(child)]"
          >
            {{ getChildBadgeCount(child) > 99 ? '99+' : getChildBadgeCount(child) }}
          </span>
        </NuxtLink>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { 
  ChartBarIcon,
  CogIcon,
  QuestionMarkCircleIcon,
  ChevronDownIcon,
  DocumentTextIcon,
  UserGroupIcon,
  ChatBubbleLeftRightIcon,
  BuildingLibraryIcon,
  ChartPieIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  collapsed: {
    type: Boolean,
    default: false
  },
  badge: {
    type: Number,
    default: 0
  },
  badgeColor: {
    type: String,
    default: 'red',
    validator: (value) => ['red', 'green', 'blue', 'yellow', 'purple'].includes(value)
  },
  getBadgeCount: {
    type: Function,
    default: () => () => 0
  },
  getBadgeColor: {
    type: Function,
    default: () => () => 'red'
  }
})

const isExpanded = ref(false)

const toggleItem = () => {
  if (props.item.children && !props.collapsed) {
    isExpanded.value = !isExpanded.value
  } else if (props.item.children && props.collapsed) {
    // For collapsed state, we could show a popover menu here in the future
  }
}

const handleNavigation = () => {
  // 確保導航正常執行，可以在這裡添加額外的邏輯
  console.log('Navigating to:', props.item.href)
}

const iconComponents = {
  ChartBarIcon,
  CogIcon,
  QuestionMarkCircleIcon,
  DocumentTextIcon,
  UserGroupIcon,
  ChatBubbleLeftRightIcon,
  BuildingLibraryIcon,
  ChartPieIcon
}

const getIcon = (iconName) => {
  return iconComponents[iconName] || ChartBarIcon
}

// Computed property for badge colors
const badgeClasses = computed(() => {
  const colorClasses = {
    red: 'bg-red-500 text-white',
    green: 'bg-green-500 text-white',
    blue: 'bg-blue-500 text-white',
    yellow: 'bg-yellow-500 text-white',
    purple: 'bg-purple-500 text-white'
  }
  return colorClasses[props.badgeColor] || colorClasses.red
})

// Child badge functions
const getChildBadgeCount = (child) => {
  return props.getBadgeCount(child)
}

const getChildBadgeClasses = (child) => {
  const childColor = props.getBadgeColor(child)
  const colorClasses = {
    red: 'bg-red-500 text-white',
    green: 'bg-green-500 text-white',
    blue: 'bg-blue-500 text-white',
    yellow: 'bg-yellow-500 text-white',
    purple: 'bg-purple-500 text-white'
  }
  return colorClasses[childColor] || colorClasses.red
}
</script>