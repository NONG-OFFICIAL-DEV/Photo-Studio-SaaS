import { ref } from 'vue'
import { defineStore } from 'pinia'
import { getServiceCategoriesApi } from '@/apis/service-category.api'

/**
 * Small cache for the category list — shared by the service form's
 * select and the list page's category filter so both stay in sync
 * without re-fetching. Same pattern as stores/customerTags.js.
 */
export const useServiceCategoriesStore = defineStore('serviceCategories', () => {
  const categories = ref([])
  const loaded = ref(false)

  async function fetch(force = false) {
    if (loaded.value && !force) return categories.value

    const { data } = await getServiceCategoriesApi()
    categories.value = data.data
    loaded.value = true
    return categories.value
  }

  function invalidate() {
    loaded.value = false
  }

  return { categories, loaded, fetch, invalidate }
})
