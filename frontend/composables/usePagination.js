/**
 * Pagination Composable
 * 統一的分頁邏輯處理
 */

export const usePagination = (initialPerPage = 10) => {
  const pagination = reactive({ 
    total: 0, 
    perPage: initialPerPage, 
    currentPage: 1, 
    lastPage: 1 
  })

  // 計算屬性
  const totalPages = computed(() => pagination.lastPage)
  const startIndex = computed(() => (pagination.currentPage - 1) * pagination.perPage)
  const endIndex = computed(() => Math.min(startIndex.value + pagination.perPage, pagination.total))
  
  // 分頁方法
  const nextPage = () => { 
    if (pagination.currentPage < totalPages.value) {
      pagination.currentPage++
    }
  }
  
  const prevPage = () => { 
    if (pagination.currentPage > 1) {
      pagination.currentPage--
    }
  }
  
  const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
      pagination.currentPage = page
    }
  }
  
  // 重置分頁
  const resetPagination = () => {
    pagination.currentPage = 1
    pagination.total = 0
    pagination.lastPage = 1
  }
  
  // 更新分頁資訊
  const updatePagination = (meta) => {
    Object.assign(pagination, meta)
  }

  return {
    pagination,
    totalPages,
    startIndex,
    endIndex,
    nextPage,
    prevPage,
    goToPage,
    resetPagination,
    updatePagination
  }
}