<template>
  <NuxtLayout>
    <NuxtPage :key="$route.fullPath"/>
  </NuxtLayout>
</template>

<script setup>
useHead({
  title: '融資貸款公司 CRM 系統',
  meta: [
    { name: 'description', content: '專為中租經銷商設計的融資貸款客戶關係管理系統，整合汽車、機車、手機貸款業務' },
    { name: 'color-scheme', content: 'light' }
  ],
  htmlAttrs: {
    class: 'light',
    'data-theme': 'light'
  },
  bodyAttrs: {
    class: 'light'
  }
})
</script>