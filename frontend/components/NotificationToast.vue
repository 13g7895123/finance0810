<template>
  <!-- Point 3: Toast notification component for new WP leads -->
  <Teleport to="body">
    <Transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show && notification"
        class="fixed top-20 right-6 z-50 w-full max-w-sm"
      >
        <div class="bg-white rounded-lg shadow-2xl border border-gray-200 overflow-hidden">
          <!-- Header with close button -->
          <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600">
            <div class="flex items-center space-x-2">
              <BellIcon class="w-5 h-5 text-white" />
              <h3 class="text-white font-semibold">{{ notification.title || '新通知' }}</h3>
            </div>
            <button
              @click="close"
              class="text-white hover:text-gray-200 transition-colors"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Content -->
          <div class="p-4">
            <p class="text-gray-700 text-sm leading-relaxed">
              {{ notification.message }}
            </p>

            <!-- Additional data if available -->
            <div v-if="notification.data" class="mt-3 pt-3 border-t border-gray-200">
              <div class="grid grid-cols-2 gap-2 text-xs">
                <div v-if="notification.data.customer_name">
                  <span class="text-gray-500">客戶:</span>
                  <span class="ml-1 text-gray-900 font-medium">{{ notification.data.customer_name }}</span>
                </div>
                <div v-if="notification.data.customer_phone">
                  <span class="text-gray-500">電話:</span>
                  <span class="ml-1 text-gray-900 font-medium">{{ notification.data.customer_phone }}</span>
                </div>
                <div v-if="notification.data.website_domain" class="col-span-2">
                  <span class="text-gray-500">網站:</span>
                  <span class="ml-1 text-gray-900 font-medium">{{ notification.data.website_domain }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="px-4 py-3 bg-gray-50 flex items-center justify-end space-x-2">
            <button
              @click="close"
              class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 transition-colors"
            >
              稍後處理
            </button>
            <button
              v-if="notification.lead_id"
              @click="viewLead"
              class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors font-medium"
            >
              查看案件
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { BellIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  notification: {
    type: Object,
    default: null
  },
  duration: {
    type: Number,
    default: 8000 // 8 seconds
  }
})

const emit = defineEmits(['close'])

const show = ref(false)
let autoCloseTimer = null

// Watch for new notification
watch(() => props.notification, (newNotification) => {
  if (newNotification) {
    show.value = true

    // Clear existing timer
    if (autoCloseTimer) {
      clearTimeout(autoCloseTimer)
    }

    // Auto close after duration
    autoCloseTimer = setTimeout(() => {
      close()
    }, props.duration)

    // Play notification sound (optional)
    playNotificationSound()
  }
}, { immediate: true })

const close = () => {
  show.value = false
  if (autoCloseTimer) {
    clearTimeout(autoCloseTimer)
  }
  emit('close')
}

const viewLead = () => {
  if (props.notification?.lead_id) {
    navigateTo(`/cases/pending?lead=${props.notification.lead_id}`)
  }
  close()
}

const playNotificationSound = () => {
  // Optional: Play a subtle notification sound
  // You can add an audio file to public folder and play it
  try {
    const audio = new Audio('/notification.mp3')
    audio.volume = 0.3
    audio.play().catch(() => {
      // Ignore errors (user might not have interacted with page yet)
    })
  } catch (error) {
    // Ignore audio errors
  }
}

// Cleanup on unmount
onUnmounted(() => {
  if (autoCloseTimer) {
    clearTimeout(autoCloseTimer)
  }
})
</script>
