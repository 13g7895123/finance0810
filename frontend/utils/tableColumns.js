/**
 * Common table column configurations
 * 提供常用的表格欄位配置
 */

// Common formatters
export const formatters = {
  date: (value) => {
    if (!value) return '無記錄'
    return new Date(value).toLocaleDateString('zh-TW', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  },
  
  datetime: (value) => {
    if (!value) return '無記錄'
    return new Date(value).toLocaleString('zh-TW', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  },
  
  currency: (value) => {
    if (!value && value !== 0) return '未設定'
    return `NT$ ${Number(value).toLocaleString()}`
  },
  
  percentage: (value) => {
    if (!value && value !== 0) return '0%'
    return `${value}%`
  },
  
  status: (value, statusOptions) => {
    return statusOptions[value] || value || '未知'
  },
  
  boolean: (value) => {
    return value ? '是' : '否'
  },
  
  truncate: (value, length = 50) => {
    if (!value) return ''
    return value.length > length ? value.substring(0, length) + '...' : value
  }
}

// Common column configurations
export const commonColumns = {
  // User columns
  user: {
    key: 'user',
    title: '用戶資訊',
    sortable: false,
    cellClass: 'font-medium'
  },
  
  userAvatar: {
    key: 'user.avatar',
    title: '頭像',
    width: '60px',
    sortable: false
  },
  
  userName: {
    key: 'name',
    title: '姓名',
    sortable: true,
    cellClass: 'font-medium text-gray-900'
  },
  
  userEmail: {
    key: 'email',
    title: '電子郵件',
    sortable: true,
    cellClass: 'text-gray-500'
  },
  
  userRole: {
    key: 'roles.0.display_name',
    title: '角色',
    sortable: true,
    width: '120px'
  },
  
  userStatus: {
    key: 'status',
    title: '狀態',
    sortable: true,
    width: '100px'
  },
  
  // Customer columns
  customerInfo: {
    key: 'customer',
    title: '客戶資訊',
    sortable: false
  },
  
  customerName: {
    key: 'name',
    title: '客戶姓名',
    sortable: true,
    cellClass: 'font-medium text-gray-900'
  },
  
  customerPhone: {
    key: 'phone',
    title: '電話',
    sortable: true,
    width: '140px'
  },
  
  customerEmail: {
    key: 'email',
    title: '電子郵件',
    sortable: true,
    cellClass: 'text-gray-500'
  },
  
  customerRegion: {
    key: 'region',
    title: '地區',
    sortable: true,
    width: '100px',
    formatter: (value) => value || '未填寫'
  },
  
  customerStatus: {
    key: 'status',
    title: '狀態',
    sortable: true,
    width: '120px'
  },
  
  assignedUser: {
    key: 'assigned_user.name',
    title: '負責業務',
    sortable: true,
    width: '120px',
    formatter: (value) => value || '未分配'
  },
  
  // Case columns
  caseStatus: {
    key: 'case_status',
    title: '案件狀態',
    sortable: true,
    width: '120px'
  },
  
  // LINE related columns
  lineStatus: {
    key: 'line_user_id',
    title: 'LINE 狀態',
    sortable: false,
    width: '120px'
  },
  
  lineUser: {
    key: 'line_display_name',
    title: 'LINE 用戶',
    sortable: true,
    formatter: (value) => value || '未綁定'
  },
  
  // Date columns
  createdAt: {
    key: 'created_at',
    title: '建立時間',
    sortable: true,
    width: '140px',
    formatter: formatters.date
  },
  
  updatedAt: {
    key: 'updated_at',
    title: '更新時間',
    sortable: true,
    width: '140px',
    formatter: formatters.date
  },
  
  lastLogin: {
    key: 'last_login_at',
    title: '最後登入',
    sortable: true,
    width: '140px',
    formatter: formatters.datetime
  },
  
  lastContact: {
    key: 'updated_at',
    title: '最後聯絡',
    sortable: true,
    width: '120px',
    formatter: formatters.date
  },
  
  // Financial columns
  approvedAmount: {
    key: 'approved_amount',
    title: '核准金額',
    sortable: true,
    width: '120px',
    textClass: 'text-right',
    formatter: formatters.currency
  },
  
  disbursedAmount: {
    key: 'disbursed_amount',
    title: '撥款金額',
    sortable: true,
    width: '120px',
    textClass: 'text-right',
    formatter: formatters.currency
  },
  
  // Action column
  actions: {
    key: 'actions',
    title: '操作',
    sortable: false,
    width: '200px',
    textClass: 'text-right'
  }
}

// Common column sets
export const columnSets = {
  users: [
    commonColumns.user,
    commonColumns.userRole,
    commonColumns.userStatus,
    commonColumns.lastLogin,
    commonColumns.actions
  ],
  
  customers: [
    commonColumns.customerInfo,
    {
      key: 'contact',
      title: '聯絡方式',
      sortable: false,
      width: '200px'
    },
    commonColumns.customerStatus,
    commonColumns.caseStatus,
    commonColumns.lineStatus,
    commonColumns.lastContact,
    commonColumns.assignedUser,
    commonColumns.actions
  ],
  
  simpleCustomers: [
    commonColumns.customerName,
    commonColumns.customerPhone,
    commonColumns.customerEmail,
    commonColumns.customerStatus,
    commonColumns.assignedUser,
    commonColumns.actions
  ]
}

// Status options for different entities
export const statusOptions = {
  user: {
    active: '啟用',
    inactive: '停用',
    suspended: '暫停'
  },
  
  customer: {
    new: '新客戶',
    contacted: '已聯絡',
    interested: '有興趣',
    not_interested: '無興趣',
    invalid: '無效',
    converted: '已轉換'
  },
  
  case: {
    submitted: '已送件',
    approved: '已核准',
    rejected: '已婉拒',
    disbursed: '已撥款'
  }
}

// Status styling
export const statusStyles = {
  user: {
    active: 'bg-green-600 text-white',
    inactive: 'bg-red-600 text-white',
    suspended: 'bg-yellow-600 text-white'
  },
  
  customer: {
    new: 'bg-blue-100 text-blue-800',
    contacted: 'bg-yellow-100 text-yellow-800',
    interested: 'bg-green-100 text-green-800',
    not_interested: 'bg-red-100 text-red-800',
    invalid: 'bg-gray-100 text-gray-800',
    converted: 'bg-purple-100 text-purple-800'
  },
  
  case: {
    submitted: 'bg-blue-100 text-blue-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    disbursed: 'bg-purple-100 text-purple-800'
  }
}

/**
 * Create a status column with proper styling
 */
export const createStatusColumn = (key, title, type, width = '120px') => {
  return {
    key,
    title,
    sortable: true,
    width,
    formatter: (value) => statusOptions[type][value] || value || '未知'
  }
}

/**
 * Create an action column with specified width
 */
export const createActionColumn = (width = '200px') => {
  return {
    ...commonColumns.actions,
    width
  }
}