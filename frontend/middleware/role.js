export default defineNuxtRouteMiddleware(async (to) => {
  const authStore = useAuthStore()
  
  // 初始化認證狀態
  if (process.client) {
    await nextTick()
    authStore.initializeAuth()
  }
  
  // 如果未登入，重定向到登入頁面
  if (!authStore.isLoggedIn) {
    return navigateTo('/auth/login')
  }

  // 定義路由權限映射
  const routePermissions = {
    // 儀表板
    '/dashboard': ['dashboard', 'all_access'],
    '/dashboard/analytics': ['dashboard', 'all_access'],
    
    // 案件管理 - 需要客戶管理權限
    '/cases': ['customer_management', 'all_access'],
    '/cases/pending': ['customer_management', 'all_access'],
    '/cases/progress': ['customer_management', 'all_access'],
    '/cases/completed': ['customer_management', 'all_access'],
    
    // 業務管理
    '/sales': ['customer_management', 'personal_customers', 'all_access'],
    '/sales/customers': ['customer_management', 'personal_customers', 'all_access'],
    '/sales/reports': ['reports', 'all_access'],
    '/sales/statistics': ['reports', 'all_access'],
    
    // 銀行交涉紀錄 - 只有經銷商/公司高層
    '/bank-records': ['all_access'],
    
    // 統計報表
    '/reports': ['reports', 'all_access'],
    '/reports/sales': ['reports', 'all_access'],
    '/reports/customers': ['reports', 'all_access'],
    '/reports/performance': ['reports', 'all_access'],
    
    // 聊天室
    '/chat': ['chat', 'all_access'],
    
    // 系統設定
    '/settings': ['settings', 'user_management', 'all_access'],
    '/settings/system': ['settings', 'all_access'],
    '/settings/users': ['user_management', 'all_access'],
    '/settings/permissions': ['all_access'],
    '/settings/theme': ['settings', 'user_management', 'all_access'],
    '/settings/ui': ['settings', 'user_management', 'all_access']
  }

  // 檢查當前路由是否需要特定權限
  const currentPath = to.path
  const requiredPermissions = routePermissions[currentPath]

  if (requiredPermissions) {
    // 檢查用戶是否有所需權限
    const hasPermission = requiredPermissions.some(permission => 
      authStore.hasPermission(permission)
    )

    if (!hasPermission) {
      // 根據用戶角色重定向到合適的頁面
      if (authStore.isSales) {
        return navigateTo('/sales/customers')
      } else if (authStore.isAdmin) {
        return navigateTo('/dashboard/analytics')
      } else {
        return navigateTo('/dashboard/analytics')
      }
    }
  }

  // 特殊邏輯：業務人員嘗試存取客戶資料時，限制只能看到自己的資料
  if (currentPath.startsWith('/sales/customers') && authStore.isSales) {
    // 可以在這裡添加額外的邏輯來過濾客戶資料
    // 例如：設置查詢參數或狀態來限制數據
  }
})