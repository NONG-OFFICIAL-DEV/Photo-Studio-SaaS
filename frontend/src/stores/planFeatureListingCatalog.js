import { ref } from 'vue'
import { defineStore } from 'pinia'
import {
  getAdminPlanFeatureListingsApi,
  createAdminPlanFeatureListingApi,
  updateAdminPlanFeatureListingApi,
  deleteAdminPlanFeatureListingApi,
} from '@/apis/admin.api'

/**
 * Small cache for the plan feature catalog — shared by the catalog manager
 * dialog and PlanFormDialog's read-only per-plan editor, so both stay in
 * sync without re-fetching. Same load-once-and-share shape as
 * stores/expenseCategories.js.
 */
export const usePlanFeatureListingCatalogStore = defineStore('planFeatureListingCatalog', () => {
  const items = ref([])
  const loaded = ref(false)

  async function fetch(force = false) {
    if (loaded.value && !force) return items.value

    const { data } = await getAdminPlanFeatureListingsApi()
    items.value = data.data
    loaded.value = true
    return items.value
  }

  function invalidate() {
    loaded.value = false
  }

  async function create(payload) {
    await createAdminPlanFeatureListingApi(payload)
    invalidate()
    await fetch(true)
  }

  async function update(id, payload) {
    await updateAdminPlanFeatureListingApi(id, payload)
    invalidate()
    await fetch(true)
  }

  async function remove(id) {
    await deleteAdminPlanFeatureListingApi(id)
    invalidate()
    await fetch(true)
  }

  return { items, loaded, fetch, invalidate, create, update, remove }
})
