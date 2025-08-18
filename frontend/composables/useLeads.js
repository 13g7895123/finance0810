export const useLeads = () => {
  const { get, put, del, post } = useApi()

  const list = async (params = {}) => {
    const { data, error } = await get('/leads', params)
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

  const getOne = async (id) => {
    return await get(`/leads/${id}`)
  }

  const updateOne = async (id, payload) => {
    return await put(`/leads/${id}`, payload)
  }

  const convertToCase = async (lead, payload) => {
    // 若 lead 有綁定 customer，直接送件
    if (!lead.customer_id) throw new Error('Lead 尚未綁定客戶，請先綁定或建立客戶')

    // 1) 建立案件
    const result = await post(`/customers/${lead.customer_id}/cases`, payload)

    // 2) 成功後，更新 lead 狀態為 submitted（已送件）
    if (!result.error) {
      await put(`/leads/${lead.id}`, { status: 'submitted' })
    }

    return result
  }

  const listSubmittable = async (params = {}) => {
    const { data, error } = await get('/leads/submittable', params)
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

  const removeOne = async (id) => {
    return await del(`/leads/${id}`)
  }

  return { list, listSubmittable, getOne, updateOne, removeOne, convertToCase }
}
