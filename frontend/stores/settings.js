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
      name: '網路進線',
      icon: 'DocumentTextIcon',
      href: '/cases/pending',
      permissions: ['customer_management', 'all_access']
    },
    {
      name: '進件管理',
      icon: 'UserCheckIcon',
      children: [
        { name: '有效客', href: '/leads/valid-customer', permissions: ['customer_management', 'all_access'] },
        { name: '無效客', href: '/leads/invalid-customer', permissions: ['customer_management', 'all_access'] },
        { name: '客服', href: '/leads/customer-service', permissions: ['customer_management', 'all_access'] },
        { name: '黑名單', href: '/leads/blacklist', permissions: ['customer_management', 'all_access'] }
      ],
      permissions: ['customer_management', 'all_access']
    },
    {
      name: '送件管理',
      icon: 'PaperAirplaneIcon',
      children: [
        { name: '核准撥款', href: '/submissions/approved-disbursed', permissions: ['case.view', 'all_access'] },
        { name: '核准未撥', href: '/submissions/approved-pending', permissions: ['case.view', 'all_access'] },
        { name: '附條件', href: '/submissions/conditional', permissions: ['case.view', 'all_access'] },
        { name: '婉拒', href: '/submissions/declined', permissions: ['case.view', 'all_access'] }
      ],
      permissions: ['case.view', 'all_access']
    },
    {
      name: '業務管理',
      icon: 'UserGroupIcon',
      children: [
        { name: '追蹤管理', href: '/cases/customer-tracking', permissions: ['customer_management', 'personal_customers', 'all_access'] },
        { name: '追蹤行事曆', href: '/sales/contact-calendar', permissions: ['customer_management', 'personal_customers', 'all_access'] },
        { name: '追蹤紀錄', href: '/sales/tracking-records', permissions: ['customer_management', 'personal_customers', 'all_access'] }
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
        { name: 'LINE 整合', href: '/settings/line', permissions: ['settings', 'all_access'] },
        { name: '自定義欄位', href: '/settings/custom-fields', permissions: ['settings', 'all_access'] },
        { name: '系統除錯', href: '/settings/debug', permissions: ['settings', 'all_access'] },
        { name: '錯誤日誌', href: '/settings/logs', permissions: ['settings', 'all_access'] }
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