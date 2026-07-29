import { ref } from 'vue'
import { defineStore } from 'pinia'
import { getExpenseCategoriesApi } from '@/apis/expense-category.api'

/**
 * Small cache for the category list — shared by the expense form's
 * select and the category manager dialog so both stay in sync without
 * re-fetching. Same pattern as stores/serviceCategories.js.
 */
export const useExpenseCategoriesStore = defineStore('expenseCategories', () => {
  const categories = ref([])
  const loaded = ref(false)

  async function fetch(force = false) {
    if (loaded.value && !force) return categories.value

    const { data } = await getExpenseCategoriesApi()
    categories.value = data.data
    loaded.value = true
    return categories.value
  }

  function invalidate() {
    loaded.value = false
  }

  return { categories, loaded, fetch, invalidate }
})
