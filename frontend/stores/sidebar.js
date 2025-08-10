export const useSidebarStore = defineStore('sidebar', () => {
  const collapsed = ref(false)
  const mobileOpen = ref(false)
  
  const toggleSidebar = () => {
    collapsed.value = !collapsed.value
  }
  
  const toggleMobileSidebar = () => {
    mobileOpen.value = !mobileOpen.value
  }
  
  const closeMobileSidebar = () => {
    mobileOpen.value = false
  }
  
  return {
    sidebarCollapsed: collapsed,
    sidebarMobileOpen: mobileOpen,
    toggleSidebar,
    toggleMobileSidebar,
    closeMobileSidebar
  }
})