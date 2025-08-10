export const useSettingsStore = defineStore('settings', () => {
  const showFootbar = ref(true)
  const sidebarMenuItems = ref([
    {
      name: '儀表板',
      icon: 'ChartBarIcon',
      href: '/dashboard/analytics',
      permissions: ['dashboard', 'all_access']
    },
    {
      name: '聊天室',
      icon: 'ChatBubbleLeftRightIcon',
      href: '/chat',
      permissions: ['chat', 'all_access']
    },
    {
      name: '案件管理',
      icon: 'DocumentTextIcon',
      children: [
        { name: '待處理案件', href: '/cases/pending', permissions: ['customer_management', 'all_access'] },
        { name: '可送件案件', href: '/cases/submittable', permissions: ['customer_management', 'all_access'] },
        { name: '進行中案件', href: '/cases/progress', permissions: ['customer_management', 'all_access'] },
        { name: '已完成案件', href: '/cases/completed', permissions: ['customer_management', 'all_access'] },
        { name: '協商客戶', href: '/cases/negotiated', permissions: ['customer_management', 'all_access'] }
      ],
      permissions: ['customer_management', 'all_access']
    },
    {
      name: '業務管理',
      icon: 'UserGroupIcon',
      children: [
        { name: '客戶資料', href: '/sales/customers', permissions: ['customer_management', 'personal_customers', 'all_access'] },
        { name: '追蹤管理', href: '/sales/tracking', permissions: ['customer_management', 'personal_customers', 'all_access'] },
        { name: '進件資料', href: '/sales/applications', permissions: ['customer_management', 'all_access'] },
        { name: '銷售報表', href: '/sales/reports', permissions: ['reports', 'all_access'] },
        { name: '業績統計', href: '/sales/statistics', permissions: ['reports', 'all_access'] }
      ],
      permissions: ['customer_management', 'personal_customers', 'all_access']
    },
    {
      name: '網站設定',
      icon: 'CogIcon',
      children: [
        { name: '網站名稱管理', href: '/settings/websites', permissions: ['settings', 'all_access'] },
        { name: '系統設定', href: '/settings/system', permissions: ['settings', 'all_access'] },
        { name: '用戶管理', href: '/settings/users', permissions: ['user_management', 'all_access'] },
        { name: '權限管理', href: '/settings/permissions', permissions: ['all_access'] },
        { name: 'LINE 整合', href: '/settings/line', permissions: ['settings', 'all_access'] }
      ],
      permissions: ['settings', 'user_management', 'all_access']
    },
    {
      name: '統計報表',
      icon: 'ChartPieIcon',
      children: [
        { name: '每日網站績效', href: '/reports/website-performance', permissions: ['reports', 'all_access'] },
        { name: '進件統計', href: '/reports/applications', permissions: ['reports', 'all_access'] },
        { name: '撥款統計', href: '/reports/disbursement', permissions: ['reports', 'all_access'] },
        { name: '銷售分析', href: '/reports/sales', permissions: ['reports', 'all_access'] },
        { name: '客戶分析', href: '/reports/customers', permissions: ['reports', 'all_access'] },
        { name: '會計報表', href: '/reports/accounting', permissions: ['reports', 'all_access'] }
      ],
      permissions: ['reports', 'all_access']
    }
  ])
  
  const toggleFootbar = () => {
    showFootbar.value = !showFootbar.value
  }
  
  const updateMenuItems = (newItems) => {
    sidebarMenuItems.value = newItems
  }
  
  return {
    showFootbar,
    sidebarMenuItems,
    toggleFootbar,
    updateMenuItems
  }
})