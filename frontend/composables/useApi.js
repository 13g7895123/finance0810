/**
 * API Request Composable
 * 處理所有HTTP請求的統一封裝
 */

export const useApi = () => {
  const config = useRuntimeConfig()
  const router = useRouter()
  
  const baseURL = config.public.apiBaseUrl || 'http://localhost:8000/api'

  /**
   * 通用API請求方法
   */
  const apiRequest = async (method, endpoint, data = null, options = {}) => {
    try {
      const token = useCookie('auth-token')
      
      const requestOptions = {
        method: method.toUpperCase(),
        baseURL,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...options.headers
        },
        ...options
      }

      // 添加認證Token
      if (token.value) {
        requestOptions.headers.Authorization = `Bearer ${token.value}`
      }

      // 添加請求資料
      if (data && ['POST', 'PUT', 'PATCH'].includes(requestOptions.method)) {
        requestOptions.body = JSON.stringify(data)
      } else if (data && requestOptions.method === 'GET') {
        // 將參數轉換為查詢字串
        const params = new URLSearchParams(data)
        endpoint += `?${params.toString()}`
      }

      const response = await $fetch(endpoint, requestOptions)
      return { data: response, error: null }

    } catch (error) {
      console.error(`API Request Error [${method} ${endpoint}]:`, error)

      // 處理認證錯誤
      if (error.status === 401) {
        // Token過期，清除並重導向到登入頁
        const token = useCookie('auth-token')
        token.value = null
        await router.push('/auth/login')
      }

      return { 
        data: null, 
        error: {
          status: error.status,
          message: error.data?.message || error.message || '請求失敗',
          errors: error.data?.errors || null
        }
      }
    }
  }

  /**
   * GET請求
   */
  const get = async (endpoint, params = {}, options = {}) => {
    return await apiRequest('GET', endpoint, params, options)
  }

  /**
   * POST請求
   */
  const post = async (endpoint, data = {}, options = {}) => {
    return await apiRequest('POST', endpoint, data, options)
  }

  /**
   * PUT請求
   */
  const put = async (endpoint, data = {}, options = {}) => {
    return await apiRequest('PUT', endpoint, data, options)
  }

  /**
   * DELETE請求
   */
  const del = async (endpoint, options = {}) => {
    return await apiRequest('DELETE', endpoint, null, options)
  }

  /**
   * PATCH請求
   */
  const patch = async (endpoint, data = {}, options = {}) => {
    return await apiRequest('PATCH', endpoint, data, options)
  }

  return {
    apiRequest,
    get,
    post,
    put,
    del,
    patch
  }
}