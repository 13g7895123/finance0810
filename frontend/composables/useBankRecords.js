export const useBankRecords = () => {
  const { get, post, put } = useApi()

  const list = async (params = {}) => {
    const { data, error } = await get('/bank-records', params)
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

  const createOne = async (payload) => {
    return await post('/bank-records', payload)
  }

  const updateOne = async (id, payload) => {
    return await put(`/bank-records/${id}`, payload)
  }

  return { list, createOne, updateOne }
}
