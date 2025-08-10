export const useUserManagement = () => {
  const config = useRuntimeConfig()
  const authStore = useAuthStore()
  
  // API 基礎設定 - 只使用真實API
  const apiCall = async (endpoint, options = {}) => {
    const token = authStore.user?.token
    
    if (!token) {
      throw new Error('Authentication required. Please login first.')
    }
    
    const response = await $fetch(endpoint, {
      baseURL: config.public.apiBase || 'http://localhost:9219/api',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...options.headers
      },
      ...options
    })
    
    return response
  }


  // 獲取使用者列表
  const getUsers = async (params = {}) => {
    const queryParams = new URLSearchParams()
    if (params.search) queryParams.append('search', params.search)
    if (params.role) queryParams.append('role', params.role)
    if (params.status) queryParams.append('status', params.status)
    if (params.page) queryParams.append('page', params.page)
    
    const endpoint = `/users${queryParams.toString() ? '?' + queryParams.toString() : ''}`
    return await apiCall(endpoint)
  }

  // 創建新使用者
  const createUser = async (userData) => {
    return await apiCall('/users', {
      method: 'POST',
      body: userData
    })
  }

  // 更新使用者
  const updateUser = async (userId, userData) => {
    return await apiCall(`/users/${userId}`, {
      method: 'PUT',
      body: userData
    })
  }

  // 刪除使用者
  const deleteUser = async (userId) => {
    return await apiCall(`/users/${userId}`, {
      method: 'DELETE'
    })
  }

  // 指派角色
  const assignRole = async (userId, roleName) => {
    return await apiCall(`/users/${userId}/roles`, {
      method: 'POST',
      body: { role: roleName }
    })
  }

  // 獲取可用角色
  const getRoles = async () => {
    return await apiCall('/roles')
  }

  // 獲取使用者統計
  const getUserStats = async () => {
    return await apiCall('/users/stats/overview')
  }

  // 獲取特定使用者詳細資訊
  const getUser = async (userId) => {
    return await apiCall(`/users/${userId}`)
  }

  return {
    getUsers,
    createUser,
    updateUser,
    deleteUser,
    assignRole,
    getRoles,
    getUserStats,
    getUser
  }
}