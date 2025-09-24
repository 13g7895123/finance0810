<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="text-center">
      <h1 class="text-2xl font-bold text-gray-900 mb-4">編輯案件</h1>
      <p class="text-gray-600 mb-6">請選擇要編輯的案件</p>
      <NuxtLink
        to="/cases/pending"
        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
      >
        返回案件列表
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth',
  title: '編輯案件'
})

// 自動重定向到案件列表頁面
onMounted(() => {
  setTimeout(() => {
    navigateTo('/cases/pending')
  }, 2000)
})

useHead({
  title: '編輯案件 - 案件管理系統'
})
</script>