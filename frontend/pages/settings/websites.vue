<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">網站名稱管理</h1>
        <p class="text-gray-600 mt-2">管理和編輯網站名稱設定</p>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <h2 class="text-xl font-semibold text-gray-900 mb-4">網站名稱設定</h2>
      <p class="text-gray-500">此頁面用於管理網站名稱...</p>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth'
})

useHead({
  title: '網站名稱管理 - 金融管理系統'
})
</script>