<template>
  <div class="flex items-center space-x-2 justify-end">
    <!-- View/查看 -->
    <button 
      v-if="showView"
      @click="$emit('view', item)"
      class="group relative inline-flex items-center justify-center p-2 text-blue-600 hover:text-white hover:bg-blue-600 rounded-lg transition-all duration-200"
      :title="viewText"
    >
      <EyeIcon class="w-4 h-4" />
      <!-- Tooltip -->
      <div class="absolute bottom-full mb-2 hidden group-hover:block px-2 py-1 text-xs text-white bg-gray-900 rounded whitespace-nowrap z-10">
        {{ viewText }}
      </div>
    </button>
    
    <!-- Edit/編輯 -->
    <button 
      v-if="showEdit"
      @click="$emit('edit', item)"
      class="group relative inline-flex items-center justify-center p-2 text-gray-600 hover:text-white hover:bg-gray-600 rounded-lg transition-all duration-200"
      :title="editText"
    >
      <PencilIcon class="w-4 h-4" />
      <!-- Tooltip -->
      <div class="absolute bottom-full mb-2 hidden group-hover:block px-2 py-1 text-xs text-white bg-gray-900 rounded whitespace-nowrap z-10">
        {{ editText }}
      </div>
    </button>
    
    <!-- Assign/指派 -->
    <button 
      v-if="showAssign"
      @click="$emit('assign', item)"
      class="group relative inline-flex items-center justify-center p-2 text-green-600 hover:text-white hover:bg-green-600 rounded-lg transition-all duration-200"
      :title="assignText"
    >
      <UserIcon class="w-4 h-4" />
      <!-- Tooltip -->
      <div class="absolute bottom-full mb-2 hidden group-hover:block px-2 py-1 text-xs text-white bg-gray-900 rounded whitespace-nowrap z-10">
        {{ assignText }}
      </div>
    </button>
    
    <!-- Convert/轉送件 -->
    <button 
      v-if="showConvert"
      @click="$emit('convert', item)"
      class="group relative inline-flex items-center justify-center p-2 text-purple-600 hover:text-white hover:bg-purple-600 rounded-lg transition-all duration-200"
      :title="convertText"
    >
      <ArrowRightIcon class="w-4 h-4" />
      <!-- Tooltip -->
      <div class="absolute bottom-full mb-2 hidden group-hover:block px-2 py-1 text-xs text-white bg-gray-900 rounded whitespace-nowrap z-10">
        {{ convertText }}
      </div>
    </button>
    
    <!-- Delete/刪除 -->
    <button 
      v-if="showDelete"
      @click="$emit('delete', item)"
      class="group relative inline-flex items-center justify-center p-2 text-red-600 hover:text-white hover:bg-red-600 rounded-lg transition-all duration-200"
      :title="deleteText"
    >
      <TrashIcon class="w-4 h-4" />
      <!-- Tooltip -->
      <div class="absolute bottom-full mb-2 hidden group-hover:block px-2 py-1 text-xs text-white bg-gray-900 rounded whitespace-nowrap z-10">
        {{ deleteText }}
      </div>
    </button>
    
    <!-- Custom Actions Slot -->
    <slot name="custom-actions" :item="item"></slot>
  </div>
</template>

<script setup>
import {
  EyeIcon,
  PencilIcon,
  UserIcon,
  ArrowRightIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'

defineProps({
  item: {
    type: Object,
    required: true
  },
  showView: {
    type: Boolean,
    default: true
  },
  showEdit: {
    type: Boolean,
    default: true
  },
  showAssign: {
    type: Boolean,
    default: false
  },
  showConvert: {
    type: Boolean,
    default: false
  },
  showDelete: {
    type: Boolean,
    default: true
  },
  viewText: {
    type: String,
    default: '查看'
  },
  editText: {
    type: String,
    default: '編輯'
  },
  assignText: {
    type: String,
    default: '指派'
  },
  convertText: {
    type: String,
    default: '轉送件'
  },
  deleteText: {
    type: String,
    default: '刪除'
  }
})

defineEmits([
  'view',
  'edit',
  'assign', 
  'convert',
  'delete'
])
</script>

<style scoped>
/* Ensure tooltips appear above everything */
.group:hover .group-hover\\:block {
  z-index: 50;
}
</style>