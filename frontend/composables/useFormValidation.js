/**
 * Form Validation Composable
 * 統一的表單驗證邏輯
 */

export const useFormValidation = () => {
  
  // 驗證貸款金額
  const validateLoanAmount = (amount) => {
    if (!amount || isNaN(Number(amount)) || Number(amount) <= 0) {
      return '請輸入有效的貸款金額'
    }
    return null
  }
  
  // 驗證必填欄位
  const validateRequired = (value, fieldName) => {
    if (!value || (typeof value === 'string' && !value.trim())) {
      return `${fieldName}為必填項目`
    }
    return null
  }
  
  // 驗證 Email 格式
  const validateEmail = (email) => {
    if (!email) return null // 可選欄位
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(email)) {
      return '請輸入有效的 Email 格式'
    }
    return null
  }
  
  // 驗證電話號碼
  const validatePhone = (phone) => {
    if (!phone) return null // 可選欄位
    const phoneRegex = /^[\d\-\+\(\)\s]+$/
    if (!phoneRegex.test(phone)) {
      return '請輸入有效的電話號碼'
    }
    return null
  }
  
  // 驗證案件表單
  const validateCaseForm = (form) => {
    const errors = {}
    
    const loanAmountError = validateLoanAmount(form.loan_amount)
    if (loanAmountError) errors.loan_amount = loanAmountError
    
    return {
      isValid: Object.keys(errors).length === 0,
      errors
    }
  }
  
  // 驗證銀行記錄表單
  const validateBankRecordForm = (form) => {
    const errors = {}
    
    const bankNameError = validateRequired(form.bank_name, '銀行名稱')
    if (bankNameError) errors.bank_name = bankNameError
    
    const contentError = validateRequired(form.content, '聯絡內容')
    if (contentError) errors.content = contentError
    
    const emailError = validateEmail(form.contact_email)
    if (emailError) errors.contact_email = emailError
    
    const phoneError = validatePhone(form.contact_phone)
    if (phoneError) errors.contact_phone = phoneError
    
    return {
      isValid: Object.keys(errors).length === 0,
      errors
    }
  }

  return {
    validateLoanAmount,
    validateRequired,
    validateEmail,
    validatePhone,
    validateCaseForm,
    validateBankRecordForm
  }
}