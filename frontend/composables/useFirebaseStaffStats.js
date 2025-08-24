import { ref, reactive } from 'vue'
import { ref as dbRef, onValue, off } from 'firebase/database'

export const useFirebaseStaffStats = () => {
  const { $firebaseDB } = useNuxtApp()
  const { canViewAllChats, getLocalUser } = useAuth()
  
  // 狀態管理
  const isConnected = ref(false)
  const staffStats = ref(null)
  const allStaffStats = ref(null)
  const listeners = reactive(new Map())
  
  // 錯誤處理
  const error = ref(null)
  const connectionStatus = ref('disconnected')

  /**
   * 初始化連接
   */
  const initialize = () => {
    if (!$firebaseDB) {
      console.warn('Firebase Realtime Database not available for staff stats')
      connectionStatus.value = 'error'
      return false
    }
    
    connectionStatus.value = 'connecting'
    isConnected.value = true
    connectionStatus.value = 'connected'
    console.log('Firebase Realtime Database staff stats connection initialized')
    return true
  }

  /**
   * 監聽個人員工統計（一般員工用）
   */
  const watchStaffStats = (staffId) => {
    if (!isConnected.value || !staffId) return

    try {
      const statsRef = dbRef($firebaseDB, `staff_unread_stats/${staffId}`)

      const listener = onValue(statsRef, (snapshot) => {
        if (snapshot.exists()) {
          const data = snapshot.val()
          staffStats.value = {
            staffId: data.staffId,
            totalUnread: data.totalUnread || 0,
            activeConversations: data.activeConversations || 0,
            conversationDetails: data.conversationDetails || [],
            lastUpdated: data.updated ? new Date(data.updated) : new Date(),
            generatedAt: data.generatedAt || new Date().toISOString()
          }
          console.log(`Staff stats updated for ${staffId}:`, staffStats.value)
        } else {
          staffStats.value = null
          console.log(`No staff stats found for ${staffId}`)
        }
      }, (error) => {
        console.error(`Firebase Realtime Database staff stats listener error for ${staffId}:`, error)
        handleFirebaseError(error)
      })

      // 儲存監聽器引用
      listeners.set(`staff_stats_${staffId}`, { ref: statsRef, listener })
      
    } catch (error) {
      console.error(`Failed to setup Realtime Database staff stats listener for ${staffId}:`, error)
      handleFirebaseError(error)
    }
  }

  /**
   * 監聽所有員工統計總覽（admin/executive 用）
   */
  const watchAllStaffStats = () => {
    if (!isConnected.value || !canViewAllChats()) return

    try {
      const overviewRef = dbRef($firebaseDB, 'admin_staff_overview/all_staff_stats')

      const listener = onValue(overviewRef, (snapshot) => {
        if (snapshot.exists()) {
          const data = snapshot.val()
          allStaffStats.value = {
            totalStaff: data.totalStaff || 0,
            totalUnreadMessages: data.totalUnreadMessages || 0,
            totalActiveConversations: data.totalActiveConversations || 0,
            staffDetails: data.staffDetails || [],
            lastUpdated: data.lastUpdated ? new Date(data.lastUpdated) : new Date(),
            generatedAt: data.generatedAt || new Date().toISOString()
          }
          console.log('All staff stats updated:', allStaffStats.value)
        } else {
          allStaffStats.value = null
          console.log('No admin staff overview found')
        }
      }, (error) => {
        console.error('Firebase Realtime Database all staff stats listener error:', error)
        handleFirebaseError(error)
      })

      // 儲存監聽器引用
      listeners.set('all_staff_stats', { ref: overviewRef, listener })
      
    } catch (error) {
      console.error('Failed to setup Realtime Database all staff stats listener:', error)
      handleFirebaseError(error)
    }
  }

  /**
   * 開始監聽統計資料（根據權限自動選擇）
   */
  const startStatsMonitoring = () => {
    if (!initialize()) return false

    const user = getLocalUser()
    if (!user) return false

    if (canViewAllChats()) {
      // Admin/executive 用戶監聽所有員工統計
      console.log('Starting all staff stats monitoring for admin/executive user')
      watchAllStaffStats()
    } else {
      // 一般員工只監聽自己的統計
      console.log(`Starting individual staff stats monitoring for user ${user.id}`)
      watchStaffStats(user.id)
    }

    return true
  }

  /**
   * 停止監聽特定統計
   */
  const stopStatsMonitoring = (key) => {
    const listenerData = listeners.get(key)
    if (listenerData) {
      off(listenerData.ref, 'value', listenerData.listener)
      listeners.delete(key)
      console.log(`Stopped monitoring stats: ${key}`)
    }
  }

  /**
   * 獲取特定員工的詳細統計（從全域統計中查找）
   */
  const getStaffDetailFromOverview = (staffId) => {
    if (!allStaffStats.value || !allStaffStats.value.staffDetails) return null
    
    return allStaffStats.value.staffDetails.find(detail => detail.staffId === staffId)
  }

  /**
   * 計算總未讀數量（根據當前用戶權限）
   */
  const getTotalUnreadCount = () => {
    if (canViewAllChats() && allStaffStats.value) {
      return allStaffStats.value.totalUnreadMessages
    } else if (staffStats.value) {
      return staffStats.value.totalUnread
    }
    return 0
  }

  /**
   * 獲取活躍對話數量
   */
  const getActiveConversationsCount = () => {
    if (canViewAllChats() && allStaffStats.value) {
      return allStaffStats.value.totalActiveConversations
    } else if (staffStats.value) {
      return staffStats.value.activeConversations
    }
    return 0
  }

  /**
   * 錯誤處理
   */
  const handleFirebaseError = (firebaseError) => {
    error.value = firebaseError
    connectionStatus.value = 'error'
    console.warn('Firebase staff stats error, may need fallback')
  }

  /**
   * 清理所有監聽器
   */
  const cleanup = () => {
    listeners.forEach((listenerData, key) => {
      try {
        off(listenerData.ref, 'value', listenerData.listener)
        console.log(`Cleaned up Firebase stats listener: ${key}`)
      } catch (error) {
        console.error(`Error cleaning up Firebase stats listener ${key}:`, error)
      }
    })
    
    listeners.clear()
    isConnected.value = false
    connectionStatus.value = 'disconnected'
    staffStats.value = null
    allStaffStats.value = null
  }

  return {
    // 狀態
    isConnected: readonly(isConnected),
    staffStats: readonly(staffStats),
    allStaffStats: readonly(allStaffStats),
    error: readonly(error),
    connectionStatus: readonly(connectionStatus),
    
    // 方法
    initialize,
    startStatsMonitoring,
    stopStatsMonitoring,
    watchStaffStats,
    watchAllStaffStats,
    getStaffDetailFromOverview,
    getTotalUnreadCount,
    getActiveConversationsCount,
    cleanup
  }
}