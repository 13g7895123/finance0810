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
        <span class="font-medium">{{ item.name }}</span>
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
        <span class="font-medium">{{ item.name }}</span>
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
      {{ item.name }}
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
          class="block px-3 py-2 text-sm text-white opacity-80 hover:text-white hover:opacity-100 hover:bg-gray-700 rounded-lg transition-all duration-200"
        >
          {{ child.name }}
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
</script>