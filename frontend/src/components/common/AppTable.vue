<script setup>
import { ref, watch } from 'vue'
import AppSearch from '@/components/common/AppSearch.vue'
import EmptyState from '@/components/common/EmptyState.vue'

/*
 * Every data table in this app is server-driven: pagination, sorting and
 * search all round-trip to the API (App\Repositories\Eloquent\BaseRepository
 * ::paginateServer on the backend speaks the exact same contract).
 *
 * `fetchFn` receives { page, itemsPerPage, sortBy, sortDesc, search, ...filters }
 * and must return { items, total }.
 */
const props = defineProps({
  headers: { type: Array, required: true },
  fetchFn: { type: Function, required: true },
  filters: { type: Object, default: () => ({}) },
  showSearch: { type: Boolean, default: true },
  itemsPerPageOptions: { type: Array, default: () => [10, 15, 25, 50, 100] },
  itemLabel: { type: String, default: 'items' },
})

const items = ref([])
const totalItems = ref(0)
const loading = ref(false)
const search = ref('')
const options = ref({ page: 1, itemsPerPage: 15, sortBy: [] })

async function load() {
  loading.value = true
  try {
    const sort = options.value.sortBy?.[0]

    const { items: rows, total } = await props.fetchFn({
      page: options.value.page,
      perPage: options.value.itemsPerPage,
      sortBy: sort?.key,
      sortDesc: sort?.order === 'desc',
      search: search.value || undefined,
      ...props.filters,
    })

    items.value = rows
    totalItems.value = total
  } finally {
    loading.value = false
  }
}

watch(options, load, { deep: true })
watch(search, () => {
  options.value.page = 1
  load()
})
watch(() => props.filters, () => {
  options.value.page = 1
  load()
}, { deep: true })

defineExpose({ refresh: load })
</script>

<template>
  <div>
    <div v-if="showSearch" class="d-flex justify-end mb-3">
      <AppSearch v-model="search" />
    </div>

    <v-data-table-server
      v-model:items-per-page="options.itemsPerPage"
      v-model:page="options.page"
      v-model:sort-by="options.sortBy"
      :headers="headers"
      :items="items"
      :items-length="totalItems"
      :loading="loading"
      :items-per-page-options="itemsPerPageOptions"
      class="elevation-0"
    >
      <template v-for="(_, slotName) in $slots" #[slotName]="slotProps" :key="slotName">
        <slot :name="slotName" v-bind="slotProps" />
      </template>

      <template #no-data>
        <EmptyState :title="`No ${itemLabel} found`" description="Try adjusting your search or filters." />
      </template>
    </v-data-table-server>
  </div>
</template>
