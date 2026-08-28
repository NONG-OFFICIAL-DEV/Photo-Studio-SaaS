<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import PlanFeatureListingFormDialog from '@/components/admin/PlanFeatureListingFormDialog.vue'
import { usePlanFeatureListingCatalogStore } from '@/stores/planFeatureListingCatalog'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const appStore = useAppStore()
const catalogStore = usePlanFeatureListingCatalogStore()

const formDialog = ref(false)
const editingListing = ref(null)
const confirmDelete = ref(false)
const listingToDelete = ref(null)

watch(() => props.modelValue, (open) => {
  if (open) catalogStore.fetch(true)
})

function openCreate() {
  editingListing.value = null
  formDialog.value = true
}

function openEdit(listing) {
  editingListing.value = listing
  formDialog.value = true
}

function askDelete(listing) {
  listingToDelete.value = listing
  confirmDelete.value = true
}

async function confirmDeleteListing() {
  await catalogStore.remove(listingToDelete.value.id)
  confirmDelete.value = false
  appStore.notify(t('admin.planFeatureListings.messages.deletedSuccess'))
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('admin.planFeatureListings.manageCatalog')" max-width="560" @update:model-value="emit('update:modelValue', $event)">
    <div class="d-flex justify-space-between align-center mb-4">
      <p class="text-caption text-medium-emphasis mb-0">{{ t('admin.planFeatureListings.manageCatalogHint') }}</p>
      <v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="openCreate">{{ t('admin.planFeatureListings.addFeature') }}</v-btn>
    </div>

    <v-list density="compact">
      <v-list-item v-for="listing in catalogStore.items" :key="listing.id" :title="listing.label.en">
        <template #subtitle>
          <v-chip size="x-small" class="mr-1">{{ t(`admin.planFeatureListings.valueTypes.${listing.value_type}`) }}</v-chip>
          <span class="text-caption">{{ listing.key }}</span>
          <v-chip v-if="!listing.is_active" size="x-small" color="warning" variant="tonal" class="ml-1">{{ t('admin.plans.fields.isActive') }}: {{ t('common.no') }}</v-chip>
        </template>
        <template #append>
          <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="openEdit(listing)" />
          <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="askDelete(listing)" />
        </template>
      </v-list-item>
    </v-list>

    <PlanFeatureListingFormDialog v-model="formDialog" :listing="editingListing" />

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('admin.planFeatureListings.confirmDeleteTitle')"
      :message="t('admin.planFeatureListings.confirmDeleteMessage', { label: listingToDelete?.label?.en })"
      @confirm="confirmDeleteListing"
    />
  </AppDialog>
</template>
