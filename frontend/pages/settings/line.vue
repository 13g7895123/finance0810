<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">LINE 整合</h1>
        <p class="text-gray-600 mt-2">設定 LINE BOT 整合功能</p>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <h2 class="text-xl font-semibold text-gray-900 mb-4">LINE BOT 設定</h2>
      <p class="text-gray-500">此頁面用於設定 LINE BOT 整合...</p>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth'
})

useHead({
  title: 'LINE 整合 - 金融管理系統'
})
</script>