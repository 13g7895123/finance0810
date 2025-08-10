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
    
    <!-- 調試組件已移除 -->
  </div>
</template>

<script setup>
const sidebarStore = useSidebarStore()
const { sidebarCollapsed } = storeToRefs(sidebarStore)

// Add footbar state from settings
const settingsStore = useSettingsStore()
const { showFootbar } = storeToRefs(settingsStore)
</script>