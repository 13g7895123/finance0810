<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">追蹤行事曆</h1>
        <p class="text-gray-600 dark:text-gray-300 mt-2">管理客戶聯絡排程和追蹤提醒</p>
      </div>
      <div class="flex gap-4">
        <!-- 新增聯絡計畫按鈕 -->
        <UButton 
          icon="i-heroicons-plus"
          @click="showAddModal = true"
          color="primary"
        >
          新增聯絡計畫
        </UButton>
      </div>
    </div>

    <!-- 統計卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">今日計畫</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.today }}</p>
          </div>
          <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
            <Icon name="i-heroicons-calendar-days" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">逾期未聯絡</p>
            <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.overdue }}</p>
          </div>
          <div class="p-3 bg-red-100 dark:bg-red-900 rounded-full">
            <Icon name="i-heroicons-exclamation-triangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">需要提醒</p>
            <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ stats.needReminder }}</p>
          </div>
          <div class="p-3 bg-yellow-100 dark:bg-yellow-900 rounded-full">
            <Icon name="i-heroicons-bell" class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">本月完成</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.completed }}</p>
          </div>
          <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
            <Icon name="i-heroicons-check-circle" class="w-6 h-6 text-green-600 dark:text-green-400" />
          </div>
        </div>
      </div>
    </div>

    <!-- 行事曆主體 -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
      <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
              {{ currentMonth }}年{{ currentMonthNumber }}月
            </h2>
            <div class="flex gap-2">
              <UButton
                icon="i-heroicons-chevron-left"
                variant="ghost"
                size="sm"
                @click="previousMonth"
              />
              <UButton
                icon="i-heroicons-chevron-right"
                variant="ghost"
                size="sm"
                @click="nextMonth"
              />
            </div>
          </div>
          <div class="flex gap-2">
            <UButton
              variant="ghost"
              size="sm"
              @click="goToToday"
            >
              今天
            </UButton>
          </div>
        </div>
      </div>

      <!-- 行事曆格線 -->
      <div class="p-6">
        <!-- 星期標題 -->
        <div class="grid grid-cols-7 gap-1 mb-4">
          <div v-for="day in weekDays" :key="day" class="p-2 text-center text-sm font-medium text-gray-500 dark:text-gray-400">
            {{ day }}
          </div>
        </div>

        <!-- 日期格線 -->
        <div class="grid grid-cols-7 gap-1">
          <div
            v-for="(date, index) in calendarDates"
            :key="index"
            class="min-h-[120px] p-2 border border-gray-100 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer"
            :class="{
              'bg-gray-50 dark:bg-gray-800': !date.isCurrentMonth,
              'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-700': date.isToday,
              'text-gray-400 dark:text-gray-600': !date.isCurrentMonth
            }"
            @click="selectDate(date)"
          >
            <div class="text-sm font-medium mb-1" :class="{ 
              'text-blue-600 dark:text-blue-400 font-bold': date.isToday,
              'text-gray-400 dark:text-gray-600': !date.isCurrentMonth,
              'text-gray-900 dark:text-white': date.isCurrentMonth && !date.isToday
            }">
              {{ date.date }}
            </div>
            
            <!-- 聯絡計畫項目 -->
            <div class="space-y-1">
              <div
                v-for="schedule in getSchedulesForDate(date.fullDate)"
                :key="schedule.id"
                class="text-xs p-1 rounded truncate cursor-pointer"
                :class="getScheduleClass(schedule)"
                @click.stop="showScheduleDetail(schedule)"
              >
                <div class="font-medium">{{ schedule.customer?.name || '未知客戶' }}</div>
                <div class="opacity-75">{{ schedule.scheduled_time || '全天' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 新增聯絡計畫模態框 -->
    <UModal v-model="showAddModal" :ui="{ width: 'sm:max-w-lg' }">
      <div class="p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">新增聯絡計畫</h3>
        <UForm :state="formData" @submit="submitSchedule">
          <div class="space-y-4">
            <UFormGroup label="客戶" name="customer_id">
              <USelectMenu
                v-model="formData.customer_id"
                :options="customers"
                option-attribute="name"
                value-attribute="id"
                placeholder="選擇客戶"
                searchable
                :loading="customersLoading"
              />
            </UFormGroup>

            <UFormGroup label="聯絡日期" name="scheduled_date">
              <UInput
                v-model="formData.scheduled_date"
                type="date"
                :min="new Date().toISOString().split('T')[0]"
              />
            </UFormGroup>

            <UFormGroup label="聯絡時間" name="scheduled_time">
              <UInput
                v-model="formData.scheduled_time"
                type="time"
                placeholder="選填"
              />
            </UFormGroup>

            <UFormGroup label="聯絡方式" name="contact_type">
              <USelectMenu
                v-model="formData.contact_type"
                :options="contactTypes"
                placeholder="選擇聯絡方式"
              />
            </UFormGroup>

            <UFormGroup label="優先級" name="priority">
              <USelectMenu
                v-model="formData.priority"
                :options="priorities"
                placeholder="選擇優先級"
              />
            </UFormGroup>

            <UFormGroup label="備註" name="notes">
              <UTextarea
                v-model="formData.notes"
                placeholder="輸入備註..."
                :rows="3"
              />
            </UFormGroup>

            <div class="flex gap-3 justify-end pt-4">
              <UButton variant="ghost" @click="showAddModal = false">
                取消
              </UButton>
              <UButton type="submit" :loading="submitting">
                建立計畫
              </UButton>
            </div>
          </div>
        </UForm>
      </div>
    </UModal>

    <!-- 聯絡計畫詳情模態框 -->
    <UModal v-model="showDetailModal" :ui="{ width: 'sm:max-w-2xl' }">
      <div class="p-6" v-if="selectedSchedule">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">聯絡計畫詳情</h3>
          <div class="flex gap-2">
            <UButton
              v-if="selectedSchedule.status === 'scheduled'"
              size="xs"
              color="green"
              @click="markAsContacted"
            >
              標記已聯絡
            </UButton>
            <UButton
              v-if="selectedSchedule.status === 'scheduled'"
              size="xs"
              color="orange"
              @click="showRescheduleModal = true"
            >
              改約時間
            </UButton>
          </div>
        </div>

        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-500 dark:text-gray-400">客戶姓名</label>
              <p class="text-gray-900 dark:text-white">{{ selectedSchedule.customer?.name || '未知客戶' }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500 dark:text-gray-400">客戶等級</label>
              <p class="text-gray-900 dark:text-white">{{ selectedSchedule.customer?.level || '未設定' }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Line 顯示名稱</label>
              <p class="text-gray-900 dark:text-white">{{ selectedSchedule.customer?.line_display_name || '無' }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500 dark:text-gray-400">聯絡電話</label>
              <p class="text-gray-900 dark:text-white">{{ selectedSchedule.customer?.phone || '無' }}</p>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-500 dark:text-gray-400">預計日期</label>
              <p class="text-gray-900 dark:text-white">{{ formatDate(selectedSchedule.scheduled_date) }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500 dark:text-gray-400">預計時間</label>
              <p class="text-gray-900 dark:text-white">{{ selectedSchedule.scheduled_time || '全天' }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500 dark:text-gray-400">聯絡方式</label>
              <p class="text-gray-900 dark:text-white">{{ getContactTypeLabel(selectedSchedule.contact_type) }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-500 dark:text-gray-400">狀態</label>
              <UBadge :color="getStatusColor(selectedSchedule.status)">
                {{ getStatusLabel(selectedSchedule.status) }}
              </UBadge>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500 dark:text-gray-400">優先級</label>
              <UBadge :color="getPriorityColor(selectedSchedule.priority)">
                {{ getPriorityLabel(selectedSchedule.priority) }}
              </UBadge>
            </div>
          </div>

          <div v-if="selectedSchedule.notes">
            <label class="text-sm font-medium text-gray-500 dark:text-gray-400">備註</label>
            <p class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ selectedSchedule.notes }}</p>
          </div>

          <div v-if="selectedSchedule.actual_contact_at">
            <label class="text-sm font-medium text-gray-500 dark:text-gray-400">實際聯絡時間</label>
            <p class="text-gray-900 dark:text-white">{{ formatDateTime(selectedSchedule.actual_contact_at) }}</p>
          </div>
        </div>

        <div class="flex gap-3 justify-end pt-6">
          <UButton variant="ghost" @click="showDetailModal = false">
            關閉
          </UButton>
        </div>
      </div>
    </UModal>

    <!-- 改約模態框 -->
    <UModal v-model="showRescheduleModal" :ui="{ width: 'sm:max-w-md' }">
      <div class="p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">改約時間</h3>
        <UForm :state="rescheduleData" @submit="submitReschedule">
          <div class="space-y-4">
            <UFormGroup label="新日期" name="new_date">
              <UInput
                v-model="rescheduleData.new_date"
                type="date"
                :min="new Date().toISOString().split('T')[0]"
              />
            </UFormGroup>

            <UFormGroup label="新時間" name="new_time">
              <UInput
                v-model="rescheduleData.new_time"
                type="time"
                placeholder="選填"
              />
            </UFormGroup>

            <UFormGroup label="改約原因" name="notes">
              <UTextarea
                v-model="rescheduleData.notes"
                placeholder="輸入改約原因..."
                :rows="3"
              />
            </UFormGroup>

            <div class="flex gap-3 justify-end pt-4">
              <UButton variant="ghost" @click="showRescheduleModal = false">
                取消
              </UButton>
              <UButton type="submit" :loading="rescheduling">
                確認改約
              </UButton>
            </div>
          </div>
        </UForm>
      </div>
    </UModal>
  </div>
</template>

<script setup>
const toast = useToast()
const { $api } = useNuxtApp()

definePageMeta({
  middleware: 'auth'
})

useHead({
  title: '追蹤行事曆 - 金融管理系統'
})

// 響應式數據
const showAddModal = ref(false)
const showDetailModal = ref(false)
const showRescheduleModal = ref(false)
const submitting = ref(false)
const rescheduling = ref(false)
const customersLoading = ref(false)

const currentDate = ref(new Date())
const selectedSchedule = ref(null)
const schedules = ref([])
const customers = ref([])
const stats = ref({
  today: 0,
  overdue: 0,
  needReminder: 0,
  completed: 0
})

// 表單數據
const formData = ref({
  customer_id: null,
  scheduled_date: '',
  scheduled_time: '',
  contact_type: 'phone',
  priority: 'medium',
  notes: ''
})

const rescheduleData = ref({
  new_date: '',
  new_time: '',
  notes: ''
})

// 常數數據
const weekDays = ['日', '一', '二', '三', '四', '五', '六']

const contactTypes = [
  { label: '電話', value: 'phone' },
  { label: 'LINE', value: 'line' },
  { label: 'Email', value: 'email' },
  { label: '會面', value: 'meeting' },
  { label: '其他', value: 'other' }
]

const priorities = [
  { label: '高', value: 'high' },
  { label: '中', value: 'medium' },
  { label: '低', value: 'low' }
]

// 計算屬性
const currentMonth = computed(() => currentDate.value.getFullYear())
const currentMonthNumber = computed(() => currentDate.value.getMonth() + 1)

const calendarDates = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const startDate = new Date(firstDay)
  const endDate = new Date(lastDay)
  
  // 調整到週日開始
  startDate.setDate(startDate.getDate() - startDate.getDay())
  // 調整到週六結束
  endDate.setDate(endDate.getDate() + (6 - endDate.getDay()))
  
  const dates = []
  const current = new Date(startDate)
  const today = new Date()
  
  while (current <= endDate) {
    dates.push({
      date: current.getDate(),
      fullDate: current.toISOString().split('T')[0],
      isCurrentMonth: current.getMonth() === month,
      isToday: current.toDateString() === today.toDateString()
    })
    current.setDate(current.getDate() + 1)
  }
  
  return dates
})

// 方法
const previousMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
  loadCalendarData()
}

const nextMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
  loadCalendarData()
}

const goToToday = () => {
  currentDate.value = new Date()
  loadCalendarData()
}

const selectDate = (date) => {
  if (date.isCurrentMonth) {
    formData.value.scheduled_date = date.fullDate
    showAddModal.value = true
  }
}

const getSchedulesForDate = (date) => {
  return schedules.value.filter(schedule => schedule.scheduled_date === date)
}

const getScheduleClass = (schedule) => {
  const baseClasses = 'block'
  switch (schedule.status) {
    case 'scheduled':
      if (schedule.priority === 'high') {
        return `${baseClasses} bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200`
      } else if (schedule.priority === 'medium') {
        return `${baseClasses} bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200`
      } else {
        return `${baseClasses} bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200`
      }
    case 'contacted':
      return `${baseClasses} bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200`
    case 'rescheduled':
      return `${baseClasses} bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-200`
    case 'missed':
      return `${baseClasses} bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200 line-through`
    case 'completed':
      return `${baseClasses} bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200`
    default:
      return `${baseClasses} bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200`
  }
}

const showScheduleDetail = (schedule) => {
  selectedSchedule.value = schedule
  showDetailModal.value = true
}

const loadCalendarData = async () => {
  try {
    const year = currentDate.value.getFullYear()
    const month = currentDate.value.getMonth() + 1
    
    const response = await $api('/contact-schedules/calendar/data', {
      params: { year, month }
    })
    
    if (response.success) {
      // 將日期分組的數據轉換為平面數組
      schedules.value = []
      Object.keys(response.data).forEach(date => {
        response.data[date].forEach(schedule => {
          schedules.value.push(schedule)
        })
      })
    }
  } catch (error) {
    console.error('載入行事曆數據失敗:', error)
    toast.add({
      title: '載入失敗',
      description: '無法載入行事曆數據，請重試',
      color: 'red'
    })
  }
}

const loadStats = async () => {
  try {
    const [todayRes, overdueRes, reminderRes] = await Promise.all([
      $api('/contact-schedules/today/list'),
      $api('/contact-schedules/overdue/list'),
      $api('/contact-schedules/reminders/list')
    ])
    
    stats.value = {
      today: todayRes.success ? todayRes.data.length : 0,
      overdue: overdueRes.success ? overdueRes.data.length : 0,
      needReminder: reminderRes.success ? reminderRes.data.length : 0,
      completed: schedules.value.filter(s => s.status === 'completed').length
    }
  } catch (error) {
    console.error('載入統計數據失敗:', error)
  }
}

const loadCustomers = async () => {
  try {
    customersLoading.value = true
    const response = await $api('/customers', {
      params: { per_page: 1000 } // 載入所有客戶
    })
    
    if (response.success) {
      customers.value = response.data.data || []
    }
  } catch (error) {
    console.error('載入客戶列表失敗:', error)
    toast.add({
      title: '載入失敗',
      description: '無法載入客戶列表',
      color: 'red'
    })
  } finally {
    customersLoading.value = false
  }
}

const submitSchedule = async () => {
  try {
    submitting.value = true
    
    const response = await $api('/contact-schedules', {
      method: 'POST',
      body: formData.value
    })
    
    if (response.success) {
      toast.add({
        title: '建立成功',
        description: '聯絡計畫已建立',
        color: 'green'
      })
      
      showAddModal.value = false
      resetForm()
      await loadCalendarData()
      await loadStats()
    }
  } catch (error) {
    console.error('建立聯絡計畫失敗:', error)
    toast.add({
      title: '建立失敗',
      description: error.data?.message || '無法建立聯絡計畫',
      color: 'red'
    })
  } finally {
    submitting.value = false
  }
}

const markAsContacted = async () => {
  try {
    const response = await $api(`/contact-schedules/${selectedSchedule.value.id}/contacted`, {
      method: 'POST',
      body: {
        notes: '已透過行事曆標記為聯絡完成'
      }
    })
    
    if (response.success) {
      toast.add({
        title: '標記成功',
        description: '已標記為聯絡完成',
        color: 'green'
      })
      
      showDetailModal.value = false
      await loadCalendarData()
      await loadStats()
    }
  } catch (error) {
    console.error('標記失敗:', error)
    toast.add({
      title: '標記失敗',
      description: error.data?.message || '無法標記為已聯絡',
      color: 'red'
    })
  }
}

const submitReschedule = async () => {
  try {
    rescheduling.value = true
    
    const response = await $api(`/contact-schedules/${selectedSchedule.value.id}/reschedule`, {
      method: 'POST',
      body: rescheduleData.value
    })
    
    if (response.success) {
      toast.add({
        title: '改約成功',
        description: '聯絡時間已改約',
        color: 'green'
      })
      
      showRescheduleModal.value = false
      showDetailModal.value = false
      await loadCalendarData()
      await loadStats()
    }
  } catch (error) {
    console.error('改約失敗:', error)
    toast.add({
      title: '改約失敗',
      description: error.data?.message || '無法改約時間',
      color: 'red'
    })
  } finally {
    rescheduling.value = false
  }
}

const resetForm = () => {
  formData.value = {
    customer_id: null,
    scheduled_date: '',
    scheduled_time: '',
    contact_type: 'phone',
    priority: 'medium',
    notes: ''
  }
}

const getContactTypeLabel = (type) => {
  const typeMap = {
    phone: '電話',
    line: 'LINE',
    email: 'Email',
    meeting: '會面',
    other: '其他'
  }
  return typeMap[type] || type
}

const getStatusLabel = (status) => {
  const statusMap = {
    scheduled: '已排程',
    contacted: '已聯絡',
    rescheduled: '已改約',
    missed: '未聯絡',
    completed: '已完成'
  }
  return statusMap[status] || status
}

const getStatusColor = (status) => {
  const colorMap = {
    scheduled: 'blue',
    contacted: 'green',
    rescheduled: 'yellow',
    missed: 'red',
    completed: 'green'
  }
  return colorMap[status] || 'gray'
}

const getPriorityLabel = (priority) => {
  const priorityMap = {
    high: '高',
    medium: '中',
    low: '低'
  }
  return priorityMap[priority] || priority
}

const getPriorityColor = (priority) => {
  const colorMap = {
    high: 'red',
    medium: 'yellow',
    low: 'gray'
  }
  return colorMap[priority] || 'gray'
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString('zh-TW')
}

const formatDateTime = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleString('zh-TW')
}

// 生命週期
onMounted(async () => {
  await loadCustomers()
  await loadCalendarData()
  await loadStats()
})
</script>