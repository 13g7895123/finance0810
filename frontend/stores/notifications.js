export const useNotificationsStore = defineStore('notifications', () => {
  const notifications = ref([
    {
      id: 1,
      type: 'system',
      title: 'notifications.system_update',
      message: 'A new system update is available. Please update to get the latest features.',
      time: new Date(Date.now() - 5 * 60 * 1000),
      read: false,
      priority: 'high',
      icon: 'ExclamationCircleIcon'
    },
    {
      id: 2,
      type: 'user',
      title: 'notifications.user_registration',
      message: 'New user "john.doe@example.com" has registered.',
      time: new Date(Date.now() - 10 * 60 * 1000),
      read: false,
      priority: 'medium',
      icon: 'UserPlusIcon'
    },
    {
      id: 3,
      type: 'report',
      title: 'notifications.daily_report',
      message: 'Your daily analytics report is ready for review.',
      time: new Date(Date.now() - 60 * 60 * 1000),
      read: true,
      priority: 'low',
      icon: 'DocumentTextIcon'
    },
    {
      id: 4,
      type: 'security',
      title: 'Security Alert',
      message: 'Unusual login activity detected from a new device.',
      time: new Date(Date.now() - 2 * 60 * 60 * 1000),
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
    const now = new Date()
    const diff = now - time
    const minutes = Math.floor(diff / (1000 * 60))
    const hours = Math.floor(diff / (1000 * 60 * 60))
    const days = Math.floor(diff / (1000 * 60 * 60 * 24))

    if (minutes < 1) return 'Just now'
    if (minutes < 60) return `${minutes} minutes ago`
    if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`
    return `${days} day${days > 1 ? 's' : ''} ago`
  }

  // Simulate real-time notifications
  const simulateRealTimeNotifications = () => {
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