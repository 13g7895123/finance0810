export const useI18n = () => {
  const locale = useState('locale', () => 'zh-TW')
  
  // Import all translations
  const translations = {
    'zh-TW': () => import('~/locales/zh-TW.json').then(m => m.default),
    'en': () => import('~/locales/en.json').then(m => m.default),
    'ja': () => import('~/locales/ja.json').then(m => m.default)
  }
  
  const messages = useState('messages', () => ({}))
  
  // Load translation messages
  const loadMessages = async (lang) => {
    if (!messages.value[lang]) {
      try {
        const msgs = await translations[lang]()
        messages.value[lang] = msgs
      } catch (error) {
        console.warn(`Failed to load translations for ${lang}:`, error)
        messages.value[lang] = {}
      }
    }
  }
  
  // Get nested object value by path
  const getValue = (obj, path) => {
    return path.split('.').reduce((current, key) => current?.[key], obj)
  }
  
  // Translation function
  const t = (key, fallback = key) => {
    const currentMessages = messages.value[locale.value]
    if (!currentMessages) return fallback
    
    const value = getValue(currentMessages, key)
    return value !== undefined ? value : fallback
  }
  
  // Available locales
  const locales = ref([
    {
      code: 'zh-TW',
      name: '繁體中文',
      flag: '🇹🇼'
    },
    {
      code: 'en', 
      name: 'English',
      flag: '🇺🇸'
    },
    {
      code: 'ja',
      name: '日本語',
      flag: '🇯🇵'
    }
  ])
  
  // Initialize with default locale
  onMounted(async () => {
    await loadMessages(locale.value)
  })
  
  // Watch locale changes and load messages
  watch(locale, async (newLocale) => {
    await loadMessages(newLocale)
    // Save to localStorage
    if (process.client) {
      localStorage.setItem('admin-template-locale', newLocale)
    }
  })
  
  // Initialize locale from localStorage
  if (process.client) {
    const savedLocale = localStorage.getItem('admin-template-locale')
    if (savedLocale && translations[savedLocale]) {
      locale.value = savedLocale
    }
  }
  
  return {
    locale,
    locales,
    t
  }
}