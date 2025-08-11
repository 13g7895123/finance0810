export const usePermissions = () => {
  const { get, post, del } = useApi()
  
  // Get all permissions grouped by category
  const getPermissions = async () => {
    const { data, error } = await get('/permissions')
    if (error) throw error
    return data
  }

  // Get permissions by category
  const getPermissionsByCategory = async (category) => {
    const { data, error } = await get(`/permissions/category/${category}`)
    if (error) throw error
    return data
  }

  // Get user roles
  const getUserRoles = async (userId) => {
    const { data, error } = await get(`/users/${userId}/roles`)
    if (error) throw error
    return data
  }

  // Get role permissions
  const getRolePermissions = async (roleId) => {
    const { data, error } = await get(`/roles/${roleId}/permissions`)
    if (error) throw error
    return data
  }

  // Assign permission to role
  const assignPermissionToRole = async (roleId, permissionId) => {
    const { data, error } = await post(`/roles/${roleId}/permissions`, { permission_id: permissionId })
    if (error) throw error
    return data
  }

  // Remove permission from role
  const removePermissionFromRole = async (roleId, permissionId) => {
    const { data, error } = await del(`/roles/${roleId}/permissions/${permissionId}`)
    if (error) throw error
    return data
  }

  return {
    getPermissions,
    getPermissionsByCategory,
    getUserRoles,
    getRolePermissions,
    assignPermissionToRole,
    removePermissionFromRole
  }
}