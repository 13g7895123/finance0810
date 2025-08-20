/**
 * API Request Composable
 * 處理所有HTTP請求的統一封裝
 */

export const useApi = () => {
  const config = useRuntimeConfig()
  const router = useRouter()
  
  // 智能環境檢測
  const getApiBaseUrl = () => {
    // 優先使用環境變數設定
    if (config.public.apiBaseUrl) {
      return config.public.apiBaseUrl
    }
    
    // 開發環境自動檢測
    if (process.dev) {
      // 檢查是否在本地 Docker 環境 (finance.local)
      if (process.client && window.location.hostname === 'finance.local') {
        return 'http://finance.local/api'
      }
      // 開發環境預設使用本地 Docker API
      return 'http://finance.local/api'
    }
    
    // 生產環境預設
    return 'https://dev-finance.mercylife.cc/api'
  }
  
  const baseURL = getApiBaseUrl()

  /**
   * 通用API請求方法
   */
  const apiRequest = async (method, endpoint, data = null, options = {}) => {
    // 獲取 JWT token
    let token = null
    if (process.client) {
      const userProfile = sessionStorage.getItem('user-profile')
      if (userProfile) {
        try {
          const parsedProfile = JSON.parse(userProfile)
          token = parsedProfile.token
        } catch (error) {
          console.error('Failed to parse user profile:', error)
        }
      }
    }

    // Define requestOptions outside try block so it's accessible in catch
    const requestOptions = {
      method: method.toUpperCase(),
      baseURL,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
        ...options.headers
      },
      ...options
    }

    try {
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
      console.error(`API Request Error [${method} ${endpoint}]:`, {
        status: error.status,
        message: error.message,
        data: error.data,
        baseURL: baseURL,
        fullURL: `${baseURL}${endpoint}`,
        headers: requestOptions.headers,
        credentials: requestOptions.credentials
      })

      // 處理認證錯誤
      if (error.status === 401) {
        console.warn('Authentication failed - clearing session and redirecting to login')
        
        // Token過期，清除 sessionStorage 並重導向到登入頁
        if (process.client) {
          sessionStorage.removeItem('user-profile')
          // 清除舊的 localStorage 資料（向後相容）
          localStorage.removeItem('auth-token')
          localStorage.removeItem('admin-template-user')
          
          // 在生產環境下，檢查cookie狀況
          if (document.cookie.includes('auth-token')) {
            console.warn('Auth token cookie still exists but API returned 401')
          } else {
            console.warn('No auth token cookie found')
          }
        }
        
        await router.push('/auth/login')
      }

      return { 
        data: null, 
        error: {
          status: error.status,
          message: error.data?.message || error.message || '請求失敗',
          error: error.data?.error || error.message || '請求失敗',
          errors: error.data?.errors || null,
          debug_info: error.data?.debug_info || null,
          debug: process.dev ? {
            baseURL,
            fullURL: `${baseURL}${endpoint}`,
            method: requestOptions.method,
            error_response: error.data
          } : null
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
