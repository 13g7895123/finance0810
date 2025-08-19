export const useCases = () => {
  const { get, put, post } = useApi()

  const list = async (params = {}) => {
    const { data, error } = await get('/cases', params)
    if (error) return { success: false, error }
    return {
      success: true,
      items: data.data || [],
      meta: {
        total: data.total || 0,
        perPage: data.per_page || params.per_page || 15,
        currentPage: data.current_page || params.page || 1,
        lastPage: data.last_page || 1
      }
    }
  }

  const updateOne = async (id, payload) => {
    return await put(`/cases/${id}`, payload)
  }

  // Create a new case for a specific customer
  const createForCustomer = async (customerId, payload) => {
    return await post(`/customers/${customerId}/cases`, payload)
  }

  // Helper: get latest case for a customer (by created_at desc)
  const getLatestForCustomer = async (customerId) => {
    const { success, items } = await list({ customer_id: customerId, per_page: 1 })
    return success && items.length > 0 ? items[0] : null
  }

  return { list, updateOne, createForCustomer, getLatestForCustomer }
}
