export const useNotificationsStore = defineStore('notifications', () => {
  // Initialize with static times to prevent hydration mismatch
  const baseTime = new Date('2024-08-08T12:00:00Z')
  
  const notifications = ref([
    {
      id: 1,
      type: 'system',
      title: 'notifications.system_update',
      message: 'A new system update is available. Please update to get the latest features.',
      time: new Date(baseTime.getTime() - 5 * 60 * 1000),
      read: false,
      priority: 'high',
      icon: 'ExclamationCircleIcon'
    },
    {
      id: 2,
      type: 'user',
      title: 'notifications.user_registration',
      message: 'New user "john.doe@example.com" has registered.',
      time: new Date(baseTime.getTime() - 10 * 60 * 1000),
      read: false,
      priority: 'medium',
      icon: 'UserPlusIcon'
    },
    {
      id: 3,
      type: 'report',
      title: 'notifications.daily_report',
      message: 'Your daily analytics report is ready for review.',
      time: new Date(baseTime.getTime() - 60 * 60 * 1000),
      read: true,
      priority: 'low',
      icon: 'DocumentTextIcon'
    },
    {
      id: 4,
      type: 'security',
      title: 'Security Alert',
      message: 'Unusual login activity detected from a new device.',
      time: new Date(baseTime.getTime() - 2 * 60 * 60 * 1000),
      read: false,
      priority: 'high',
      icon: 'ShieldExclamationIcon'
    }
  ])

  const unreadCount = computed(() => 
    notifications.value.filter(n => !n.read).length
  )

  const priorityNotifications = computed(() =>
    notifications.value
      .filter(n => n.priority === 'high' && !n.read)
      .slice(0, 3)
  )

  const recentNotifications = computed(() =>
    notifications.value
      .sort((a, b) => b.time - a.time)
      .slice(0, 5)
  )

  const addNotification = (notification) => {
    const newNotification = {
      id: Date.now(),
      type: notification.type || 'info',
      title: notification.title,
      message: notification.message,
      time: new Date(),
      read: false,
      priority: notification.priority || 'medium',
      icon: notification.icon || 'InformationCircleIcon'
    }
    notifications.value.unshift(newNotification)
    
    // Auto-remove after 30 seconds if it's a toast notification
    if (notification.autoRemove !== false) {
      setTimeout(() => {
        removeNotification(newNotification.id)
      }, 30000)
    }
  }

  const markAsRead = (id) => {
    const notification = notifications.value.find(n => n.id === id)
    if (notification) {
      notification.read = true
    }
  }

  const markAllAsRead = () => {
    notifications.value.forEach(n => {
      n.read = true
    })
  }

  const removeNotification = (id) => {
    const index = notifications.value.findIndex(n => n.id === id)
    if (index > -1) {
      notifications.value.splice(index, 1)
    }
  }

  const clearAllNotifications = () => {
    notifications.value = []
  }

  const clearReadNotifications = () => {
    notifications.value = notifications.value.filter(n => !n.read)
  }

  const getTimeAgo = (time) => {
    // Use a static reference time to prevent hydration mismatch
    if (process.server) {
      // On server, return static relative time
      return '幾分鐘前'
    }
    
    // On client, calculate relative time
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

  // Simulate real-time notifications - client-only
  const simulateRealTimeNotifications = () => {
    // Only run on client to prevent hydration issues
    if (process.server) return
    
    const notificationTypes = [
      {
        type: 'user',
        title: 'New user registration',
        message: 'A new user has joined the platform.',
        priority: 'medium',
        icon: 'UserPlusIcon'
      },
      {
        type: 'system',
        title: 'System maintenance',
        message: 'Scheduled maintenance will begin in 1 hour.',
        priority: 'high',
        icon: 'WrenchScrewdriverIcon'
      },
      {
        type: 'report',
        title: 'Weekly report ready',
        message: 'Your weekly analytics report is ready.',
        priority: 'low',
        icon: 'DocumentTextIcon'
      }
    ]

    // Simulate notifications every 30 seconds to 2 minutes
    setInterval(() => {
      if (Math.random() > 0.7) { // 30% chance
        const randomNotification = notificationTypes[
          Math.floor(Math.random() * notificationTypes.length)
        ]
        addNotification(randomNotification)
      }
    }, 30000 + Math.random() * 90000) // 30s to 2min random interval
  }

  return {
    notifications: readonly(notifications),
    unreadCount,
    priorityNotifications,
    recentNotifications,
    addNotification,
    markAsRead,
    markAllAsRead,
    removeNotification,
    clearAllNotifications,
    clearReadNotifications,
    getTimeAgo,
    simulateRealTimeNotifications
  }
})