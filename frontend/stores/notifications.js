// Point 3: Real-time notification store with API integration
export const useNotificationsStore = defineStore('notifications', () => {
  const notifications = ref([])
  const loading = ref(false)
  const pollingInterval = ref(null)
  const lastNotificationId = ref(null)

  const unreadCount = computed(() =>
    notifications.value.filter(n => !n.is_read).length
  )

  const priorityNotifications = computed(() =>
    notifications.value
      .filter(n => n.priority === 'high' && !n.is_read)
      .slice(0, 3)
  )

  const recentNotifications = computed(() =>
    notifications.value
      .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
      .slice(0, 10)
  )

  /**
   * Point 3: Fetch notifications from API
   */
  const fetchNotifications = async () => {
    try {
      // Check if user is authenticated before fetching
      const authStore = useAuthStore()
      if (!authStore.isLoggedIn) {
        console.log('Point 3 Debug - Skipping notification fetch: user not authenticated')
        notifications.value = []
        return
      }

      const api = useApi()
      const response = await api.get('/notifications', {
        params: {
          per_page: 20
        }
      })

      // Point 3 Debug: Log raw response
      console.log('Point 3 Debug - Raw API response:', response)

      if (response.data) {
        // Point 3 Debug: Log what we're trying to parse
        console.log('Point 3 Debug - response.data:', response.data)
        console.log('Point 3 Debug - response.data.data:', response.data?.data)

        const newNotifications = response.data.data || response.data
        console.log('Point 3 Debug - Parsed notifications array:', newNotifications)
        console.log('Point 3 Debug - Is array?:', Array.isArray(newNotifications))
        console.log('Point 3 Debug - Array length:', newNotifications?.length || 0)

        // Check for new notifications to show toast
        if (lastNotificationId.value && newNotifications.length > 0) {
          const newestId = newNotifications[0].id
          if (newestId !== lastNotificationId.value) {
            // Find new notifications
            const newOnes = newNotifications.filter(n =>
              !notifications.value.find(existing => existing.id === n.id)
            )
            // Show toast for new WP lead notifications
            newOnes.forEach(notification => {
              if (notification.type === 'wp_lead' && !notification.is_read) {
                showToast(notification)
              }
            })
          }
        }

        if (newNotifications.length > 0) {
          lastNotificationId.value = newNotifications[0].id
        }

        notifications.value = newNotifications
        console.log('Point 3 Debug - Set notifications.value to:', notifications.value)
        console.log('Point 3 Debug - notifications.value length:', notifications.value.length)
      } else {
        console.log('Point 3 Debug - No response.data!')
      }
    } catch (error) {
      console.error('Point 3 - Failed to fetch notifications:', error)
    }
  }

  /**
   * Point 3: Fetch unread count only (lightweight)
   */
  const fetchUnreadCount = async () => {
    try {
      const api = useApi()
      const response = await api.get('/notifications/unread-count')
      return response.data?.count || 0
    } catch (error) {
      console.error('Point 3 - Failed to fetch unread count:', error)
      return 0
    }
  }

  /**
   * Point 3: Mark notification as read via API
   */
  const markAsRead = async (id) => {
    try {
      const api = useApi()
      await api.post(`/notifications/${id}/read`)

      // Update local state
      const notification = notifications.value.find(n => n.id === id)
      if (notification) {
        notification.is_read = true
        notification.read_at = new Date().toISOString()
      }

      return true
    } catch (error) {
      console.error('Point 3 - Failed to mark notification as read:', error)
      return false
    }
  }

  /**
   * Point 3: Mark all notifications as read via API
   */
  const markAllAsRead = async () => {
    try {
      const api = useApi()
      await api.post('/notifications/mark-all-read')

      // Update local state
      notifications.value.forEach(n => {
        n.is_read = true
        n.read_at = new Date().toISOString()
      })

      return true
    } catch (error) {
      console.error('Point 3 - Failed to mark all as read:', error)
      return false
    }
  }

  /**
   * Point 3: Clear read notifications via API
   */
  const clearReadNotifications = async () => {
    try {
      const api = useApi()
      await api.post('/notifications/clear-read')

      // Update local state
      notifications.value = notifications.value.filter(n => !n.is_read)

      return true
    } catch (error) {
      console.error('Point 3 - Failed to clear read notifications:', error)
      return false
    }
  }

  /**
   * Point 3: Delete notification via API
   */
  const removeNotification = async (id) => {
    try {
      const api = useApi()
      await api.delete(`/notifications/${id}`)

      // Update local state
      const index = notifications.value.findIndex(n => n.id === id)
      if (index > -1) {
        notifications.value.splice(index, 1)
      }

      return true
    } catch (error) {
      console.error('Point 3 - Failed to remove notification:', error)
      return false
    }
  }

  const currentToast = ref(null)

  /**
   * Point 3: Show toast notification
   */
  const showToast = (notification) => {
    // Only run on client
    if (process.server) return

    // Set current toast for the component to display
    currentToast.value = notification

    // Use native browser notification if permitted
    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification(notification.title || '新通知', {
        body: notification.message,
        icon: '/favicon.ico',
        tag: `notification-${notification.id}`,
        requireInteraction: false
      })
    }

    console.log('Point 3 - New notification toast:', notification.title, notification.message)
  }

  /**
   * Point 3: Close current toast
   */
  const closeToast = () => {
    currentToast.value = null
  }

  /**
   * Point 3: Request browser notification permission
   */
  const requestNotificationPermission = async () => {
    if ('Notification' in window && Notification.permission === 'default') {
      await Notification.requestPermission()
    }
  }

  /**
   * Point 3: Start real-time polling for notifications
   */
  const startPolling = (intervalMs = 30000) => {
    // Only run on client
    if (process.server) return

    // Check if user is authenticated before starting polling
    const authStore = useAuthStore()
    if (!authStore.isLoggedIn) {
      console.log('Point 3 - Cannot start polling: user not authenticated')
      return
    }

    // Clear any existing interval to prevent duplicates
    if (pollingInterval.value) {
      clearInterval(pollingInterval.value)
      pollingInterval.value = null
    }

    // Request notification permission
    requestNotificationPermission()

    // Initial fetch
    fetchNotifications()

    // Poll every intervalMs (default 30 seconds)
    pollingInterval.value = setInterval(() => {
      // Check auth status on each poll
      if (!authStore.isLoggedIn) {
        console.log('Point 3 - User no longer authenticated, stopping polling')
        stopPolling()
        return
      }
      fetchNotifications()
    }, intervalMs)

    console.log('Point 3 - Notification polling started')
  }

  /**
   * Point 3: Stop polling
   */
  const stopPolling = () => {
    if (pollingInterval.value) {
      clearInterval(pollingInterval.value)
      pollingInterval.value = null
      console.log('Point 3 - Notification polling stopped')
    }
  }

  /**
   * Get time ago for display
   */
  const getTimeAgo = (dateString) => {
    // Use a static reference time to prevent hydration mismatch
    if (process.server) {
      return '幾分鐘前'
    }

    const time = new Date(dateString)
    const now = new Date()
    const diff = now - time
    const minutes = Math.floor(diff / (1000 * 60))
    const hours = Math.floor(diff / (1000 * 60 * 60))
    const days = Math.floor(diff / (1000 * 60 * 60 * 24))

    if (minutes < 1) return '剛剛'
    if (minutes < 60) return `${minutes} 分鐘前`
    if (hours < 24) return `${hours} 小時前`
    return `${days} 天前`
  }

  /**
   * Legacy method for backward compatibility - now triggers real polling
   */
  const simulateRealTimeNotifications = () => {
    startPolling()
  }

  // Cleanup on store disposal
  if (import.meta.client) {
    onUnmounted(() => {
      stopPolling()
    })
  }

  return {
    notifications: readonly(notifications),
    loading: readonly(loading),
    unreadCount,
    priorityNotifications,
    recentNotifications,
    currentToast: readonly(currentToast),
    fetchNotifications,
    fetchUnreadCount,
    markAsRead,
    markAllAsRead,
    removeNotification,
    clearReadNotifications,
    closeToast,
    getTimeAgo,
    startPolling,
    stopPolling,
    simulateRealTimeNotifications, // Legacy support
    requestNotificationPermission
  }
})
