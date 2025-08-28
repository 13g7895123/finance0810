import { initializeApp } from 'firebase/app'
import { getDatabase } from 'firebase/database'

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  
  const firebaseConfig = {
    apiKey: config.public.firebaseApiKey || "AIzaSyBYourActualApiKeyHere", // Update with finance0810-692ec API key
    authDomain: "finance0810-692ec.firebaseapp.com",
    databaseURL: config.public.firebaseDatabaseUrl || "https://finance0810-692ec-default-rtdb.asia-southeast1.firebasedatabase.app/",
    projectId: config.public.firebaseProjectId || "finance0810-692ec", 
    storageBucket: "finance0810-692ec.firebasestorage.app",
    messagingSenderId: config.public.firebaseMessagingSenderId || "YourMessagingSenderIdHere", // Update with finance0810-692ec ID
    appId: config.public.firebaseAppId || "YourAppIdHere" // Update with finance0810-692ec App ID
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