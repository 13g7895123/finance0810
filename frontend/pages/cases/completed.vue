<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">已完成案件</h1>
        <p class="text-gray-600 mt-2">查看所有已完成的案件</p>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <h2 class="text-xl font-semibold text-gray-900 mb-4">已完成案件列表</h2>
      <p class="text-gray-500">此頁面用於顯示已完成的案件...</p>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth'
})

useHead({
  title: '已完成案件 - 金融管理系統'
})
</script>