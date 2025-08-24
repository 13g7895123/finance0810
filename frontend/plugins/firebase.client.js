import { initializeApp } from 'firebase/app'
import { getDatabase } from 'firebase/database'

export default defineNuxtPlugin(() => {
  const firebaseConfig = {
    // 這些配置需要從環境變量或配置文件中獲取
    apiKey: "your-api-key",
    authDomain: "finance0810-692ec.firebaseapp.com", 
    databaseURL: "https://finance0810-692ec-default-rtdb.firebaseio.com/",
    projectId: "finance0810-692ec",
    storageBucket: "finance0810-692ec.appspot.com",
    messagingSenderId: "your-sender-id",
    appId: "your-app-id"
  }

  try {
    const app = initializeApp(firebaseConfig)
    const database = getDatabase(app)

    return {
      provide: {
        firebase: app,
        firebaseDB: database
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