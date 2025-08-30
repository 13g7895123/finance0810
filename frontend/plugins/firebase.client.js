import { initializeApp } from 'firebase/app'
import { getDatabase } from 'firebase/database'

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  
  console.log('Firebase plugin loading...')
  console.log('Runtime config available:', !!config.public)
  console.log('Firebase config from env:', {
    apiKey: config.public.firebaseApiKey ? 'configured' : 'missing',
    databaseUrl: config.public.firebaseDatabaseUrl ? 'configured' : 'missing', 
    projectId: config.public.firebaseProjectId ? 'configured' : 'missing',
    messagingSenderId: config.public.firebaseMessagingSenderId ? 'configured' : 'missing',
    appId: config.public.firebaseAppId ? 'configured' : 'missing'
  })
  
  const firebaseConfig = {
    apiKey: config.public.firebaseApiKey || "AIzaSyAdMi6mBkOW8apD7kyaAObkuZNHBwNkwK8",
    authDomain: "finance0810new.firebaseapp.com", 
    databaseURL: config.public.firebaseDatabaseUrl || "https://finance0810new-default-rtdb.asia-southeast1.firebasedatabase.app/",
    projectId: config.public.firebaseProjectId || "finance0810new", 
    storageBucket: "finance0810new.firebasestorage.app",
    messagingSenderId: config.public.firebaseMessagingSenderId || "1037882716873",
    appId: config.public.firebaseAppId || "1:1037882716873:web:d572d3adcc0b4e7c479318"
  }
  
  console.log('Final Firebase config:', {
    apiKey: firebaseConfig.apiKey.substring(0, 10) + '...',
    authDomain: firebaseConfig.authDomain,
    databaseURL: firebaseConfig.databaseURL,
    projectId: firebaseConfig.projectId,
    storageBucket: firebaseConfig.storageBucket,
    messagingSenderId: firebaseConfig.messagingSenderId,
    appId: firebaseConfig.appId.substring(0, 15) + '...'
  })

  try {
    // 檢查必要的配置是否存在
    if (!firebaseConfig.apiKey || firebaseConfig.apiKey.includes('NEEDED') ||
        !firebaseConfig.messagingSenderId || firebaseConfig.messagingSenderId.includes('NEEDED') ||
        !firebaseConfig.appId || firebaseConfig.appId.includes('NEEDED')) {
      console.warn('Firebase configuration incomplete. Please check environment variables:')
      console.warn('- NUXT_FIREBASE_API_KEY')
      console.warn('- NUXT_FIREBASE_MESSAGING_SENDER_ID')
      console.warn('- NUXT_FIREBASE_APP_ID')
      throw new Error('Firebase configuration incomplete')
    }
    
    const app = initializeApp(firebaseConfig)
    const database = getDatabase(app)

    console.log('Firebase Realtime Database initialized successfully')
    console.log('Database URL:', firebaseConfig.databaseURL)
    
    return {
      provide: {
        firebase: app,
        firebaseDB: database
      }
    }
  } catch (error) {
    console.error('Firebase initialization failed:', error)
    console.warn('Chat system will not work without Firebase. Please configure Firebase properly.')
    
    return {
      provide: {
        firebase: null,
        firebaseDB: null
      }
    }
  }
})