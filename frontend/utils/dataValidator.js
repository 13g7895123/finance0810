export class DataValidator {
  static validateMessage(message) {
    const errors = []
    
    if (!message.id) errors.push('Missing message ID')
    if (!message.line_user_id) errors.push('Missing line_user_id')
    if (!message.message_content) errors.push('Missing content')
    if (!message.version) errors.push('Missing version')
    
    return {
      valid: errors.length === 0,
      errors
    }
  }
  
  static validateConversation(conversation) {
    const errors = []
    
    if (!conversation.line_user_id) errors.push('Missing line_user_id')
    if (conversation.unread_count < 0) errors.push('Invalid unread count')
    
    return {
      valid: errors.length === 0,
      errors
    }
  }
  
  static validateChecksum(data, expectedChecksum) {
    const calculatedChecksum = this.calculateChecksum(data)
    return calculatedChecksum === expectedChecksum
  }
  
  static calculateChecksum(data) {
    const str = JSON.stringify(data, Object.keys(data).sort())
    let hash = 0
    for (let i = 0; i < str.length; i++) {
      const char = str.charCodeAt(i)
      hash = ((hash << 5) - hash) + char
      hash = hash & hash // 轉換為 32 位整數
    }
    return hash
  }
}