import { ref } from 'vue'
import { defineStore } from 'pinia'
import { getCustomerTagsApi } from '@/apis/customer-tag.api'

/**
 * Small cache for the tag list — shared by the customer form's multi-select
 * and the list page's tag filter so both stay in sync without re-fetching.
 */
export const useCustomerTagsStore = defineStore('customerTags', () => {
  const tags = ref([])
  const loaded = ref(false)

  async function fetch(force = false) {
    if (loaded.value && !force) return tags.value

    const { data } = await getCustomerTagsApi()
    tags.value = data.data
    loaded.value = true
    return tags.value
  }

  function invalidate() {
    loaded.value = false
  }

  return { tags, loaded, fetch, invalidate }
})
