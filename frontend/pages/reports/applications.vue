<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">進件統計</h1>
        <p class="text-gray-600 mt-2">查看進件申請統計報表</p>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <h2 class="text-xl font-semibold text-gray-900 mb-4">進件統計報表</h2>
      <p class="text-gray-500">此頁面用於顯示進件統計...</p>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth'
})

useHead({
  title: '進件統計 - 金融管理系統'
})
</script>