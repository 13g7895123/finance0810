/**
 * WebSocket Composable for Real-time Chat (Laravel WebSockets + Pusher)
 * WebSocket 實時聊天組合函數 (使用 Laravel WebSockets 和 Pusher 協議)
 */

export const useWebSocket = () => {
  const pusher = ref(null)
  const isConnected = ref(false)
  const isConnecting = ref(false)
  const activeChannels = ref(new Map())
  const connectionErrors = ref(0)
  const maxRetries = 5
  const baseRetryDelay = 1000
  
  const authStore = useAuthStore()
  
  // WebSocket 配置 (動態環境檢測，支援nginx代理)
  const runtimeConfig = useRuntimeConfig()
  const getWebSocketConfig = () => {
    // 檢測當前環境
    const isProduction = !import.meta.dev
    const currentHost = process.client ? window.location.hostname : 'localhost'
    const isHTTPS = process.client ? window.location.protocol === 'https:' : false
    
    if (isProduction || currentHost !== 'localhost') {
      // 生產環境或非本地開發環境 - 支援nginx代理
      const config = {
        key: 'laravel-websockets-key',
        cluster: 'mt1',
        wsHost: currentHost,
        wsPort: isHTTPS ? 443 : 6001,
        forceTLS: isHTTPS,
        encrypted: isHTTPS,
        disableStats: true,
        enabledTransports: isHTTPS ? ['wss'] : ['ws'],
      }
      
      // 如果使用nginx代理且WebSocket透過/app/路徑代理
      if (isHTTPS) {
        // HTTPS環境下使用WSS協議，nginx代理會處理路徑轉換
        config.wsPath = '/app/'
        config.wsPort = 443
        config.enabledTransports = ['wss']
        
        // 關閉Pusher統計功能，避免與代理衝突
        config.disableStats = true
        
        console.log('使用HTTPS WebSocket配置 (支援nginx /app/ 代理):', config)
      } else {
        // HTTP環境直接連接
        config.wsPort = 6001
        config.enabledTransports = ['ws']
        
        console.log('使用HTTP WebSocket配置:', config)
      }
      
      return config
    } else {
      // 本地開發環境
      return {
        key: 'laravel-websockets-key',
        cluster: 'mt1',
        wsHost: 'localhost',
        wsPort: 6001,
        forceTLS: false,
        encrypted: false,
        disableStats: true,
        enabledTransports: ['ws'],
      }
    }
  }
  
  const config = getWebSocketConfig()
  
  /**
   * 連接WebSocket
   */
  const connect = async () => {
    if (isConnected.value || isConnecting.value) {
      return
    }
    
    isConnecting.value = true
    
    try {
      // 動態導入 Pusher
      if (typeof window !== 'undefined') {
        let Pusher
        try {
          // 嘗試從全局獲取 Pusher
          Pusher = window.Pusher
          if (!Pusher) {
            // 如果沒有全局 Pusher，嘗試動態導入
            const pusherModule = await import('pusher-js')
            Pusher = pusherModule.default || pusherModule
          }
        } catch (error) {
          console.error('Failed to load Pusher:', error)
          isConnecting.value = false
          return
        }
        
        if (!Pusher) {
          console.error('Pusher library not available')
          isConnecting.value = false
          return
        }
        
        // 創建 Pusher 連接
        pusher.value = new Pusher(config.key, {
          wsHost: config.wsHost,
          wsPort: config.wsPort,
          forceTLS: config.forceTLS,
          encrypted: config.encrypted,
          disableStats: config.disableStats,
          enabledTransports: config.enabledTransports,
          cluster: config.cluster,
          authEndpoint: '/api/broadcasting/auth',
          auth: {
            headers: {
              'Authorization': `Bearer ${authStore?.token || ''}`,
              'Accept': 'application/json',
            }
          }
        })
        
        // 連接事件處理
        pusher.value.connection.bind('connected', () => {
          console.log('WebSocket connected successfully')
          isConnected.value = true
          isConnecting.value = false
          connectionErrors.value = 0
        })
        
        pusher.value.connection.bind('disconnected', () => {
          console.log('WebSocket disconnected')
          isConnected.value = false
          isConnecting.value = false
          
          // 自動重連
          if (connectionErrors.value < maxRetries) {
            setTimeout(() => {
              connectionErrors.value++
              connect()
            }, baseRetryDelay * Math.pow(2, connectionErrors.value))
          }
        })
        
        pusher.value.connection.bind('error', (error) => {
          console.error('WebSocket connection error:', error)
          isConnected.value = false
          isConnecting.value = false
          connectionErrors.value++
          
          // 詳細的錯誤類型分析
          if (error?.type) {
            switch (error.type) {
              case 'WebSocketError':
                console.error('WebSocket連線錯誤 - 可能的原因：')
                console.error('1. nginx代理配置問題 (檢查/app/路徑代理)')
                console.error('2. WebSocket服務未啟動')
                console.error('3. 防火牆阻擋端口6001')
                break
              case 'AuthError':
                console.error('WebSocket認證錯誤 - JWT token可能無效')
                break
              case 'TransportError':
                console.error('WebSocket傳輸錯誤 - 網路連線問題')
                break
              default:
                console.error('未知WebSocket錯誤類型:', error.type)
            }
          }
          
          // 記錄當前配置用於除錯
          console.error('當前WebSocket配置:', config)
        })
        
      } else {
        console.error('Window object not available (SSR)')
        isConnecting.value = false
      }
      
    } catch (error) {
      console.error('Failed to create WebSocket connection:', error)
      isConnected.value = false
      isConnecting.value = false
    }
  }
  
  /**
   * 斷開WebSocket連接
   */
  const disconnect = () => {
    if (pusher.value) {
      // 取消訂閱所有頻道
      activeChannels.value.forEach((channel, channelName) => {
        try {
          pusher.value.unsubscribe(channelName)
        } catch (error) {
          console.error(`Failed to unsubscribe from ${channelName}:`, error)
        }
      })
      
      pusher.value.disconnect()
      pusher.value = null
    }
    
    isConnected.value = false
    isConnecting.value = false
    activeChannels.value.clear()
  }
  
  /**
   * 訂閱私有頻道
   */
  const subscribeToPrivateChannel = (channelName, callback) => {
    if (!pusher.value || !isConnected.value) {
      console.warn('WebSocket not connected, cannot subscribe to channel')
      return null
    }
    
    try {
      const fullChannelName = `private-${channelName}`
      const channel = pusher.value.subscribe(fullChannelName)
      
      // 監聽新訊息事件
      channel.bind('new-message', (data) => {
        console.log('Received new message:', data)
        if (callback) {
          callback({
            type: 'new_message',
            data: data
          })
        }
      })
      
      // 訂閱成功事件
      channel.bind('pusher:subscription_succeeded', () => {
        console.log(`Successfully subscribed to ${fullChannelName}`)
        activeChannels.value.set(fullChannelName, channel)
      })
      
      // 訂閱失敗事件
      channel.bind('pusher:subscription_error', (error) => {
        console.error(`Failed to subscribe to ${fullChannelName}:`, error)
      })
      
      return channel
      
    } catch (error) {
      console.error(`Error subscribing to channel ${channelName}:`, error)
      return null
    }
  }
  
  /**
   * 取消訂閱頻道
   */
  const unsubscribeFromChannel = (channelName) => {
    const fullChannelName = channelName.startsWith('private-') ? channelName : `private-${channelName}`
    
    if (pusher.value && activeChannels.value.has(fullChannelName)) {
      try {
        pusher.value.unsubscribe(fullChannelName)
        activeChannels.value.delete(fullChannelName)
        console.log(`Unsubscribed from ${fullChannelName}`)
      } catch (error) {
        console.error(`Failed to unsubscribe from ${fullChannelName}:`, error)
      }
    }
  }
  
  /**
   * 加入聊天室
   */
  const joinChatRoom = (lineUserId, callback) => {
    return subscribeToPrivateChannel(`chat.${lineUserId}`, callback)
  }
  
  /**
   * 加入管理員頻道
   */
  const joinAdminChannel = (callback) => {
    return subscribeToPrivateChannel('chat.admin', callback)
  }
  
  /**
   * 離開聊天室
   */
  const leaveChatRoom = (lineUserId) => {
    unsubscribeFromChannel(`chat.${lineUserId}`)
  }
  
  /**
   * 離開管理員頻道
   */
  const leaveAdminChannel = () => {
    unsubscribeFromChannel('chat.admin')
  }
  
  /**
   * 發送聊天訊息 (通過 HTTP API，WebSocket 用於接收)
   */
  const sendChatMessage = async (lineUserId, message) => {
    try {
      const { $api } = useNuxtApp()
      const response = await $api(`/api/chats/${lineUserId}/reply`, {
        method: 'POST',
        body: {
          message: message
        }
      })
      
      return response
    } catch (error) {
      console.error('Failed to send chat message:', error)
      throw error
    }
  }
  
  /**
   * 清理所有連接和回調
   */
  const cleanup = () => {
    disconnect()
  }
  
  /**
   * 當組件銷毀時清理
   */
  onUnmounted(() => {
    cleanup()
  })
  
  return {
    pusher: readonly(pusher),
    isConnected: readonly(isConnected),
    isConnecting: readonly(isConnecting),
    activeChannels: readonly(activeChannels),
    connect,
    disconnect,
    subscribeToPrivateChannel,
    unsubscribeFromChannel,
    joinChatRoom,
    joinAdminChannel,
    leaveChatRoom,
    leaveAdminChannel,
    sendChatMessage,
    cleanup
  }
}