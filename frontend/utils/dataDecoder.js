/**
 * 數據解碼工具 - 將優化的字段名還原為完整字段名
 */

/**
 * 解碼聊天消息數據
 */
export const decodeChatData = (encoded) => {
  if (!encoded || typeof encoded !== 'object') {
    return null
  }
  
  return {
    id: encoded.i,
    line_user_id: encoded.u,
    message_content: encoded.c,
    message_timestamp: encoded.t,
    is_from_customer: encoded.f,
    status: encoded.s,
    version: encoded.v,
    customer_name: encoded.n,
    customer_phone: encoded.p,
    unread_count: encoded.r || 0,
    message_type: encoded.mt || 'text',
    attachments: encoded.at || null
  }
}

/**
 * 解碼對話列表數據
 */
export const decodeConversationData = (encoded) => {
  if (!encoded || typeof encoded !== 'object') {
    return null
  }
  
  return {
    id: encoded.i,
    line_user_id: encoded.u,
    customer_name: encoded.n,
    customer_phone: encoded.p,
    last_message: encoded.lm,
    last_message_time: encoded.lt,
    unread_count: encoded.r || 0,
    status: encoded.s,
    version: encoded.v,
    version_updated_at: encoded.vt
  }
}

/**
 * 解碼消息列表數據
 */
export const decodeMessageData = (encoded) => {
  if (!encoded || typeof encoded !== 'object') {
    return null
  }
  
  return {
    id: encoded.i,
    line_user_id: encoded.u,
    message_content: encoded.c,
    message_timestamp: encoded.t,
    is_from_customer: encoded.f,
    status: encoded.s,
    version: encoded.v,
    message_type: encoded.mt || 'text',
    attachments: encoded.at || null
  }
}

/**
 * 批量解碼數據數組
 */
export const decodeBatchData = (encodedArray, decoder) => {
  if (!Array.isArray(encodedArray)) {
    return []
  }
  
  return encodedArray.map(item => decoder(item)).filter(Boolean)
}

/**
 * 編碼數據 - 將完整字段名壓縮為短字段名
 */
export const encodeChatData = (data) => {
  if (!data || typeof data !== 'object') {
    return null
  }
  
  return {
    i: data.id,
    u: data.line_user_id,
    c: data.message_content,
    t: data.message_timestamp,
    f: data.is_from_customer,
    s: data.status,
    v: data.version,
    n: data.customer_name,
    p: data.customer_phone,
    r: data.unread_count || 0,
    mt: data.message_type || 'text',
    at: data.attachments || null
  }
}

/**
 * 計算數據壓縮率
 */
export const calculateCompressionRatio = (original, compressed) => {
  const originalSize = JSON.stringify(original).length
  const compressedSize = JSON.stringify(compressed).length
  
  return {
    originalSize,
    compressedSize,
    ratio: ((originalSize - compressedSize) / originalSize * 100).toFixed(2) + '%',
    savings: originalSize - compressedSize
  }
}

/**
 * 數據完整性檢查
 */
export const validateDecodedData = (decoded, requiredFields = []) => {
  if (!decoded || typeof decoded !== 'object') {
    return { valid: false, errors: ['Invalid data structure'] }
  }
  
  const errors = []
  
  for (const field of requiredFields) {
    if (decoded[field] === undefined || decoded[field] === null) {
      errors.push(`Missing required field: ${field}`)
    }
  }
  
  return {
    valid: errors.length === 0,
    errors
  }
}

/**
 * 智能解碼器 - 自動檢測數據類型並應用對應解碼器
 */
export const smartDecode = (data) => {
  if (!data || typeof data !== 'object') {
    return data
  }
  
  // 檢測是否為編碼數據
  if (data.i !== undefined && data.u !== undefined) {
    // 檢測數據類型
    if (data.lm !== undefined || data.lt !== undefined) {
      // 對話數據
      return decodeConversationData(data)
    } else if (data.c !== undefined || data.t !== undefined) {
      // 消息數據
      return decodeChatData(data)
    }
  }
  
  // 原始數據，直接返回
  return data
}

/**
 * 批量智能解碼
 */
export const smartDecodeBatch = (dataArray) => {
  if (!Array.isArray(dataArray)) {
    return dataArray
  }
  
  return dataArray.map(item => smartDecode(item))
}