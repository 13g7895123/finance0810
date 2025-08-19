/**
 * Constants Composable
 * 統一的常數定義
 */

export const useConstants = () => {
  
  // Lead 狀態（前置階段）
  const LEAD_STATUSES = {
    PENDING: 'pending',
    INTAKE: 'intake',
    APPROVED: 'approved',
    SUBMITTED: 'submitted',
    DISBURSED: 'disbursed',
    BLACKLIST: 'blacklist'
  }

  const LEAD_STATUS_LABELS = {
    [LEAD_STATUSES.PENDING]: '待處理',
    [LEAD_STATUSES.INTAKE]: '已進件',
    [LEAD_STATUSES.APPROVED]: '已核准',
    [LEAD_STATUSES.SUBMITTED]: '已送件',
    [LEAD_STATUSES.DISBURSED]: '已撥款',
    [LEAD_STATUSES.BLACKLIST]: '黑名單'
  }

  const LEAD_STATUS_TRANSITIONS = {
    [LEAD_STATUSES.PENDING]: [LEAD_STATUSES.INTAKE, LEAD_STATUSES.APPROVED, LEAD_STATUSES.SUBMITTED, LEAD_STATUSES.BLACKLIST],
    [LEAD_STATUSES.INTAKE]: [LEAD_STATUSES.APPROVED, LEAD_STATUSES.SUBMITTED],
    [LEAD_STATUSES.APPROVED]: [LEAD_STATUSES.SUBMITTED, LEAD_STATUSES.DISBURSED],
    [LEAD_STATUSES.SUBMITTED]: [LEAD_STATUSES.DISBURSED],
    [LEAD_STATUSES.DISBURSED]: [],
    [LEAD_STATUSES.BLACKLIST]: []
  }

  // 案件狀態
  const CASE_STATUSES = {
    SUBMITTED: 'submitted',
    APPROVED: 'approved', 
    REJECTED: 'rejected',
    DISBURSED: 'disbursed'
  }
  
  // 案件狀態標籤
  const CASE_STATUS_LABELS = {
    [CASE_STATUSES.SUBMITTED]: '已送件',
    [CASE_STATUSES.APPROVED]: '已核准',
    [CASE_STATUSES.REJECTED]: '已婉拒',
    [CASE_STATUSES.DISBURSED]: '已撥款'
  }
  
  // 案件狀態可轉換規則
  const CASE_STATUS_TRANSITIONS = {
    [CASE_STATUSES.SUBMITTED]: [CASE_STATUSES.APPROVED, CASE_STATUSES.REJECTED],
    [CASE_STATUSES.APPROVED]: [CASE_STATUSES.DISBURSED],
    [CASE_STATUSES.REJECTED]: [],
    [CASE_STATUSES.DISBURSED]: []
  }
  
  // 銀行記錄狀態
  const BANK_RECORD_STATUSES = {
    PENDING: 'pending',
    IN_PROGRESS: 'in_progress',
    COMPLETED: 'completed',
    CANCELLED: 'cancelled'
  }
  
  // 銀行記錄狀態標籤
  const BANK_RECORD_STATUS_LABELS = {
    [BANK_RECORD_STATUSES.PENDING]: '待處理',
    [BANK_RECORD_STATUSES.IN_PROGRESS]: '進行中',
    [BANK_RECORD_STATUSES.COMPLETED]: '已完成',
    [BANK_RECORD_STATUSES.CANCELLED]: '取消'
  }
  
  // 通信類型
  const COMMUNICATION_TYPES = {
    PHONE: 'phone',
    EMAIL: 'email', 
    MEETING: 'meeting',
    VIDEO_CALL: 'video_call'
  }
  
  // 通信類型標籤
  const COMMUNICATION_TYPE_LABELS = {
    [COMMUNICATION_TYPES.PHONE]: '電話',
    [COMMUNICATION_TYPES.EMAIL]: 'Email',
    [COMMUNICATION_TYPES.MEETING]: '會議',
    [COMMUNICATION_TYPES.VIDEO_CALL]: '視訊'
  }
  
  // 分頁選項
  const PAGINATION_OPTIONS = [
    { value: 10, label: '10 /頁' },
    { value: 20, label: '20 /頁' },
    { value: 50, label: '50 /頁' }
  ]
  
  // 搜尋配置
  const SEARCH_CONFIG = {
    MIN_CHARACTERS: 2,
    DEBOUNCE_DELAY: 300
  }
  
  // 檢查案件狀態是否可轉換
  const canTransitionCaseStatus = (currentStatus, targetStatus) => {
    const allowedTransitions = CASE_STATUS_TRANSITIONS[currentStatus] || []
    return allowedTransitions.includes(targetStatus)
  }
  
  // 獲取狀態標籤
  const getCaseStatusLabel = (status) => {
    return CASE_STATUS_LABELS[status] || status
  }
  
  const getBankRecordStatusLabel = (status) => {
    return BANK_RECORD_STATUS_LABELS[status] || status
  }
  
  const getCommunicationTypeLabel = (type) => {
    return COMMUNICATION_TYPE_LABELS[type] || type
  }

  return {
    LEAD_STATUSES,
    LEAD_STATUS_LABELS,
    LEAD_STATUS_TRANSITIONS,
    CASE_STATUSES,
    CASE_STATUS_LABELS,
    CASE_STATUS_TRANSITIONS,
    BANK_RECORD_STATUSES,
    BANK_RECORD_STATUS_LABELS,
    COMMUNICATION_TYPES,
    COMMUNICATION_TYPE_LABELS,
    PAGINATION_OPTIONS,
    SEARCH_CONFIG,
    canTransitionCaseStatus,
    getCaseStatusLabel,
    getBankRecordStatusLabel,
    getCommunicationTypeLabel
  }
}