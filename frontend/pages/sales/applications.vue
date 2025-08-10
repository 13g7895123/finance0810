<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">進件資料</h1>
        <p class="text-gray-600 mt-2">查看所有進件申請資料</p>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <h2 class="text-xl font-semibold text-gray-900 mb-4">進件資料列表</h2>
      <p class="text-gray-500">此頁面用於顯示所有進件申請資料...</p>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth'
})

useHead({
  title: '進件資料 - 金融管理系統'
})
</script>