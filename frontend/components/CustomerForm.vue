<template>
  <div class="p-4">
    <form @submit.prevent="onSubmit" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">姓名</label>
          <input v-model="form.name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">手機號碼</label>
          <input v-model="form.phone" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Email</label>
          <input v-model="form.email" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">所在地區</label>
          <input v-model="form.region" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">地址</label>
          <input v-model="form.address" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">來源管道</label>
          <select v-model="form.channel" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">未指定</option>
            <option value="wp_form">網站表單</option>
            <option value="line">官方賴</option>
            <option value="email">Email</option>
            <option value="phone_call">電話</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">網站來源 (domain)</label>
          <select v-model="form.website_source" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">請選擇網站</option>
            <option v-for="website in availableWebsites" :key="website.id" :value="website.domain">
              {{ website.name }} ({{ website.domain }})
            </option>
            <option value="_custom">手動輸入...</option>
          </select>
          <!-- 手動輸入欄位，當選擇 '_custom' 時顯示 -->
          <input 
            v-if="form.website_source === '_custom'" 
            v-model="customWebsiteInput" 
            type="text" 
            placeholder="請輸入域名..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mt-2" 
            @blur="handleCustomWebsiteInput"
          />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">備註</label>
          <textarea v-model="form.notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>
      </div>
      <div class="flex justify-end space-x-3">
        <button type="button" class="px-4 py-2 rounded border " @click="$emit('cancel')">取消</button>
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

// Point 40: Available websites for selection
const availableWebsites = ref([])
const customWebsiteInput = ref('')

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

// Point 40: Initialize API composable
const { get } = useApi()

// Point 40: Load available websites from API
const loadWebsites = async () => {
  try {
    const { data, error } = await get('/websites/options')
    if (error) {
      console.error('載入網站選項失敗:', error)
      // 如果載入失敗，仍然允許使用者手動輸入
      return
    }
    availableWebsites.value = data || []
  } catch (error) {
    console.error('載入網站選項失敗:', error)
    // 如果載入失敗，仍然允許使用者手動輸入
  }
}

// Point 40: Handle custom website input
const handleCustomWebsiteInput = () => {
  if (customWebsiteInput.value.trim()) {
    form.website_source = customWebsiteInput.value.trim()
    customWebsiteInput.value = ''
  }
}

const onSubmit = () => {
  emit('save', { ...form })
}

// Point 40: Load websites when component is mounted
onMounted(() => {
  loadWebsites()
})
</script>
