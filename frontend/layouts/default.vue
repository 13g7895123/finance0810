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
const sidebarStore = useSidebarStore()
const { sidebarCollapsed } = storeToRefs(sidebarStore)

// Add footbar state from settings
const settingsStore = useSettingsStore()
const { showFootbar } = storeToRefs(settingsStore)

// Point 3: Notification store for toast
const notificationsStore = useNotificationsStore()

// Point 3 Fix: Start polling for notifications when layout mounts
onMounted(() => {
  console.log('Point 3 - Starting notifications polling from default layout')
  notificationsStore.startPolling()
})

onUnmounted(() => {
  notificationsStore.stopPolling()
})
</script>