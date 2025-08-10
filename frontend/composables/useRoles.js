/**
 * Roles & Permissions Management Composable
 * 處理角色與權限管理相關功能
 */

export const useRoles = () => {
  const { get, post, del } = useApi()

  /**
   * 獲取所有角色列表
   */
  const getRoles = async () => {
    const { data, error } = await get('/roles')
    
    if (error) {
      return { success: false, error, roles: [] }
    }

    return { success: true, roles: data.roles || data }
  }

  /**
   * 獲取所有權限列表 (按分類)
   * 注意：這個API端點需要後端實現
   */
  const getPermissions = async () => {
    const { data, error } = await get('/permissions')
    
    if (error) {
      return { success: false, error, permissions: [] }
    }

    return { success: true, permissions: data.permissions || data }
  }

  /**
   * 獲取用戶的角色列表
   * 注意：這個API端點需要後端實現
   */
  const getUserRoles = async (userId) => {
    const { data, error } = await get(`/users/${userId}/roles`)
    
    if (error) {
      return { success: false, error, roles: [] }
    }

    return { success: true, roles: data.roles || data }
  }

  /**
   * 分配角色給用戶
   */
  const assignRole = async (userId, roleName) => {
    const { data, error } = await post(`/users/${userId}/roles`, { 
      role: roleName 
    })
    
    if (error) {
      return { success: false, error }
    }

    return { success: true, message: data.message || '角色分配成功' }
  }

  /**
   * 移除用戶角色
   */
  const removeRole = async (userId, roleName) => {
    const { data, error } = await del(`/users/${userId}/roles/${roleName}`)
    
    if (error) {
      return { success: false, error }
    }

    return { success: true, message: data.message || '角色移除成功' }
  }

  /**
   * 獲取角色的權限列表
   * 注意：這個API端點需要後端實現
   */
  const getRolePermissions = async (roleId) => {
    const { data, error } = await get(`/roles/${roleId}/permissions`)
    
    if (error) {
      return { success: false, error, permissions: [] }
    }

    return { success: true, permissions: data.permissions || data }
  }

  /**
   * 批量分配角色給用戶
   */
  const batchAssignRoles = async (userId, roleNames) => {
    const promises = roleNames.map(roleName => 
      assignRole(userId, roleName)
    )

    try {
      const results = await Promise.allSettled(promises)
      const successful = results.filter(r => r.status === 'fulfilled').length
      const failed = results.filter(r => r.status === 'rejected').length

      return {
        success: failed === 0,
        message: `已分配 ${successful} 個角色${failed > 0 ? `，${failed} 個失敗` : ''}`,
        successful,
        failed
      }
    } catch (error) {
      return { success: false, error }
    }
  }

  /**
   * 替換用戶的所有角色
   */
  const replaceUserRoles = async (userId, newRoleNames) => {
    try {
      // 先獲取用戶現有角色
      const { roles: currentRoles } = await getUserRoles(userId)
      
      // 移除所有現有角色
      const removePromises = currentRoles.map(role => 
        removeRole(userId, role.name)
      )
      await Promise.allSettled(removePromises)

      // 分配新角色
      const assignPromises = newRoleNames.map(roleName => 
        assignRole(userId, roleName)
      )
      const results = await Promise.allSettled(assignPromises)
      
      const successful = results.filter(r => r.status === 'fulfilled').length
      const failed = results.filter(r => r.status === 'rejected').length

      return {
        success: failed === 0,
        message: `已更新用戶角色，成功分配 ${successful} 個${failed > 0 ? `，${failed} 個失敗` : ''}`,
        successful,
        failed
      }
    } catch (error) {
      return { success: false, error }
    }
  }

  /**
   * 獲取權限分類
   */
  const getPermissionCategories = () => {
    return [
      { key: 'customer', label: '客戶管理' },
      { key: 'case', label: '案件管理' },
      { key: 'bank', label: '銀行交涉' },
      { key: 'report', label: '報表統計' },
      { key: 'chat', label: '聊天管理' },
      { key: 'user', label: '用戶管理' },
      { key: 'system', label: '系統管理' }
    ]
  }

  /**
   * 獲取角色顯示名稱對應
   */
  const getRoleDisplayNames = () => {
    return {
      admin: '經銷商/公司高層',
      executive: '經銷商/公司高層',
      manager: '行政人員/主管',
      staff: '業務人員'
    }
  }

  /**
   * 檢查用戶是否擁有特定角色
   */
  const userHasRole = async (userId, roleName) => {
    const { roles } = await getUserRoles(userId)
    return roles.some(role => role.name === roleName)
  }

  /**
   * 檢查角色是否擁有特定權限
   */
  const roleHasPermission = async (roleId, permissionName) => {
    const { permissions } = await getRolePermissions(roleId)
    return permissions.some(permission => permission.name === permissionName)
  }

  return {
    // 基本 CRUD
    getRoles,
    getPermissions,
    getUserRoles,
    assignRole,
    removeRole,
    getRolePermissions,
    
    // 批量操作
    batchAssignRoles,
    replaceUserRoles,
    
    // 輔助功能
    getPermissionCategories,
    getRoleDisplayNames,
    userHasRole,
    roleHasPermission
  }
}