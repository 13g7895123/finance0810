<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">銷售報表</h1>
        <p class="text-gray-600 mt-2">查看銷售相關統計報表</p>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <h2 class="text-xl font-semibold text-gray-900 mb-4">銷售報表</h2>
      <p class="text-gray-500">此頁面用於顯示銷售統計報表...</p>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth'
})

useHead({
  title: '銷售報表 - 金融管理系統'
})
</script>