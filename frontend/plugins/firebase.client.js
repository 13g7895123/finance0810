import { initializeApp } from 'firebase/app'
import { getFirestore } from 'firebase/firestore'

export default defineNuxtPlugin(() => {
  const firebaseConfig = {
    // 注意：需要從 Firebase Console 的 Project Settings > General > Your apps 獲取正確的配置
    // 以下是基於專案 ID 的預設配置，但 apiKey, messagingSenderId, appId 需要從 Firebase Console 獲取
    apiKey: process.env.NUXT_FIREBASE_API_KEY || "AIzaSyCONFIG_NEEDED_FROM_FIREBASE_CONSOLE",
    authDomain: "finance0810-692ec.firebaseapp.com",
    projectId: "finance0810-692ec", 
    storageBucket: "finance0810-692ec.appspot.com",
    messagingSenderId: process.env.NUXT_FIREBASE_MESSAGING_SENDER_ID || "SENDER_ID_NEEDED",
    appId: process.env.NUXT_FIREBASE_APP_ID || "APP_ID_NEEDED"
  }

  try {
    const app = initializeApp(firebaseConfig)
    const firestore = getFirestore(app)

    console.log('Firebase initialized successfully with Firestore')
    
    return {
      provide: {
        firebase: app,
        firebaseDB: firestore  // 為了保持向後兼容，仍使用 firebaseDB 名稱，但實際是 Firestore
      }
    }
  } catch (error) {
    console.warn('Firebase initialization failed:', error)
    // 返回null作為fallback，讓應用繼續運行
    return {
      provide: {
        firebase: null,
        firebaseDB: null
      }
    }
  }
})