import { initializeApp } from 'firebase/app'
import { getDatabase } from 'firebase/database'

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  
  const firebaseConfig = {
    apiKey: config.public.firebaseApiKey || "AIzaSyCONFIG_NEEDED", // Update with finance0810new API key from Firebase Console
    authDomain: "finance0810new.firebaseapp.com",
    databaseURL: config.public.firebaseDatabaseUrl || "https://finance0810new-default-rtdb.asia-southeast1.firebasedatabase.app/",
    projectId: config.public.firebaseProjectId || "finance0810new", 
    storageBucket: "finance0810new.firebasestorage.app",
    messagingSenderId: config.public.firebaseMessagingSenderId || "SENDER_ID_NEEDED", // Update with finance0810new Sender ID
    appId: config.public.firebaseAppId || "APP_ID_NEEDED" // Update with finance0810new App ID
  }

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