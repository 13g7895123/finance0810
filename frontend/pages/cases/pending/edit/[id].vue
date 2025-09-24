<template>
  <div :key="`case-edit-${route.params.id}`" class="space-y-6">
    <!-- 頁面標題與導航 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">編輯案件</h1>
        <p class="text-gray-600 mt-2">案件編號：{{ generateCaseNumber() }}</p>
      </div>
      <div class="flex space-x-3">
        <NuxtLink
          to="/cases/pending"
          class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
        >
          返回列表
        </NuxtLink>
      </div>
    </div>

    <!-- 載入狀態 -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      <span class="ml-3 text-gray-600">載入中...</span>
    </div>

    <!-- 錯誤訊息 -->
    <div v-else-if="loadError" class="bg-red-50 border border-red-200 rounded-lg p-4">
      <div class="flex">
        <div class="text-red-400">
          <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-red-800">載入失敗</h3>
          <div class="mt-2 text-sm text-red-700">{{ loadError }}</div>
        </div>
      </div>
    </div>

    <!-- 編輯表單 -->
    <form v-else @submit.prevent="saveChanges" class="space-y-8">

      <!-- 個人資料區塊 -->
      <div class="bg-white shadow rounded-lg p-6">
        <div class="border-b border-gray-200 pb-4 mb-6">
          <h2 class="text-xl font-semibold text-gray-900 flex items-center">
            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            個人資料
          </h2>
          <p class="text-sm text-gray-600 mt-1">客戶基本個人資訊</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- 姓名 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              姓名 <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="請輸入姓名"
            />
          </div>

          <!-- 出生年月日 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">出生年月日</label>
            <input
              v-model="form.birth_date"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
            />
          </div>

          <!-- 身份證字號 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">身份證字號</label>
            <input
              v-model="form.id_number"
              type="text"
              maxlength="10"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="請輸入身份證字號"
            />
          </div>

          <!-- 最高學歷 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">最高學歷</label>
            <select
              v-model="form.education_level"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
            >
              <option value="">請選擇學歷</option>
              <option value="國小">國小</option>
              <option value="國中">國中</option>
              <option value="高中職">高中職</option>
              <option value="專科">專科</option>
              <option value="大學">大學</option>
              <option value="碩士">碩士</option>
              <option value="博士">博士</option>
              <option value="其他">其他</option>
            </select>
          </div>

          <!-- 手機號碼 -->
          <div class="md:col-span-1">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              手機號碼 <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.phone"
              type="tel"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="請輸入手機號碼"
            />
          </div>
        </div>
      </div>

      <!-- 聯絡資訊區塊 -->
      <div class="bg-white shadow rounded-lg p-6">
        <div class="border-b border-gray-200 pb-4 mb-6">
          <h2 class="text-xl font-semibold text-gray-900 flex items-center">
            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            聯絡資訊
          </h2>
          <p class="text-sm text-gray-600 mt-1">聯絡方式與地址資訊</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- 可聯繫時間 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">可聯繫時間</label>
            <input
              v-model="form.contact_time"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="例：平日9:00-18:00"
            />
          </div>

          <!-- 室內電話 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">室內電話</label>
            <input
              v-model="form.home_phone"
              type="tel"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="請輸入室內電話"
            />
          </div>

          <!-- 戶籍地址 -->
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">戶籍地址</label>
            <textarea
              v-model="form.registered_address"
              rows="2"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="請輸入完整戶籍地址"
            ></textarea>
          </div>

          <!-- 通訊地址是否同戶籍地 -->
          <div class="md:col-span-2">
            <label class="flex items-center">
              <input
                v-model="form.mailing_same_as_registered"
                type="checkbox"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <span class="ml-2 text-sm text-gray-700">通訊地址與戶籍地址相同</span>
            </label>
          </div>

          <!-- 通訊地址 -->
          <div class="md:col-span-2" v-show="!form.mailing_same_as_registered">
            <label class="block text-sm font-medium text-gray-700 mb-2">通訊地址</label>
            <textarea
              v-model="form.mailing_address"
              rows="2"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="請輸入通訊地址"
            ></textarea>
          </div>

          <!-- 通訊電話 -->
          <div v-show="!form.mailing_same_as_registered">
            <label class="block text-sm font-medium text-gray-700 mb-2">通訊電話</label>
            <input
              v-model="form.mailing_phone"
              type="tel"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="請輸入通訊電話"
            />
          </div>

          <!-- 現居地住多久 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">現居地住多久</label>
            <input
              v-model="form.residence_duration"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="例：3年2個月"
            />
          </div>

          <!-- 居住地持有人 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">居住地持有人</label>
            <select
              v-model="form.residence_owner"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
            >
              <option value="">請選擇</option>
              <option value="本人">本人</option>
              <option value="父母">父母</option>
              <option value="配偶">配偶</option>
              <option value="子女">子女</option>
              <option value="親戚">親戚</option>
              <option value="朋友">朋友</option>
              <option value="租屋">租屋</option>
              <option value="其他">其他</option>
            </select>
          </div>

          <!-- 電信業者 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">電信業者</label>
            <select
              v-model="form.telecom_provider"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
            >
              <option value="">請選擇</option>
              <option value="中華電信">中華電信</option>
              <option value="台灣大哥大">台灣大哥大</option>
              <option value="遠傳電信">遠傳電信</option>
              <option value="亞太電信">亞太電信</option>
              <option value="台灣之星">台灣之星</option>
              <option value="其他">其他</option>
            </select>
          </div>
        </div>
      </div>

      <!-- 公司資料區塊 -->
      <div class="bg-white shadow rounded-lg p-6">
        <div class="border-b border-gray-200 pb-4 mb-6">
          <h2 class="text-xl font-semibold text-gray-900 flex items-center">
            <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            公司資料
          </h2>
          <p class="text-sm text-gray-600 mt-1">工作與收入相關資訊</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- 電子郵件 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">電子郵件</label>
            <input
              v-model="form.email"
              type="email"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="請輸入電子郵件"
            />
          </div>

          <!-- 公司名稱 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">公司名稱</label>
            <input
              v-model="form.company_name"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="請輸入公司名稱"
            />
          </div>

          <!-- 公司電話 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">公司電話</label>
            <input
              v-model="form.company_phone"
              type="tel"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="請輸入公司電話"
            />
          </div>

          <!-- 職稱 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">職稱</label>
            <input
              v-model="form.job_title"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="請輸入職稱"
            />
          </div>

          <!-- 公司地址 -->
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">公司地址</label>
            <textarea
              v-model="form.company_address"
              rows="2"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="請輸入公司地址"
            ></textarea>
          </div>

          <!-- 月收入 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">月收入</label>
            <input
              v-model.number="form.monthly_income"
              type="number"
              min="0"
              step="1000"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="請輸入月收入"
            />
          </div>

          <!-- 目前公司在職多久 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">目前公司在職多久</label>
            <input
              v-model="form.current_job_duration"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="例：2年6個月"
            />
          </div>

          <!-- 有無新轉勞保 -->
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">有無新轉勞保</label>
            <div class="flex space-x-4">
              <label class="flex items-center">
                <input
                  v-model="form.labor_insurance_transfer"
                  type="radio"
                  :value="null"
                  class="text-blue-600 focus:ring-blue-500"
                />
                <span class="ml-2 text-sm text-gray-700">未設定</span>
              </label>
              <label class="flex items-center">
                <input
                  v-model="form.labor_insurance_transfer"
                  type="radio"
                  :value="true"
                  class="text-blue-600 focus:ring-blue-500"
                />
                <span class="ml-2 text-sm text-gray-700">有</span>
              </label>
              <label class="flex items-center">
                <input
                  v-model="form.labor_insurance_transfer"
                  type="radio"
                  :value="false"
                  class="text-blue-600 focus:ring-blue-500"
                />
                <span class="ml-2 text-sm text-gray-700">無</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- 緊急聯絡人區塊 -->
      <div class="bg-white shadow rounded-lg p-6">
        <div class="border-b border-gray-200 pb-4 mb-6">
          <h2 class="text-xl font-semibold text-gray-900 flex items-center">
            <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-2.197a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            緊急聯絡人
          </h2>
          <p class="text-sm text-gray-600 mt-1">緊急聯絡人資訊</p>
        </div>

        <!-- 聯絡人① -->
        <div class="space-y-6">
          <div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">聯絡人①</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">姓名</label>
                <input
                  v-model="form.emergency_contact_1_name"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  placeholder="請輸入聯絡人姓名"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">關係</label>
                <select
                  v-model="form.emergency_contact_1_relationship"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                >
                  <option value="">請選擇關係</option>
                  <option value="父親">父親</option>
                  <option value="母親">母親</option>
                  <option value="配偶">配偶</option>
                  <option value="子女">子女</option>
                  <option value="兄弟姐妹">兄弟姐妹</option>
                  <option value="朋友">朋友</option>
                  <option value="同事">同事</option>
                  <option value="其他">其他</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">電話</label>
                <input
                  v-model="form.emergency_contact_1_phone"
                  type="tel"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  placeholder="請輸入聯絡電話"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">方便聯絡時間</label>
                <input
                  v-model="form.emergency_contact_1_available_time"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  placeholder="例：平日下午"
                />
              </div>

              <div class="md:col-span-2">
                <label class="flex items-center">
                  <input
                    v-model="form.emergency_contact_1_confidential"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span class="ml-2 text-sm text-gray-700">是否保密</span>
                </label>
              </div>
            </div>
          </div>

          <!-- 聯絡人② -->
          <div class="border-t border-gray-200 pt-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">聯絡人②</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">姓名</label>
                <input
                  v-model="form.emergency_contact_2_name"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  placeholder="請輸入聯絡人姓名"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">關係</label>
                <select
                  v-model="form.emergency_contact_2_relationship"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                >
                  <option value="">請選擇關係</option>
                  <option value="父親">父親</option>
                  <option value="母親">母親</option>
                  <option value="配偶">配偶</option>
                  <option value="子女">子女</option>
                  <option value="兄弟姐妹">兄弟姐妹</option>
                  <option value="朋友">朋友</option>
                  <option value="同事">同事</option>
                  <option value="其他">其他</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">電話</label>
                <input
                  v-model="form.emergency_contact_2_phone"
                  type="tel"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  placeholder="請輸入聯絡電話"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">方便聯絡時間</label>
                <input
                  v-model="form.emergency_contact_2_available_time"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  placeholder="例：假日早上"
                />
              </div>

              <div class="md:col-span-2">
                <label class="flex items-center">
                  <input
                    v-model="form.emergency_contact_2_confidential"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span class="ml-2 text-sm text-gray-700">是否保密</span>
                </label>
              </div>
            </div>
          </div>

          <!-- 介紹人 -->
          <div class="border-t border-gray-200 pt-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">介紹人</label>
              <input
                v-model="form.referrer"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                placeholder="請輸入介紹人姓名"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- 操作按鈕 -->
      <div class="flex justify-end space-x-4 pb-8">
        <NuxtLink
          to="/cases/pending"
          class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
        >
          取消
        </NuxtLink>
        <button
          type="submit"
          :disabled="saving"
          class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {{ saving ? '儲存中...' : '儲存變更' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth',
  layout: 'default'
})

const route = useRoute()
const router = useRouter()
const { $api } = useNuxtApp()
const { success, error: showError } = useNotification()

// 響應式數據
const loading = ref(true)
const saving = ref(false)
const loadError = ref(null)
const caseData = ref(null)

// 表單數據
const form = reactive({
  // 個人資料
  name: '',
  birth_date: '',
  id_number: '',
  education_level: '',
  phone: '',

  // 聯絡資訊
  contact_time: '',
  registered_address: '',
  home_phone: '',
  mailing_same_as_registered: false,
  mailing_address: '',
  mailing_phone: '',
  residence_duration: '',
  residence_owner: '',
  telecom_provider: '',

  // 公司資料
  email: '',
  company_name: '',
  company_phone: '',
  company_address: '',
  job_title: '',
  monthly_income: null,
  labor_insurance_transfer: null,
  current_job_duration: '',

  // 緊急聯絡人
  emergency_contact_1_name: '',
  emergency_contact_1_relationship: '',
  emergency_contact_1_phone: '',
  emergency_contact_1_available_time: '',
  emergency_contact_1_confidential: false,
  emergency_contact_2_name: '',
  emergency_contact_2_relationship: '',
  emergency_contact_2_phone: '',
  emergency_contact_2_available_time: '',
  emergency_contact_2_confidential: false,
  referrer: ''
})

// 載入案件數據
const loadCaseData = async () => {
  const id = route.params.id
  if (!id) {
    loadError.value = '案件ID無效'
    loading.value = false
    return
  }

  try {
    loading.value = true
    loadError.value = null

    // Point 2 修復：強制清空表單資料，確保響應式追蹤正常
    console.log('Clearing form data for new case...')

    // 先清空每個屬性，確保 Vue 的響應式系統正確追蹤變化
    form.name = ''
    form.birth_date = ''
    form.id_number = ''
    form.education_level = ''
    form.phone = ''
    form.contact_time = ''
    form.registered_address = ''
    form.home_phone = ''
    form.mailing_same_as_registered = false
    form.mailing_address = ''
    form.mailing_phone = ''
    form.residence_duration = ''
    form.residence_owner = ''
    form.telecom_provider = ''
    form.email = ''
    form.company_name = ''
    form.company_phone = ''
    form.company_address = ''
    form.job_title = ''
    form.monthly_income = null
    form.labor_insurance_transfer = null
    form.current_job_duration = ''
    form.emergency_contact_1_name = ''
    form.emergency_contact_1_relationship = ''
    form.emergency_contact_1_phone = ''
    form.emergency_contact_1_available_time = ''
    form.emergency_contact_1_confidential = false
    form.emergency_contact_2_name = ''
    form.emergency_contact_2_relationship = ''
    form.emergency_contact_2_phone = ''
    form.emergency_contact_2_available_time = ''
    form.emergency_contact_2_confidential = false
    form.referrer = ''
    const { data, error: apiError } = await $api.get(`/leads/${id}`)

    if (apiError) {
      loadError.value = apiError.message || '載入案件失敗'
      return
    }

    if (!data || !data.data) {
      loadError.value = '案件不存在'
      return
    }

    caseData.value = data.data
    console.log('Loaded case data:', caseData.value)

    // Point 2 修復：強化表單數據填充，確保響應式追蹤
    const formData = caseData.value

    // 使用 nextTick 確保響應式更新完成後再填充資料
    await nextTick()
    console.log('Filling form with new data...')

    // 逐一填充表單資料，確保響應式追蹤
    // 個人資料
    form.name = formData.name || ''
    form.birth_date = formData.birth_date ? new Date(formData.birth_date).toISOString().split('T')[0] : ''
    form.id_number = formData.id_number || ''
    form.education_level = formData.education_level || ''
    form.phone = formData.phone || ''

    // 聯絡資訊
    form.contact_time = formData.contact_time || ''
    form.registered_address = formData.registered_address || ''
    form.home_phone = formData.home_phone || ''
    form.mailing_same_as_registered = !!formData.mailing_same_as_registered
    form.mailing_address = formData.mailing_address || ''
    form.mailing_phone = formData.mailing_phone || ''
    form.residence_duration = formData.residence_duration || ''
    form.residence_owner = formData.residence_owner || ''
    form.telecom_provider = formData.telecom_provider || ''

    // 公司資料
    form.email = formData.email || ''
    form.company_name = formData.company_name || ''
    form.company_phone = formData.company_phone || ''
    form.company_address = formData.company_address || ''
    form.job_title = formData.job_title || ''
    form.monthly_income = formData.monthly_income ? Number(formData.monthly_income) : null
    form.labor_insurance_transfer = formData.labor_insurance_transfer === true ? true : formData.labor_insurance_transfer === false ? false : null
    form.current_job_duration = formData.current_job_duration || ''

    // 緊急聯絡人
    form.emergency_contact_1_name = formData.emergency_contact_1_name || ''
    form.emergency_contact_1_relationship = formData.emergency_contact_1_relationship || ''
    form.emergency_contact_1_phone = formData.emergency_contact_1_phone || ''
    form.emergency_contact_1_available_time = formData.emergency_contact_1_available_time || ''
    form.emergency_contact_1_confidential = !!formData.emergency_contact_1_confidential
    form.emergency_contact_2_name = formData.emergency_contact_2_name || ''
    form.emergency_contact_2_relationship = formData.emergency_contact_2_relationship || ''
    form.emergency_contact_2_phone = formData.emergency_contact_2_phone || ''
    form.emergency_contact_2_available_time = formData.emergency_contact_2_available_time || ''
    form.emergency_contact_2_confidential = !!formData.emergency_contact_2_confidential
    form.referrer = formData.referrer || ''

    console.log('Form data loaded successfully for case:', caseData.value.id)

  } catch (err) {
    loadError.value = '載入案件時發生錯誤'
    console.error('Load case error:', err)
  } finally {
    loading.value = false
  }
}

// 儲存變更
const saveChanges = async () => {
  const id = route.params.id
  if (!id) {
    showError('案件ID無效')
    return
  }

  try {
    saving.value = true

    // Point 2 修復：直接使用表單數據，避免過度處理
    const submitData = {
      // 個人資料
      name: form.name || '',
      birth_date: form.birth_date || null,
      id_number: form.id_number || null,
      education_level: form.education_level || null,
      phone: form.phone || '',

      // 聯絡資訊
      contact_time: form.contact_time || null,
      registered_address: form.registered_address || null,
      home_phone: form.home_phone || null,
      mailing_same_as_registered: form.mailing_same_as_registered,
      mailing_address: form.mailing_address || null,
      mailing_phone: form.mailing_phone || null,
      residence_duration: form.residence_duration || null,
      residence_owner: form.residence_owner || null,
      telecom_provider: form.telecom_provider || null,

      // 公司資料
      email: form.email || null,
      company_name: form.company_name || null,
      company_phone: form.company_phone || null,
      company_address: form.company_address || null,
      job_title: form.job_title || null,
      monthly_income: form.monthly_income,
      labor_insurance_transfer: form.labor_insurance_transfer,
      current_job_duration: form.current_job_duration || null,

      // 緊急聯絡人
      emergency_contact_1_name: form.emergency_contact_1_name || null,
      emergency_contact_1_relationship: form.emergency_contact_1_relationship || null,
      emergency_contact_1_phone: form.emergency_contact_1_phone || null,
      emergency_contact_1_available_time: form.emergency_contact_1_available_time || null,
      emergency_contact_1_confidential: form.emergency_contact_1_confidential,
      emergency_contact_2_name: form.emergency_contact_2_name || null,
      emergency_contact_2_relationship: form.emergency_contact_2_relationship || null,
      emergency_contact_2_phone: form.emergency_contact_2_phone || null,
      emergency_contact_2_available_time: form.emergency_contact_2_available_time || null,
      emergency_contact_2_confidential: form.emergency_contact_2_confidential,
      referrer: form.referrer || null
    }

    console.log('Submitting data:', submitData)

    const { data, error: apiError } = await $api.put(`/leads/${id}`, submitData)

    if (apiError) {
      showError(apiError.message || '儲存失敗')
      console.error('API Error:', apiError)
      return
    }

    if (data && !data.success) {
      showError(data.message || '儲存失敗')
      console.error('Save failed:', data)
      return
    }

    success('案件資料已更新')
    router.push('/cases/pending')

  } catch (err) {
    showError('儲存時發生錯誤')
    console.error('Save case error:', err)
  } finally {
    saving.value = false
  }
}

// 生成案件編號
const generateCaseNumber = () => {
  if (!caseData.value) return ''

  const date = new Date(caseData.value.created_at)
  const year = date.getFullYear().toString().slice(-2)
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const serial = String(caseData.value.id).padStart(3, '0')

  return `CASE${year}${month}${day}${serial}`
}

// 監聽通訊地址選項變化
watch(() => form.mailing_same_as_registered, (newVal) => {
  if (newVal) {
    form.mailing_address = form.registered_address
    form.mailing_phone = form.home_phone
  }
})

// 監聽路由參數變化，確保URL變動時重新載入資料
watch(() => route.params.id, (newId, oldId) => {
  console.log(`Route change detected: ${oldId} -> ${newId}`)
  if (newId && newId !== oldId) {
    // 強制重置狀態
    loading.value = true
    loadError.value = null
    caseData.value = null
    // 重新載入資料
    loadCaseData()
  }
}, { immediate: true })

// Point 2 修復：移除 onMounted 重複載入，因為 watch 已經有 immediate: true

// 設定頁面標題
useHead({
  title: computed(() => caseData.value ? `編輯案件 ${generateCaseNumber()}` : '編輯案件'),
})
</script>