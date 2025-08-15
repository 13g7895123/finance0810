<template>
  <div class="p-4">
    <form @submit.prevent="onSubmit" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">姓名</label>
          <input v-model="form.name" type="text" class="w-full px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" required />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">手機號碼</label>
          <input v-model="form.phone" type="text" class="w-full px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" required />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Email</label>
          <input v-model="form.email" type="email" class="w-full px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">所在地區</label>
          <input v-model="form.region" type="text" class="w-full px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">地址</label>
          <input v-model="form.address" type="text" class="w-full px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">來源管道</label>
          <select v-model="form.channel" class="w-full px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700">
            <option value="">未指定</option>
            <option value="wp_form">WP 表單</option>
            <option value="line">LINE OA</option>
            <option value="email">Email</option>
            <option value="phone_call">電話</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">網站來源 (domain)</label>
          <input v-model="form.website_source" type="text" class="w-full px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">備註</label>
          <textarea v-model="form.notes" rows="3" class="w-full px-3 py-2 border rounded dark:bg-gray-800 dark:border-gray-700" />
        </div>
      </div>
      <div class="flex justify-end space-x-3">
        <button type="button" class="px-4 py-2 rounded border dark:border-gray-700" @click="$emit('cancel')">取消</button>
        <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white">儲存</button>
      </div>
    </form>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: Object, default: () => ({}) }
})
const emit = defineEmits(['save','cancel'])

const form = reactive({
  id: null,
  name: '',
  phone: '',
  email: '',
  region: '',
  address: '',
  channel: 'wp_form',
  website_source: '',
  notes: ''
})

watch(() => props.modelValue, (v) => {
  Object.assign(form, {
    id: v?.id ?? null,
    name: v?.name ?? '',
    phone: v?.phone ?? '',
    email: v?.email ?? '',
    region: v?.region ?? '',
    address: v?.address ?? '',
    channel: v?.channel ?? 'wp_form',
    website_source: v?.website_source ?? '',
    notes: v?.notes ?? ''
  })
}, { immediate: true })

const onSubmit = () => {
  emit('save', { ...form })
}
</script>
