import { ref } from 'vue'
import { defineStore } from 'pinia'
import { getBranchesApi } from '@/apis/branch.api'

/**
 * Small cache for the tenant's branch list — shared by every create form
 * that needs to show a branch picker (Employee, Booking, InventoryItem)
 * and by BranchesTab.vue itself, so all of them stay in sync without
 * separately re-fetching. Same pattern as stores/serviceCategories.js.
 */
export const useBranchStore = defineStore('branches', () => {
  const branches = ref([])
  const loaded = ref(false)

  async function fetch(force = false) {
    if (loaded.value && !force) return branches.value

    const { data } = await getBranchesApi()
    branches.value = data.data
    loaded.value = true
    return branches.value
  }

  function invalidate() {
    loaded.value = false
  }

  return { branches, loaded, fetch, invalidate }
})
