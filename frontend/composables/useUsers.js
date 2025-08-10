/**
 * Users Management Composable
 * 處理用戶管理相關功能
 */

export const useUsers = () => {
  const { get, post, put, del } = useApi()

  /**
   * 獲取用戶列表
   */
  const getUsers = async (params = {}) => {
    const { data, error } = await get('/users', params)
    
    if (error) {
      return { success: false, error, users: [], meta: null }
    }

    return { 
      success: true, 
      users: data.data || data, 
      meta: {
        total: data.total || 0,
        perPage: data.per_page || 15,
        currentPage: data.current_page || 1,
        lastPage: data.last_page || 1
      }
    }
  }

  /**
   * 獲取單一用戶資料
   */
  const getUser = async (userId) => {
    const { data, error } = await get(`/users/${userId}`)
    
    if (error) {
      return { success: false, error, user: null }
    }

    return { success: true, user: data.user || data }
  }

  /**
   * 新增用戶
   */
  const createUser = async (userData) => {
    const { data, error } = await post('/users', userData)
    
    if (error) {
      return { success: false, error }
    }

    return { success: true, user: data.user || data }
  }

  /**
   * 更新用戶資料
   */
  const updateUser = async (userId, userData) => {
    const { data, error } = await put(`/users/${userId}`, userData)
    
    if (error) {
      return { success: false, error }
    }

    return { success: true, user: data.user || data }
  }

  /**
   * 刪除用戶
   */
  const deleteUser = async (userId) => {
    const { data, error } = await del(`/users/${userId}`)
    
    if (error) {
      return { success: false, error }
    }

    return { success: true, message: data.message || '用戶已刪除' }
  }

  /**
   * 獲取用戶統計資料
   */
  const getUserStats = async () => {
    const { data, error } = await get('/users/stats/overview')
    
    if (error) {
      return { success: false, error, stats: null }
    }

    return { success: true, stats: data }
  }

  /**
   * 搜尋用戶
   */
  const searchUsers = async (searchParams) => {
    const params = {
      search: searchParams.query || '',
      role: searchParams.role || '',
      status: searchParams.status || '',
      page: searchParams.page || 1,
      per_page: searchParams.perPage || 15
    }

    return await getUsers(params)
  }

  /**
   * 批量操作用戶狀態
   */
  const batchUpdateUserStatus = async (userIds, status) => {
    const promises = userIds.map(id => 
      updateUser(id, { status })
    )

    try {
      const results = await Promise.allSettled(promises)
      const successful = results.filter(r => r.status === 'fulfilled').length
      const failed = results.filter(r => r.status === 'rejected').length

      return {
        success: failed === 0,
        message: `已更新 ${successful} 位用戶狀態${failed > 0 ? `，${failed} 位失敗` : ''}`,
        successful,
        failed
      }
    } catch (error) {
      return { success: false, error }
    }
  }

  /**
   * 驗證用戶名是否可用
   */
  const checkUsernameAvailable = async (username, excludeUserId = null) => {
    try {
      const params = { search: username }
      const { users } = await getUsers(params)
      
      const existingUser = users.find(user => 
        user.username === username && user.id !== excludeUserId
      )
      
      return !existingUser
    } catch (error) {
      return false
    }
  }

  /**
   * 驗證Email是否可用
   */
  const checkEmailAvailable = async (email, excludeUserId = null) => {
    try {
      const params = { search: email }
      const { users } = await getUsers(params)
      
      const existingUser = users.find(user => 
        user.email === email && user.id !== excludeUserId
      )
      
      return !existingUser
    } catch (error) {
      return false
    }
  }

  return {
    // CRUD 操作
    getUsers,
    getUser,
    createUser,
    updateUser,
    deleteUser,
    
    // 進階功能
    getUserStats,
    searchUsers,
    batchUpdateUserStatus,
    
    // 驗證功能
    checkUsernameAvailable,
    checkEmailAvailable
  }
}