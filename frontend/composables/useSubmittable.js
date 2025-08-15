export const useSubmittable = () => {
  const { get, post } = useApi()

  // 走後端正式 API：/customers/submittable（server-side 分頁）
  const list = async (params = {}) => {
    const { data, error } = await get('/customers/submittable', params)
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

  const submitCase = async (customerId, payload) => {
    return await post(`/customers/${customerId}/cases`, payload)
  }

  return { list, submitCase }
}
