<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">協商客戶</h1>
        <p class="text-gray-600 mt-2">查看正在協商的客戶案件</p>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <h2 class="text-xl font-semibold text-gray-900 mb-4">協商客戶列表</h2>
      <p class="text-gray-500">此頁面用於顯示正在協商的客戶...</p>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth'
})

useHead({
  title: '協商客戶 - 金融管理系統'
})
</script>