export const usePermissions = () => {
  const { get, post, del } = useApi()
  
  // Get all permissions grouped by category
  const getPermissions = async () => {
    try {
      const { data, error } = await get('/permissions')
      if (error) {
        console.error('Error fetching permissions:', error)
        throw error
      }
      return data
    } catch (err) {
      console.error('Failed to get permissions:', err)
      throw err
    }
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
    try {
      const { data, error } = await get(`/roles/${roleId}/permissions`)
      if (error) {
        console.error(`Error fetching permissions for role ${roleId}:`, error)
        throw error
      }
      return data
    } catch (err) {
      console.error(`Failed to get role permissions for role ${roleId}:`, err)
      throw err
    }
  }

  // Assign permission to role
  const assignPermissionToRole = async (roleId, permissionName) => {
    try {
      if (!roleId || !permissionName) {
        throw new Error('Role ID and permission name are required')
      }
      const { data, error } = await post(`/roles/${roleId}/permissions`, { permission_name: permissionName })
      if (error) {
        console.error(`Error assigning permission ${permissionName} to role ${roleId}:`, error)
        throw error
      }
      return data
    } catch (err) {
      console.error(`Failed to assign permission ${permissionName} to role ${roleId}:`, err)
      throw err
    }
  }

  // Remove permission from role
  const removePermissionFromRole = async (roleId, permissionName) => {
    try {
      if (!roleId || !permissionName) {
        throw new Error('Role ID and permission name are required')
      }
      const { data, error } = await del(`/roles/${roleId}/permissions/${permissionName}`)
      if (error) {
        console.error(`Error removing permission ${permissionName} from role ${roleId}:`, error)
        throw error
      }
      return data
    } catch (err) {
      console.error(`Failed to remove permission ${permissionName} from role ${roleId}:`, err)
      throw err
    }
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