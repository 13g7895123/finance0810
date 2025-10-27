<template>
  <div class="min-h-screen bg-white">
    <AppSidebar />
    <div
      class="min-h-screen flex flex-col main-content-area light-gradient-bg"
      :class="{
        'sidebar-collapsed': sidebarCollapsed
      }"
    >
      <AppNavbar />
      <main class="flex-1 p-6 overflow-auto">
        <slot />
      </main>
      <!-- Footer已隱藏 -->
    </div>

    <!-- Point 3: Notification toast component -->
    <NotificationToast
      :notification="notificationsStore.currentToast"
      @close="notificationsStore.closeToast"
    />
  </div>
</template>

<script setup>
import { watch } from 'vue'

const sidebarStore = useSidebarStore()
const { sidebarCollapsed } = storeToRefs(sidebarStore)

// Add footbar state from settings
const settingsStore = useSettingsStore()
const { showFootbar } = storeToRefs(settingsStore)

// Point 3: Notification store for toast
const notificationsStore = useNotificationsStore()
const authStore = useAuthStore()

// Point 3 Fix: Start polling for notifications only after authentication
onMounted(() => {
  // Only start polling if user is authenticated
  if (authStore.isLoggedIn) {
    console.log('Point 3 - Starting notifications polling from default layout (user authenticated)')
    notificationsStore.startPolling()
  } else {
    console.log('Point 3 - Skipping notifications polling (user not authenticated)')
  }
})

// Watch for authentication changes
watch(() => authStore.isLoggedIn, (isLoggedIn) => {
  if (isLoggedIn) {
    console.log('Point 3 - User authenticated, starting notifications polling')
    notificationsStore.startPolling()
  } else {
    console.log('Point 3 - User logged out, stopping notifications polling')
    notificationsStore.stopPolling()
  }
})

onUnmounted(() => {
  notificationsStore.stopPolling()
})
</script>