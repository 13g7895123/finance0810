<template>
  <div class="space-y-6">
    <!-- 頁面標題 -->
    <div class="bg-white rounded-lg shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold text-gray-900 ">
          系統除錯面板
        </h2>
        <div class="flex items-center space-x-2">
          <div 
            :class="[
              'w-3 h-3 rounded-full',
              systemHealth?.overall_status === 'healthy' ? 'bg-green-500 animate-pulse' : 
              systemHealth?.overall_status === 'warning' ? 'bg-yellow-500 animate-pulse' : 
              'bg-red-500 animate-pulse'
            ]"
          ></div>
          <span class="text-sm text-gray-600 ">
            {{ getOverallStatusText() }}
          </span>
        </div>
      </div>
      <p class="text-gray-600 ">
        診斷系統狀態、管理Firebase同步、監控資料庫連接
      </p>
      
      <!-- 權限提示與調試信息 -->
      <div v-if="!canAccessDebug" class="mt-4 p-4 bg-yellow-100 border border-yellow-300 rounded-lg">
        <div class="flex items-center mb-2">
          <svg class="w-5 h-5 text-yellow-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
          <span class="text-yellow-800 font-medium">您需要管理員權限才能使用除錯功能</span>
        </div>
        
        <!-- 開發模式調試信息 -->
        <div v-if="showDebugInfo" class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded text-xs">
          <div class="font-medium text-yellow-900 mb-2">調試信息：</div>
          <div class="space-y-1 text-yellow-700">
            <div>用戶: {{ user?.name || user?.email || '未載入' }}</div>
            <div>用戶ID: {{ user?.id || '無' }}</div>
            <div>角色: {{ getUserRoleNames() }}</div>
            <div>角色原始數據: {{ JSON.stringify(user?.roles) }}</div>
            <div>isAdmin方法: {{ user?.isAdmin ? '有' : '無' }}</div>
            <div>isManager方法: {{ user?.isManager ? '有' : '無' }}</div>
            <div>canAccessAllChats方法: {{ user?.canAccessAllChats ? '有' : '無' }}</div>
          </div>
          <button 
            @click="temporaryAccess = true"
            class="mt-2 px-3 py-1 bg-yellow-200 text-yellow-800 rounded text-xs hover:bg-yellow-300"
          >
            臨時啟用（開發用）
          </button>
        </div>
      </div>
    </div>

    <div v-if="canAccessDebug || temporaryAccess" class="space-y-6">
      <!-- 快速動作區 -->
      <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">快速動作</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
          <button
            @click="refreshHealthCheck"
            :disabled="loading.healthCheck"
            class="flex flex-col items-center p-4 bg-gray-100 text-gray-900 border-2 border-gray-300 rounded-lg hover:bg-gray-200 hover:border-blue-500 transition-colors disabled:opacity-50"
          >
            <svg v-if="loading.healthCheck" class="animate-spin w-8 h-8 text-blue-500 mb-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <svg v-else class="w-8 h-8 text-blue-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-sm font-medium text-gray-900 ">
              {{ loading.healthCheck ? '檢查中...' : '系統健康檢查' }}
            </span>
          </button>

          <button
            @click="syncToFirebase"
            :disabled="loading.sync"
            class="flex flex-col items-center p-4 bg-gray-100 text-gray-900 border-2 border-gray-300 rounded-lg hover:bg-gray-200 hover:border-green-500 transition-colors disabled:opacity-50"
          >
            <svg v-if="loading.sync" class="animate-spin w-8 h-8 text-green-500 mb-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <svg v-else class="w-8 h-8 text-green-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <span class="text-sm font-medium text-gray-900 ">
              {{ loading.sync ? '同步中...' : 'Firebase 同步' }}
            </span>
          </button>

          <button
            @click="validateData"
            :disabled="loading.validation"
            class="flex flex-col items-center p-4 bg-gray-100 text-gray-900 border-2 border-gray-300 rounded-lg hover:bg-gray-200 hover:border-yellow-500 transition-colors disabled:opacity-50"
          >
            <svg v-if="loading.validation" class="animate-spin w-8 h-8 text-yellow-500 mb-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <svg v-else class="w-8 h-8 text-yellow-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <span class="text-sm font-medium text-gray-900 ">
              {{ loading.validation ? '驗證中...' : '資料完整性驗證' }}
            </span>
          </button>

          <button
            @click="enableDebugMode"
            class="flex flex-col items-center p-4 bg-gray-100 text-gray-900 border-2 border-gray-300 rounded-lg hover:bg-gray-200 hover:border-purple-500 transition-colors"
          >
            <svg class="w-8 h-8 text-purple-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="text-sm font-medium text-gray-900 ">
              {{ debugModeEnabled ? '除錯模式：已啟用' : '啟用除錯模式' }}
            </span>
          </button>

          <button
            @click="testFirebaseConnection"
            :disabled="loading.firebaseTest"
            class="flex flex-col items-center p-4 bg-gray-100 text-gray-900 border-2 border-gray-300 rounded-lg hover:bg-gray-200 hover:border-orange-500 transition-colors disabled:opacity-50"
          >
            <svg v-if="loading.firebaseTest" class="animate-spin w-8 h-8 text-orange-500 mb-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <svg v-else class="w-8 h-8 text-orange-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
            </svg>
            <span class="text-sm font-medium text-gray-900 ">
              {{ loading.firebaseTest ? '測試中...' : 'Firebase連接測試' }}
            </span>
          </button>

          <button
            @click="fullSyncToFirebase"
            :disabled="loading.fullSync"
            class="flex flex-col items-center p-4 bg-gray-100 text-gray-900 border-2 border-gray-300 rounded-lg hover:bg-gray-200 hover:border-indigo-500 transition-colors disabled:opacity-50"
          >
            <svg v-if="loading.fullSync" class="animate-spin w-8 h-8 text-indigo-500 mb-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <svg v-else class="w-8 h-8 text-indigo-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <span class="text-sm font-medium text-gray-900 ">
              {{ loading.fullSync ? '完整同步中...' : 'Firebase完整同步' }}
            </span>
          </button>
        </div>
      </div>

      <!-- 系統狀態總覽 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Firebase 狀態 -->
        <div class="bg-white rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"/>
            </svg>
            Firebase Realtime Database
          </h3>
          
          <div class="space-y-3">
            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
              <span class="text-sm text-gray-600 ">連接狀態</span>
              <span :class="[
                'text-sm font-medium px-2 py-1 rounded',
                systemHealth?.firebase_connection ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
              ]">
                {{ systemHealth?.firebase_connection ? '已連接' : '未連接' }}
              </span>
            </div>
            
            <div v-if="systemHealth?.configuration" class="space-y-2">
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 ">專案ID</span>
                <span :class="[
                  'text-xs px-2 py-1 rounded',
                  systemHealth.configuration.project_id ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                ]">
                  {{ systemHealth.configuration.project_id ? '已配置' : '未配置' }}
                </span>
              </div>
              
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 ">資料庫URL</span>
                <span :class="[
                  'text-xs px-2 py-1 rounded',
                  systemHealth.configuration.database_url ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                ]">
                  {{ systemHealth.configuration.database_url ? '已配置' : '未配置' }}
                </span>
              </div>
              
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 ">憑證檔案</span>
                <span :class="[
                  'text-xs px-2 py-1 rounded',
                  systemHealth.configuration.credentials_file_exists ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                ]">
                  {{ systemHealth.configuration.credentials_file_exists ? '存在' : '缺失' }}
                </span>
              </div>
              
              <!-- 詳細配置信息 -->
              <div v-if="systemHealth.firebase?.configuration_details" class="mt-3 p-2 bg-gray-50 rounded text-xs">
                <div class="font-medium text-gray-700 mb-1">配置詳情：</div>
                <div class="space-y-1 text-gray-600 ">
                  <div>專案: {{ systemHealth.firebase.configuration_details.project_id || '未設定' }}</div>
                  <div>URL: {{ systemHealth.firebase.configuration_details.database_url ? '已設定' : '未設定' }}</div>
                  <div>憑證可讀: {{ systemHealth.firebase.configuration_details.credentials_file_readable ? '是' : '否' }}</div>
                </div>
              </div>
              
              <!-- 連接詳情 -->
              <div v-if="systemHealth.firebase?.connection_details" class="mt-3 p-2 bg-gray-50 rounded text-xs">
                <div class="font-medium text-gray-700 mb-1">連接狀態：</div>
                <div class="space-y-1 text-gray-600 ">
                  <div>測試時間: {{ formatDateTime(systemHealth.firebase.connection_details.last_test_time) }}</div>
                  <div v-if="systemHealth.firebase.connection_details.error">錯誤: {{ systemHealth.firebase.connection_details.error }}</div>
                  <div v-if="systemHealth.firebase.connection_details.error_type">類型: {{ systemHealth.firebase.connection_details.error_type }}</div>
                  <div v-if="!systemHealth.firebase.connection_details.error">狀態: {{ systemHealth.firebase.connection_details.response_time }}</div>
                </div>
              </div>
            </div>

            <div v-if="systemHealth?.database_connectivity" class="p-3 bg-blue-50 rounded-lg">
              <div class="text-sm text-blue-800 ">
                Firebase資料: {{ systemHealth.database_connectivity.data_count || 0 }} 筆對話
              </div>
              <div v-if="systemHealth.database_connectivity.error" class="text-xs text-red-600 mt-1">
                錯誤: {{ systemHealth.database_connectivity.error }}
              </div>
            </div>
          </div>
        </div>

        <!-- MySQL 狀態 -->
        <div class="bg-white rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
              <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
            </svg>
            MySQL 資料庫
          </h3>
          
          <div class="space-y-3">
            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
              <span class="text-sm text-gray-600 ">連接狀態</span>
              <span :class="[
                'text-sm font-medium px-2 py-1 rounded',
                systemHealth?.mysql?.connection ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
              ]">
                {{ systemHealth?.mysql?.connection ? '已連接' : '未連接' }}
              </span>
            </div>
            
            <div v-if="systemHealth?.mysql" class="grid grid-cols-2 gap-3">
              <div class="p-3 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 ">總對話數</div>
                <div class="text-lg font-semibold text-gray-900 ">
                  {{ systemHealth.mysql.conversations_count || 0 }}
                </div>
              </div>
              
              <div class="p-3 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 ">總客戶數</div>
                <div class="text-lg font-semibold text-gray-900 ">
                  {{ systemHealth.mysql.customers_count || 0 }}
                </div>
              </div>
              
              <div class="p-3 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 ">LINE客戶</div>
                <div class="text-lg font-semibold text-gray-900 ">
                  {{ systemHealth.mysql.line_customers || 0 }}
                </div>
              </div>
              
              <div class="p-3 bg-gray-50 rounded-lg">
                <div class="text-xs text-gray-500 ">已分配客戶</div>
                <div class="text-lg font-semibold text-gray-900 ">
                  {{ systemHealth.mysql.assigned_customers || 0 }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 同步結果與驗證結果 -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- 上次同步結果 -->
        <div v-if="lastSyncResult" class="bg-white rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">上次同步結果</h3>
          
          <!-- 同步說明 -->
          <div v-if="lastSyncResult.sync_description" class="mb-4 p-3 bg-blue-50 rounded-lg">
            <div class="text-sm text-blue-800 font-medium mb-2">同步說明</div>
            <div class="text-xs text-blue-700 ">{{ lastSyncResult.sync_description }}</div>
            <div class="text-xs text-blue-600 mt-1">資料來源: {{ lastSyncResult.data_source }}</div>
          </div>
          
          <!-- 同步條件 -->
          <div v-if="lastSyncResult.sync_criteria" class="mb-4">
            <div class="text-sm font-medium text-gray-700 mb-2">同步條件</div>
            <div class="space-y-1">
              <div class="text-xs text-gray-600 ">• {{ lastSyncResult.sync_criteria.has_line_user_id }}</div>
              <div class="text-xs text-gray-600 ">• {{ lastSyncResult.sync_criteria.has_assigned_customer }}</div>
              <div class="text-xs text-gray-600 ">• 時間範圍: {{ lastSyncResult.sync_criteria.time_range }}</div>
            </div>
          </div>
          
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">處理總數</span>
              <span class="text-sm font-medium text-gray-900 ">{{ lastSyncResult.total_found || 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">成功同步</span>
              <span class="text-sm font-medium text-green-600 ">{{ lastSyncResult.synced || 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">同步失敗</span>
              <span class="text-sm font-medium text-red-600 ">{{ lastSyncResult.failed || 0 }}</span>
            </div>
            <div class="mt-4 w-full bg-gray-200 rounded-full h-2">
              <div 
                class="bg-green-600 h-2 rounded-full"
                :style="{ width: `${getSyncSuccessRate()}%` }"
              ></div>
            </div>
            <div class="text-xs text-gray-500 text-center">
              成功率: {{ getSyncSuccessRate() }}%
            </div>
          </div>
        </div>

        <!-- 資料驗證結果 -->
        <div v-if="lastValidationResult" class="bg-white rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">資料完整性驗證</h3>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">MySQL 記錄</span>
              <span class="text-sm font-medium text-gray-900 ">{{ lastValidationResult.mysql_count || 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">Firebase 記錄</span>
              <span class="text-sm font-medium text-gray-900 ">{{ lastValidationResult.firebase_count || 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-gray-600 ">資料一致性</span>
              <span :class="[
                'text-sm font-medium px-2 py-1 rounded',
                lastValidationResult.is_consistent ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
              ]">
                {{ lastValidationResult.is_consistent ? '一致' : '不一致' }}
              </span>
            </div>
          </div>
        </div>
        
        <!-- Firebase連接測試結果 -->
        <div v-if="lastFirebaseTestResult" class="bg-white rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2" :class="lastFirebaseTestResult.overall_success ? 'text-green-500' : 'text-red-500'" fill="currentColor" viewBox="0 0 20 20">
              <path v-if="lastFirebaseTestResult.overall_success" fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              <path v-else fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            Firebase連接測試
          </h3>
          
          <div class="space-y-3">
            <!-- 整體狀態 -->
            <div class="flex justify-between items-center p-3 rounded-lg" :class="lastFirebaseTestResult.overall_success ? 'bg-green-50' : 'bg-red-50'">
              <span class="text-sm font-medium" :class="lastFirebaseTestResult.overall_success ? 'text-green-800' : 'text-red-800'">
                測試結果
              </span>
              <span class="text-sm font-medium px-2 py-1 rounded" :class="lastFirebaseTestResult.overall_success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                {{ lastFirebaseTestResult.overall_success ? '成功' : '失敗' }}
              </span>
            </div>
            
            <!-- 測試步驟 -->
            <div v-if="lastFirebaseTestResult.test_steps?.length" class="space-y-2">
              <div class="text-sm font-medium text-gray-700 mb-2">測試步驟：</div>
              <div 
                v-for="(step, index) in lastFirebaseTestResult.test_steps" 
                :key="index"
                class="flex items-center justify-between p-2 bg-gray-50 rounded text-xs"
              >
                <div class="flex items-center space-x-2">
                  <span class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs font-medium" 
                        :class="step.status === 'passed' ? 'bg-green-500' : step.status === 'failed' ? 'bg-red-500' : 'bg-yellow-500'">
                    {{ step.step }}
                  </span>
                  <span class="text-gray-700 ">{{ step.name }}</span>
                </div>
                <span class="text-xs px-2 py-1 rounded font-medium"
                      :class="step.status === 'passed' ? 'bg-green-100 text-green-800' : 
                              step.status === 'failed' ? 'bg-red-100 text-red-800' :
                              'bg-yellow-100 text-yellow-800'">
                  {{ step.status === 'passed' ? '✓ 成功' : step.status === 'failed' ? '✗ 失敗' : '⧖ 進行中' }}
                </span>
              </div>
              
              <!-- 錯誤詳情 -->
              <div v-if="lastFirebaseTestResult.test_steps.some(step => step.status === 'failed' && step.error)" class="mt-3">
                <div class="text-sm font-medium text-red-700 mb-2">錯誤詳情：</div>
                <div v-for="(step, index) in lastFirebaseTestResult.test_steps.filter(step => step.status === 'failed' && step.error)" 
                     :key="'error-' + index"
                     class="p-2 bg-red-50 border border-red-200 rounded text-xs">
                  <div class="font-medium text-red-800 ">{{ step.name }}</div>
                  <div class="text-red-700 mt-1">{{ step.error }}</div>
                </div>
              </div>
            </div>
            
            <!-- 測試時間 -->
            <div class="text-xs text-gray-500 text-center border-t pt-2">
              測試時間: {{ formatDateTime(lastFirebaseTestResult.timestamp) }}
            </div>
          </div>
        </div>
      </div>

      <!-- 系統異常與建議 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 系統異常 -->
        <div v-if="systemHealth?.system_anomalies?.length || systemHealth?.critical_issues?.length || systemHealth?.warning_issues?.length" class="bg-white rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-red-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            系統異常狀況
          </h3>
          <div class="space-y-2">
            <!-- 嚴重問題 -->
            <div v-if="systemHealth.critical_issues?.length" class="space-y-2">
              <div class="text-sm font-medium text-red-700 ">嚴重問題：</div>
              <div 
                v-for="(issue, index) in systemHealth.critical_issues" 
                :key="'critical-' + index"
                class="flex items-start space-x-3 p-2 bg-red-50 rounded-lg"
              >
                <svg class="w-4 h-4 text-red-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span class="text-sm text-red-800 ">{{ issue }}</span>
              </div>
            </div>
            
            <!-- 警告問題 -->
            <div v-if="systemHealth.warning_issues?.length" class="space-y-2">
              <div class="text-sm font-medium text-yellow-700 ">警告問題：</div>
              <div 
                v-for="(issue, index) in systemHealth.warning_issues" 
                :key="'warning-' + index"
                class="flex items-start space-x-3 p-2 bg-yellow-50 rounded-lg"
              >
                <svg class="w-4 h-4 text-yellow-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span class="text-sm text-yellow-800 ">{{ issue }}</span>
              </div>
            </div>
            
            <!-- 一般系統異常 -->
            <div v-if="systemHealth.system_anomalies?.length" class="space-y-2">
              <div class="text-sm font-medium text-gray-700 ">其他異常：</div>
              <div 
                v-for="(anomaly, index) in systemHealth.system_anomalies" 
                :key="'anomaly-' + index"
                class="flex items-start space-x-3 p-2 bg-gray-50 rounded-lg"
              >
                <svg class="w-4 h-4 text-gray-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <span class="text-sm text-gray-800 ">{{ anomaly }}</span>
              </div>
            </div>
          </div>
        </div>
        
        <!-- 建議操作 -->
        <div v-if="systemHealth?.recommendations?.length" class="bg-white rounded-lg shadow-sm p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">系統建議</h3>
          <div class="space-y-2">
            <div 
              v-for="(recommendation, index) in systemHealth.recommendations" 
              :key="index"
              class="flex items-start space-x-3 p-3 bg-blue-50 rounded-lg"
            >
              <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
              </svg>
              <span class="text-sm text-blue-800 ">{{ recommendation }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast 通知 -->
    <div
      v-if="notification.show"
      :class="[
        'fixed top-4 right-4 p-4 rounded-lg shadow-lg transition-all duration-300 z-40',
        notification.type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        notification.type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        notification.type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
      ]"
    >
      <div class="flex items-center justify-between space-x-2">
        <div class="flex items-center space-x-2">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path v-if="notification.type === 'success'" fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            <path v-else-if="notification.type === 'error'" fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            <path v-else fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
          </svg>
          <span>{{ notification.message }}</span>
        </div>
        <button 
          v-if="notification.type === 'error' && errorDetails.error"
          @click="errorDetails.show = true"
          class="text-xs underline hover:no-underline ml-2"
        >
          查看詳情
        </button>
      </div>
    </div>

    <!-- 錯誤詳情模態框 -->
    <div 
      v-if="errorDetails.show" 
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="errorDetails.show = false"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-4xl max-h-[90vh] overflow-hidden">
        <!-- 模態框標題 -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200 ">
          <div class="flex items-center space-x-2">
            <svg class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <h3 class="text-lg font-semibold text-gray-900 ">錯誤詳細信息</h3>
          </div>
          <button 
            @click="errorDetails.show = false"
            class="text-gray-400 hover:text-gray-600 "
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        
        <!-- 模態框內容 -->
        <div class="p-6 max-h-[70vh] overflow-y-auto">
          <!-- 基本錯誤信息 -->
          <div class="mb-6">
            <h4 class="text-sm font-medium text-gray-900 mb-2">錯誤訊息</h4>
            <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
              <p class="text-sm text-red-800 ">{{ errorDetails.error }}</p>
            </div>
          </div>
          
          <!-- 詳細錯誤信息 -->
          <div v-if="errorDetails.details" class="space-y-4">
            <!-- 異常類型 -->
            <div v-if="errorDetails.details.exception_type">
              <h4 class="text-sm font-medium text-gray-900 mb-2">異常類型</h4>
              <p class="text-sm text-gray-700 font-mono bg-gray-50 p-2 rounded">
                {{ errorDetails.details.exception_type }}
              </p>
            </div>
            
            <!-- 發生位置 -->
            <div v-if="errorDetails.details.file && errorDetails.details.line">
              <h4 class="text-sm font-medium text-gray-900 mb-2">發生位置</h4>
              <p class="text-sm text-gray-700 font-mono bg-gray-50 p-2 rounded">
                {{ errorDetails.details.file }}:{{ errorDetails.details.line }}
              </p>
            </div>
            
            <!-- 操作上下文 -->
            <div v-if="errorDetails.details.context">
              <h4 class="text-sm font-medium text-gray-900 mb-2">操作內容</h4>
              <p class="text-sm text-gray-700 bg-gray-50 p-2 rounded">
                {{ errorDetails.details.context }}
              </p>
            </div>
            
            <!-- 解決建議 -->
            <div v-if="errorDetails.details.suggestions && errorDetails.details.suggestions.length">
              <h4 class="text-sm font-medium text-gray-900 mb-2">解決建議</h4>
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <ul class="space-y-1">
                  <li 
                    v-for="(suggestion, index) in errorDetails.details.suggestions" 
                    :key="index"
                    class="text-sm text-blue-800 flex items-start"
                  >
                    <span class="w-4 h-4 text-blue-500 mr-2 flex-shrink-0 mt-0.5">•</span>
                    {{ suggestion }}
                  </li>
                </ul>
              </div>
            </div>
            
            <!-- 錯誤堆疊（開發模式） -->
            <div v-if="errorDetails.details.trace && process.dev" class="border-t pt-4">
              <h4 class="text-sm font-medium text-gray-900 mb-2">錯誤堆疊 (開發模式)</h4>
              <details class="bg-gray-50 rounded-lg">
                <summary class="p-2 cursor-pointer text-sm text-gray-600 hover:text-gray-800 ">
                  點擊查看完整堆疊追蹤
                </summary>
                <div class="p-3 border-t border-gray-200 ">
                  <pre class="text-xs text-gray-700 whitespace-pre-wrap font-mono overflow-x-auto">{{ errorDetails.details.trace }}</pre>
                </div>
              </details>
            </div>
          </div>
        </div>
        
        <!-- 模態框底部 -->
        <div class="flex justify-end p-6 border-t border-gray-200 space-x-3">
          <button
            @click="navigator.clipboard && navigator.clipboard.writeText(JSON.stringify({error: errorDetails.error, details: errorDetails.details}, null, 2))"
            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-md hover:border-gray-400 "
          >
            複製錯誤信息
          </button>
          <button
            @click="errorDetails.show = false"
            class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"
          >
            關閉
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth'
})

// Composables
const { get, post } = useApi()
const { user, isAdmin, isManager, hasRole } = useAuth()

// Reactive data
const systemHealth = ref(null)
const lastSyncResult = ref(null)
const lastValidationResult = ref(null)
const lastFirebaseTestResult = ref(null)
const debugModeEnabled = ref(false)
const temporaryAccess = ref(false)
const showDebugInfo = ref(process.dev)

// Loading states
const loading = ref({
  healthCheck: false,
  sync: false,
  validation: false,
  firebaseTest: false,
  fullSync: false
})

// Notification state
const notification = ref({
  show: false,
  type: 'info',
  message: ''
})

// Error details state
const errorDetails = ref({
  show: false,
  error: null,
  details: null
})

// Computed
const canAccessDebug = computed(() => {
  // 直接使用useAuth提供的方法，這些方法已經正確處理了角色檢查
  return isAdmin() || isManager() || hasRole('executive')
})

// Methods
const getUserRoleNames = () => {
  if (!user.value || !user.value.roles) return '無角色'
  return Array.isArray(user.value.roles) ? user.value.roles.join(', ') : '角色格式錯誤'
}

const showNotification = (type, message, duration = 5000) => {
  notification.value = { show: true, type, message }
  setTimeout(() => {
    notification.value.show = false
  }, duration)
}

const showErrorDetails = (error, errorDetailsData = null) => {
  errorDetails.value = {
    show: true,
    error: error,
    details: errorDetailsData
  }
}

const getOverallStatusText = () => {
  if (!systemHealth.value) return '未檢查'
  switch (systemHealth.value.overall_status) {
    case 'healthy': return '系統正常'
    case 'warning': return '系統警告'
    case 'critical': return '系統異常'
    default: return '狀態未知'
  }
}

const getSyncSuccessRate = () => {
  if (!lastSyncResult.value) return 0
  const total = lastSyncResult.value.total_found || 0
  const success = lastSyncResult.value.synced || 0
  return total > 0 ? Math.round((success / total) * 100) : 0
}

const formatDateTime = (dateTimeString) => {
  if (!dateTimeString) return '未知'
  try {
    return new Date(dateTimeString).toLocaleString('zh-TW', {
      year: 'numeric',
      month: '2-digit', 
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    })
  } catch (error) {
    return dateTimeString
  }
}

const refreshHealthCheck = async () => {
  loading.value.healthCheck = true
  try {
    const { data: response, error } = await get('/debug/system/health')
    
    if (error) {
      console.error('API Error:', error)
      throw new Error(error.message || '健康檢查失敗')
    }
    
    if (response.success) {
      systemHealth.value = response.health
      showNotification('success', '系統健康檢查完成')
    } else {
      throw new Error(response.error || '健康檢查失敗')
    }
  } catch (error) {
    console.error('健康檢查失敗:', error)
    
    let errorMessage = '未知錯誤'
    let errorDetailsData = null
    
    if (error.response) {
      // API 回應錯誤
      errorMessage = error.response.data?.error || error.response.statusText || '服務器錯誤'
      errorDetailsData = error.response.data?.error_details || null
      console.error('API Error Response:', error.response.data)
    } else if (error.request) {
      // 請求失敗（網路問題等）
      errorMessage = '無法連接到服務器，請檢查網路連接'
      console.error('Request Error:', error.request)
    } else {
      // 其他錯誤
      errorMessage = error.message || '請求處理失敗'
      console.error('General Error:', error.message)
    }
    
    showNotification('error', '健康檢查失敗：' + errorMessage)
    if (errorDetailsData) {
      showErrorDetails(errorMessage, errorDetailsData)
    }
  } finally {
    loading.value.healthCheck = false
  }
}

const syncToFirebase = async () => {
  loading.value.sync = true
  try {
    const { data: response, error } = await post('/debug/chat/batch-sync', {
      limit: 100,
      force: false
    })
    
    if (error) {
      console.error('Sync API Error:', error)
      throw new Error(error.message || '同步失敗')
    }
    
    if (response.success) {
      lastSyncResult.value = response.data
      showNotification('success', `Firebase同步完成：${response.data.synced} 成功，${response.data.failed} 失敗`)
      
      // 同步完成後自動刷新健康檢查
      setTimeout(() => {
        refreshHealthCheck()
      }, 2000)
    } else {
      throw new Error(response.error || '同步失敗')
    }
  } catch (error) {
    console.error('Firebase 同步失敗:', error)
    
    let errorMessage = '未知錯誤'
    let errorDetailsData = null
    
    if (error.response) {
      errorMessage = error.response.data?.error || error.response.statusText || '服務器錯誤'
      errorDetailsData = error.response.data?.error_details || null
      console.error('Sync Error Response:', error.response.data)
    } else if (error.request) {
      errorMessage = '無法連接到服務器，請檢查網路連接'
      console.error('Sync Request Error:', error.request)
    } else {
      errorMessage = error.message || '請求處理失敗'
      console.error('Sync General Error:', error.message)
    }
    
    showNotification('error', 'Firebase 同步失敗：' + errorMessage)
    if (errorDetailsData) {
      showErrorDetails(errorMessage, errorDetailsData)
    }
  } finally {
    loading.value.sync = false
  }
}

const validateData = async () => {
  loading.value.validation = true
  try {
    const { data: response, error } = await post('/debug/chat/validate-integrity', {
      check_all: true
    })
    
    if (error) {
      console.error('Validation API Error:', error)
      throw new Error(error.message || '驗證失敗')
    }
    
    if (response.success) {
      lastValidationResult.value = response.data
      const status = response.data.is_consistent ? 'success' : 'warning'
      const message = response.data.is_consistent ? '資料驗證通過，資料一致' : '發現資料不一致問題'
      showNotification(status, message)
    } else {
      throw new Error(response.error || '驗證失敗')
    }
  } catch (error) {
    console.error('資料驗證失敗:', error)
    
    let errorMessage = '未知錯誤'
    let errorDetailsData = null
    
    if (error.response) {
      errorMessage = error.response.data?.error || error.response.statusText || '服務器錯誤'
      errorDetailsData = error.response.data?.error_details || null
      console.error('Validation Error Response:', error.response.data)
    } else if (error.request) {
      errorMessage = '無法連接到服務器，請檢查網路連接'
      console.error('Validation Request Error:', error.request)
    } else {
      errorMessage = error.message || '請求處理失敗'
      console.error('Validation General Error:', error.message)
    }
    
    showNotification('error', '資料驗證失敗：' + errorMessage)
    if (errorDetailsData) {
      showErrorDetails(errorMessage, errorDetailsData)
    }
  } finally {
    loading.value.validation = false
  }
}

const enableDebugMode = () => {
  debugModeEnabled.value = !debugModeEnabled.value
  localStorage.setItem('debug_panel_enabled', debugModeEnabled.value.toString())
  localStorage.setItem('firebase_debug_mode', debugModeEnabled.value.toString())
  
  const message = debugModeEnabled.value ? 
    'Firebase 除錯模式已啟用，聊天室頁面將顯示除錯面板' : 
    'Firebase 除錯模式已關閉'
    
  showNotification('success', message)
}

const testFirebaseConnection = async () => {
  loading.value.firebaseTest = true
  try {
    const { data: response, error } = await post('/debug/firebase/test-connection')
    
    if (error) {
      console.error('Firebase Test API Error:', error)
      throw new Error(error.message || 'Firebase連接測試失敗')
    }
    
    if (response.success) {
      lastFirebaseTestResult.value = response.test_results
      showNotification('success', response.message || 'Firebase連接測試成功')
      
      // 顯示詳細測試結果
      if (response.test_results) {
        console.log('Firebase連接測試結果:', response.test_results)
        
        // 可以選擇顯示測試詳情
        const testDetails = response.test_results.test_steps
          .map(step => `步驟${step.step}: ${step.name} - ${step.status === 'passed' ? '✓' : '✗'}`)
          .join('\n')
        
        showNotification('info', '測試步驟完成，請查看下方詳細結果', 5000)
      }
      
      // 測試完成後自動刷新健康檢查
      setTimeout(() => {
        refreshHealthCheck()
      }, 2000)
    } else {
      throw new Error(response.error || 'Firebase連接測試失敗')
    }
  } catch (error) {
    console.error('Firebase連接測試失敗:', error)
    
    let errorMessage = '未知錯誤'
    let errorDetailsData = null
    
    if (error.response) {
      errorMessage = error.response.data?.error || error.response.statusText || '服務器錯誤'
      errorDetailsData = error.response.data?.error_details || null
      
      // 顯示測試結果（即使失敗也要顯示）
      if (error.response.data?.test_results) {
        lastFirebaseTestResult.value = error.response.data.test_results
        console.error('Firebase測試失敗結果:', error.response.data.test_results)
        
        const failedSteps = error.response.data.test_results.test_steps
          ?.filter(step => step.status === 'failed')
          ?.map(step => `${step.name}: ${step.error || '未知錯誤'}`)
          ?.join('\n')
        
        if (failedSteps) {
          console.error('失敗的測試步驟:', failedSteps)
        }
      }
      
      console.error('Firebase Test Error Response:', error.response.data)
    } else if (error.request) {
      errorMessage = '無法連接到服務器，請檢查網路連接'
      console.error('Firebase Test Request Error:', error.request)
    } else {
      errorMessage = error.message || '請求處理失敗'
      console.error('Firebase Test General Error:', error.message)
    }
    
    showNotification('error', 'Firebase連接測試失敗：' + errorMessage)
    if (errorDetailsData) {
      showErrorDetails(errorMessage, errorDetailsData)
    }
  } finally {
    loading.value.firebaseTest = false
  }
}

const fullSyncToFirebase = async () => {
  loading.value.fullSync = true
  try {
    const { data: response, error } = await post('/debug/chat/full-sync', {
      batch_size: 100,
      prevent_duplicates: true
    })
    
    if (error) {
      console.error('Full Sync API Error:', error)
      throw new Error(error.message || 'Firebase完整同步失敗')
    }
    
    if (response.success) {
      lastSyncResult.value = response.data
      const message = `Firebase完整同步完成：處理 ${response.data.total_processed || 0} 筆，成功同步 ${response.data.synced || 0} 筆，跳過 ${response.data.skipped || 0} 筆，失敗 ${response.data.failed || 0} 筆`
      showNotification('success', message)
      
      // 同步完成後自動刷新健康檢查
      setTimeout(() => {
        refreshHealthCheck()
      }, 2000)
    } else {
      throw new Error(response.error || 'Firebase完整同步失敗')
    }
  } catch (error) {
    console.error('Firebase完整同步失敗:', error)
    
    let errorMessage = '未知錯誤'
    let errorDetailsData = null
    
    if (error.response) {
      errorMessage = error.response.data?.error || error.response.statusText || '服務器錯誤'
      errorDetailsData = error.response.data?.error_details || null
      console.error('Full Sync Error Response:', error.response.data)
    } else if (error.request) {
      errorMessage = '無法連接到服務器，請檢查網路連接'
      console.error('Full Sync Request Error:', error.request)
    } else {
      errorMessage = error.message || '請求處理失敗'
      console.error('Full Sync General Error:', error.message)
    }
    
    showNotification('error', 'Firebase完整同步失敗：' + errorMessage)
    if (errorDetailsData) {
      showErrorDetails(errorMessage, errorDetailsData)
    }
  } finally {
    loading.value.fullSync = false
  }
}

// 頁面初始化
onMounted(() => {
  // 檢查當前除錯模式狀態
  debugModeEnabled.value = localStorage.getItem('firebase_debug_mode') === 'true'
  
  // 開發模式下顯示調試信息
  if (process.dev) {
    console.log('Debug page - User object:', user.value)
    console.log('Debug page - Can access debug:', canAccessDebug.value)
    console.log('Debug page - User roles:', user.value?.roles)
    showDebugInfo.value = true
  }
  
  // 如果有權限，自動執行健康檢查
  if (canAccessDebug.value || temporaryAccess.value) {
    refreshHealthCheck()
  }
})

// 頁面標題
useHead({
  title: '系統除錯 - 設定'
})
</script>