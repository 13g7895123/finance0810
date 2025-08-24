import { initializeApp } from 'firebase/app'
import { getDatabase } from 'firebase/database'

export default defineNuxtPlugin(() => {
  const firebaseConfig = {
    // 注意：需要從 Firebase Console 的 Project Settings > General > Your apps 獲取正確的配置
    // 以下是基於專案 ID 的預設配置，但 apiKey, messagingSenderId, appId 需要從 Firebase Console 獲取
    apiKey: process.env.NUXT_FIREBASE_API_KEY || "AIzaSyCONFIG_NEEDED_FROM_FIREBASE_CONSOLE",
    authDomain: "finance0810new.firebaseapp.com",
    databaseURL: process.env.NUXT_FIREBASE_DATABASE_URL || "https://finance0810new-default-rtdb.asia-southeast1.firebasedatabase.app/",
    projectId: "finance0810new", 
    storageBucket: "finance0810new.appspot.com",
    messagingSenderId: process.env.NUXT_FIREBASE_MESSAGING_SENDER_ID || "SENDER_ID_NEEDED",
    appId: process.env.NUXT_FIREBASE_APP_ID || "APP_ID_NEEDED"
  }

  try {
    const app = initializeApp(firebaseConfig)
    const database = getDatabase(app)

    console.log('Firebase initialized successfully with Realtime Database')
    
    return {
      provide: {
        firebase: app,
        firebaseDB: database  // 現在是 Realtime Database
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