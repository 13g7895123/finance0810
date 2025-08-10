<template>
  <div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg-custom shadow-sm p-6">
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
        設定
      </h2>
      <p class="text-gray-600 dark:text-gray-300 mb-6">
        管理您的應用程式設定和偏好。
      </p>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <NuxtLink
          to="/settings/theme"
          class="block p-6 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-primary-500 transition-colors duration-200"
        >
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            主題設定
          </h3>
          <p class="text-gray-600 dark:text-gray-300 text-sm">
            自定義顏色主題和顯示模式
          </p>
        </NuxtLink>
        
        <div class="block p-6 border border-gray-200 dark:border-gray-700 rounded-lg">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            一般設定
          </h3>
          <p class="text-gray-600 dark:text-gray-300 text-sm">
            基本應用程式設定
          </p>
        </div>
        
        <div class="block p-6 border border-gray-200 dark:border-gray-700 rounded-lg">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            用戶管理
          </h3>
          <p class="text-gray-600 dark:text-gray-300 text-sm">
            管理用戶帳戶和權限
          </p>
        </div>
      </div>
    </div>
  </div>
</template>