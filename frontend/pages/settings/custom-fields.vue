<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 ">自定義欄位（案件）</h1>
        <p class="text-gray-600 mt-2">管理 entity_type = case 的欄位（支援：文字、文字區塊、數字、小數、日期、單選、多選、是/否）</p>
      </div>
      <button @click="openCreate" class="px-3 py-2 bg-blue-600 text-white rounded">新增欄位</button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900 ">欄位清單</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 ">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">排序</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">名稱</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">鍵值 Key</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">類型</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">必填</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">顯示</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200 ">
            <tr v-if="loading"><td colspan="7" class="px-6 py-6 text-center text-gray-500">載入中...</td></tr>
            <tr v-for="f in fields" :key="f.id">
              <td class="px-6 py-3">{{ f.sort_order }}</td>
              <td class="px-6 py-3">{{ f.label }}</td>
              <td class="px-6 py-3">{{ f.key }}</td>
              <td class="px-6 py-3">{{ (FIELD_TYPES.find(t => t.value === f.type)?.label) || f.type }}</td>
              <td class="px-6 py-3">{{ f.is_required ? '是' : '否' }}</td>
              <td class="px-6 py-3">{{ f.is_visible ? '是' : '否' }}</td>
              <td class="px-6 py-3 space-x-2">
                <button class="px-2 py-1 border rounded" @click="openEdit(f)">編輯</button>
                <button class="px-2 py-1 border rounded text-red-600" @click="remove(f)">刪除</button>
              </td>
            </tr>
            <tr v-if="!loading && fields.length === 0"><td colspan="7" class="px-6 py-6 text-center text-gray-500">沒有資料</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 表單 Modal -->
    <div v-if="formOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeForm">
      <div class="bg-white rounded-lg p-6 w-full max-w-xl">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ form.id ? '編輯欄位' : '新增欄位' }}</h3>
        <form @submit.prevent="save" class="space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm mb-1">顯示名稱 (label)</label>
              <input v-model="form.label" required class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm mb-1">鍵值 Key</label>
              <input v-model="form.key" :disabled="!!form.id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm mb-1">類型</label>
              <select v-model="form.type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option v-for="t in FIELD_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
              </select>
            </div>
            <div>
              <label class="inline-flex items-center mt-7">
                <input type="checkbox" v-model="form.is_required" class="mr-2" /> 必填
              </label>
            </div>
            <div>
              <label class="block text-sm mb-1">排序</label>
              <input v-model.number="form.sort_order" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="inline-flex items-center mt-7">
                <input type="checkbox" v-model="form.is_visible" class="mr-2" /> 顯示
              </label>
            </div>
          </div>

          <!-- 選項（select/multiselect） -->
          <div v-if="['select','multiselect'].includes(form.type)">
            <label class="block text-sm mb-1">選項 (以逗號分隔)</label>
            <input v-model="optionsText" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="例如：高,中,低" />
          </div>

          <div class="flex justify-end space-x-3 pt-2">
            <button type="button" class="px-4 py-2 border rounded " @click="closeForm">取消</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded" :disabled="saving">{{ saving ? '儲存中...' : '儲存' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({ middleware: 'auth' })

const { list, create, update, remove: removeFieldApi } = useCustomFields()

const FIELD_TYPES = [
  { value: 'text', label: '文字' },
  { value: 'textarea', label: '文字區塊' },
  { value: 'number', label: '數字' },
  { value: 'decimal', label: '小數' },
  { value: 'date', label: '日期' },
  { value: 'select', label: '單選' },
  { value: 'multiselect', label: '多選' },
  { value: 'boolean', label: '是/否' }
]

const fields = ref([])
const loading = ref(false)
const formOpen = ref(false)
const saving = ref(false)
const optionsText = ref('')

const form = reactive({ id: null, entity_type: 'case', key: '', label: '', type: 'text', is_required: false, is_visible: true, sort_order: 0, options: [] })

const load = async () => {
  loading.value = true
  const { success, items } = await list('case')
  if (success) fields.value = items
  loading.value = false
}

onMounted(load)

const openCreate = () => {
  Object.assign(form, { id: null, entity_type: 'case', key: '', label: '', type: 'text', is_required: false, is_visible: true, sort_order: 0, options: [] })
  optionsText.value = ''
  formOpen.value = true
}

const openEdit = (f) => {
  Object.assign(form, { id: f.id, entity_type: 'case', key: f.key, label: f.label, type: f.type, is_required: !!f.is_required, is_visible: !!f.is_visible, sort_order: f.sort_order || 0, options: f.options || [] })
  optionsText.value = Array.isArray(f.options) ? f.options.join(',') : ''
  formOpen.value = true
}

const closeForm = () => { formOpen.value = false }

const save = async () => {
  saving.value = true
  try {
    const payload = {
      entity_type: 'case',
      key: form.key,
      label: form.label,
      type: form.type,
      is_required: !!form.is_required,
      is_visible: !!form.is_visible,
      sort_order: Number(form.sort_order) || 0,
      options: ['select','multiselect'].includes(form.type)
        ? optionsText.value.split(',').map(s => s.trim()).filter(Boolean)
        : []
    }
    let resp
    if (form.id) {
      resp = await update(form.id, payload)
    } else {
      resp = await create(payload)
    }
    if (!resp.error) {
      formOpen.value = false
      await load()
    }
  } finally {
    saving.value = false
  }
}

const removeField = async (f) => {
  if (!confirm('確定刪除該欄位？')) return
  const { error } = await removeFieldApi(f.id)
  if (!error) await load()
}

// 暴露方法
const remove = removeField
</script>
