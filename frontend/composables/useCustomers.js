/**
 * Customer Management Composable
 * 客戶管理相關API封裝
 */

export const useCustomers = () => {
  const { get, post, put, del, patch } = useApi()

  /**
   * 獲取客戶列表
   */
  const getCustomers = async (params = {}) => {
    return await get('/customers', params)
  }

  /**
   * 獲取客戶詳情
   */
  const getCustomer = async (id) => {
    return await get(`/customers/${id}`)
  }

  /**
   * 創建新客戶
   */
  const createCustomer = async (customerData) => {
    return await post('/customers', customerData)
  }

  /**
   * 更新客戶資料
   */
  const updateCustomer = async (id, customerData) => {
    return await put(`/customers/${id}`, customerData)
  }

  /**
   * 刪除客戶
   */
  const deleteCustomer = async (id) => {
    return await del(`/customers/${id}`)
  }

  /**
   * 分配客戶給業務人員
   */
  const assignCustomer = async (id, assignedTo) => {
    return await post(`/customers/${id}/assign`, { assigned_to: assignedTo })
  }

  /**
   * 設定客戶追蹤日期
   */
  const setTrackingDate = async (id, trackingData) => {
    return await post(`/customers/${id}/track-date`, trackingData)
  }

  /**
   * 更新客戶狀態
   */
  const updateCustomerStatus = async (id, statusData) => {
    return await post(`/customers/${id}/status`, statusData)
  }

  /**
   * 獲取客戶歷史記錄
   */
  const getCustomerHistory = async (id) => {
    return await get(`/customers/${id}/history`)
  }

  /**
   * 綁定客戶LINE用戶
   */
  const linkLineUser = async (id, lineData) => {
    return await post(`/customers/${id}/link-line`, lineData)
  }

  /**
   * 解除客戶LINE用戶綁定
   */
  const unlinkLineUser = async (id) => {
    return await del(`/customers/${id}/unlink-line`)
  }

  /**
   * 檢查LINE好友狀態
   */
  const checkLineFriendStatus = async (id) => {
    return await get(`/customers/${id}/line/friend-status`)
  }

  /**
   * 獲取客戶狀態選項
   */
  const getStatusOptions = () => {
    return {
      'new': '新客戶',
      'contacted': '已聯絡',
      'interested': '有興趣',
      'not_interested': '無興趣',
      'invalid': '無效客戶',
      'converted': '已成交'
    }
  }

  /**
   * 獲取追蹤狀態選項
   */
  const getTrackingStatusOptions = () => {
    return {
      'pending': '待處理',
      'scheduled': '已排程',
      'contacted': '已聯絡',
      'follow_up': '需追蹤',
      'completed': '已完成'
    }
  }

  /**
   * 獲取案件狀態選項
   */
  const getCaseStatusOptions = () => {
    return {
      null: '無案件',
      'submitted': '已送件',
      'approved': '已核准',
      'rejected': '已婉拒',
      'disbursed': '已撥款'
    }
  }

  return {
    getCustomers,
    getCustomer,
    createCustomer,
    updateCustomer,
    deleteCustomer,
    assignCustomer,
    setTrackingDate,
    updateCustomerStatus,
    getCustomerHistory,
    linkLineUser,
    unlinkLineUser,
    checkLineFriendStatus,
    getStatusOptions,
    getTrackingStatusOptions,
    getCaseStatusOptions
  }
}