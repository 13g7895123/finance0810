# 後台用戶管理與權限管理 API 路徑分析

## 需求分析

### 用戶管理功能需求：
1. **用戶列表** - 顯示所有用戶
2. **用戶詳情** - 查看單一用戶資料
3. **新增用戶** - 創建新用戶帳號
4. **編輯用戶** - 修改用戶資料
5. **刪除用戶** - 刪除用戶帳號
6. **用戶統計** - 用戶概覽數據

### 權限管理功能需求：
1. **角色列表** - 顯示所有系統角色
2. **權限列表** - 顯示所有系統權限
3. **分配角色** - 給用戶指派角色
4. **移除角色** - 移除用戶角色
5. **權限詳情** - 查看角色包含的權限
6. **權限分類** - 按分類顯示權限

## API 路徑規劃

### 用戶管理 API
```
GET    /api/users                    - 用戶列表 (分頁、搜尋、過濾)
POST   /api/users                    - 新增用戶
GET    /api/users/{id}               - 用戶詳情
PUT    /api/users/{id}               - 更新用戶
DELETE /api/users/{id}               - 刪除用戶
GET    /api/users/stats/overview     - 用戶統計概覽
```

### 權限管理 API
```
GET    /api/roles                    - 角色列表
GET    /api/users/{id}/roles         - 用戶角色列表
POST   /api/users/{id}/roles         - 分配角色給用戶
DELETE /api/users/{id}/roles/{role}  - 移除用戶角色

GET    /api/permissions              - 權限列表 (按分類)
GET    /api/roles/{id}/permissions   - 角色權限列表
```

### 認證相關 API
```
POST   /api/auth/login               - 登入
POST   /api/auth/logout              - 登出
GET    /api/auth/me                  - 當前用戶資訊
POST   /api/auth/refresh             - 刷新Token
```

## Composable 設計

### 1. useAuth - 認證管理
```javascript
// ~/composables/useAuth.js
export const useAuth = () => {
  const login = async (credentials) => { /* ... */ }
  const logout = async () => { /* ... */ }
  const getMe = async () => { /* ... */ }
  const refreshToken = async () => { /* ... */ }
  
  return { login, logout, getMe, refreshToken }
}
```

### 2. useUsers - 用戶管理
```javascript
// ~/composables/useUsers.js  
export const useUsers = () => {
  const getUsers = async (params) => { /* ... */ }
  const getUser = async (id) => { /* ... */ }
  const createUser = async (userData) => { /* ... */ }
  const updateUser = async (id, userData) => { /* ... */ }
  const deleteUser = async (id) => { /* ... */ }
  const getUserStats = async () => { /* ... */ }
  
  return { getUsers, getUser, createUser, updateUser, deleteUser, getUserStats }
}
```

### 3. useRoles - 角色權限管理
```javascript
// ~/composables/useRoles.js
export const useRoles = () => {
  const getRoles = async () => { /* ... */ }
  const getPermissions = async () => { /* ... */ }
  const getUserRoles = async (userId) => { /* ... */ }
  const assignRole = async (userId, role) => { /* ... */ }
  const removeRole = async (userId, role) => { /* ... */ }
  
  return { getRoles, getPermissions, getUserRoles, assignRole, removeRole }
}
```

### 4. useApi - HTTP 請求封裝
```javascript
// ~/composables/useApi.js
export const useApi = () => {
  const apiRequest = async (method, endpoint, data, config) => { /* ... */ }
  const get = async (endpoint, config) => { /* ... */ }
  const post = async (endpoint, data, config) => { /* ... */ }
  const put = async (endpoint, data, config) => { /* ... */ }
  const del = async (endpoint, config) => { /* ... */ }
  
  return { apiRequest, get, post, put, del }
}
```

## 頁面結構規劃

### 用戶管理頁面
- `/admin/users` - 用戶列表頁
- `/admin/users/create` - 新增用戶頁  
- `/admin/users/[id]` - 用戶詳情頁
- `/admin/users/[id]/edit` - 編輯用戶頁

### 權限管理頁面
- `/admin/roles` - 角色權限管理頁
- `/admin/permissions` - 權限列表頁

## 資料狀態管理

### Pinia Store 設計
```javascript
// ~/stores/admin.js
export const useAdminStore = defineStore('admin', {
  state: () => ({
    users: [],
    roles: [],
    permissions: [],
    currentUser: null,
    loading: false,
    error: null
  }),
  
  actions: {
    async fetchUsers() { /* ... */ },
    async fetchRoles() { /* ... */ },
    async fetchPermissions() { /* ... */ }
  }
})
```

## 需要確認的事項

1. **後端API可用性** - 檢查現有API是否完整可用
2. **認證機制** - JWT Token處理與自動刷新
3. **權限檢查** - 前端路由權限控制
4. **錯誤處理** - API錯誤回應處理機制
5. **loading狀態** - 載入中的UI狀態管理

## 下一步行動

1. 檢查後端API實際可用情況
2. 測試現有API端點
3. 創建對應的composable文件
4. 實現前端頁面組件
5. 進行API串接測試