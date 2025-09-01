export const useSidebarBadges = () => {
  const { get } = useApi()
  
  // Reactive state for badges
  const badges = ref({
    pending: 0,
    intake: 0,
    disbursed: 0,
    tracking: 0,
    blacklist: 0,
    negotiated: 0
  })
  
  const loading = ref(false)
  
  // Get count for a specific status
  const getCount = async (status) => {
    try {
      const { data, error } = await get('/leads', { 
        status: status,
        page: 1,
        per_page: 1
      })
      if (!error && data) {
        return data.total || 0
      }
      return 0
    } catch (err) {
      console.warn(`Failed to get ${status} count:`, err)
      return 0
    }
  }
  
  // Get pending count specifically (most important)
  const getPendingCount = async () => {
    const count = await getCount('pending')
    badges.value.pending = count
    return count
  }
  
  // Get all badge counts
  const refreshAllBadges = async () => {
    if (loading.value) return
    
    loading.value = true
    try {
      const [pending, intake, disbursed, tracking, blacklist, negotiated] = await Promise.all([
        getCount('pending'),
        getCount('intake'),
        getCount('disbursed'),
        getCount('tracking'),
        getCount('blacklist'),
        getCount('negotiated')
      ])
      
      badges.value = {
        pending,
        intake,
        disbursed,
        tracking,
        blacklist,
        negotiated
      }
    } catch (err) {
      console.error('Failed to refresh badges:', err)
    } finally {
      loading.value = false
    }
  }
  
  // Initialize badges on mount
  let pollInterval = null
  
  const startPolling = (intervalMs = 30000) => { // Poll every 30 seconds
    stopPolling()
    refreshAllBadges() // Initial load
    pollInterval = setInterval(refreshAllBadges, intervalMs)
  }
  
  const stopPolling = () => {
    if (pollInterval) {
      clearInterval(pollInterval)
      pollInterval = null
    }
  }
  
  // Manual refresh function
  const refreshBadges = async () => {
    await refreshAllBadges()
  }
  
  // Cleanup on unmount
  onUnmounted(() => {
    stopPolling()
  })
  
  return {
    badges: readonly(badges),
    loading: readonly(loading),
    refreshBadges,
    getPendingCount,
    startPolling,
    stopPolling
  }
}