export const useUserManagement = () => {
  const authStore = useAuthStore()
  const { get, post, put, del } = useApi()
  
  // 檢查認證狀態
  const checkAuth = () => {
    if (!authStore.user || !authStore.isLoggedIn) {
      throw new Error('Authentication required. Please login first.')
    }
  }


  // 獲取使用者列表
  const getUsers = async (params = {}) => {
    checkAuth()
    const { data, error } = await get('/users', params)
    if (error) throw error
    return data
  }

  // 創建新使用者
  const createUser = async (userData) => {
    checkAuth()
    const { data, error } = await post('/users', userData)
    if (error) throw error
    return data
  }

  // 更新使用者
  const updateUser = async (userId, userData) => {
    checkAuth()
    const { data, error } = await put(`/users/${userId}`, userData)
    if (error) throw error
    return data
  }

  // 刪除使用者
  const deleteUser = async (userId) => {
    checkAuth()
    const { data, error } = await del(`/users/${userId}`)
    if (error) throw error
    return data
  }

  // 指派角色
  const assignRole = async (userId, roleName) => {
    checkAuth()
    try {
      if (!userId || !roleName) {
        throw new Error('User ID and role name are required')
      }
      const { data, error } = await post(`/users/${userId}/roles`, { role: roleName })
      if (error) {
        console.error(`Error assigning role ${roleName} to user ${userId}:`, error)
        throw error
      }
      return data
    } catch (err) {
      console.error(`Failed to assign role ${roleName} to user ${userId}:`, err)
      throw err
    }
  }

  // 移除用戶角色
  const removeUserFromRole = async (userId, roleId) => {
    checkAuth()
    const { data, error } = await del(`/users/${userId}/roles/${roleId}`)
    if (error) throw error
    return data
  }

  // 獲取可用角色
  const getRoles = async () => {
    checkAuth()
    const { data, error } = await get('/roles')
    if (error) throw error
    return data
  }

  // 獲取使用者統計
  const getUserStats = async () => {
    checkAuth()
    const { data, error } = await get('/users/stats/overview')
    if (error) throw error
    return data
  }

  // 獲取特定使用者詳細資訊
  const getUser = async (userId) => {
    checkAuth()
    const { data, error } = await get(`/users/${userId}`)
    if (error) throw error
    return data
  }

  return {
    getUsers,
    createUser,
    updateUser,
    deleteUser,
    assignRole,
    removeUserFromRole,
    getRoles,
    getUserStats,
    getUser
  }
}