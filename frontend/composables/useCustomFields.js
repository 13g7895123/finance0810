export const useCustomFields = () => {
  const { get, post, put, del } = useApi()

  // 取得自定義欄位定義
  const list = async (entityType = 'lead') => {
    const { data, error } = await get('/custom-fields', { entity_type: entityType })
    if (error) return { success: false, error }
    // 排序
    const items = Array.isArray(data) ? data.sort((a,b) => (a.sort_order||0) - (b.sort_order||0)) : []
    return { success: true, items }
  }

  const create = async (payload) => {
    return await post('/custom-fields', payload)
  }

  const update = async (id, payload) => {
    return await put(`/custom-fields/${id}`, payload)
  }

  const remove = async (id) => {
    return await del(`/custom-fields/${id}`)
  }

  // 設定欄位值（用於個別 entity）
  const setValue = async ({ entity_type, entity_id, key, value }) => {
    return await post('/custom-fields/set-value', { entity_type, entity_id, key, value })
  }

  return { list, create, update, remove, setValue }
}
