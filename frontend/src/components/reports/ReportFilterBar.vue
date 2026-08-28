<script setup>
import { useI18n } from 'vue-i18n'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { useBranchStore } from '@/stores/branches'

defineProps({
  dateFrom: { type: String, required: true },
  dateTo: { type: String, required: true },
  branchId: { type: String, default: null },
})

const emit = defineEmits(['update:dateFrom', 'update:dateTo', 'update:branchId'])

const { t } = useI18n()
const branchStore = useBranchStore()
branchStore.fetch()
</script>

<template>
  <v-row dense align="center" class="mb-2">
    <v-col cols="6" sm="3">
      <AppDatePicker
        :model-value="dateFrom"
        :label="t('reports.dateFrom')"
        :clearable="false"
        @update:model-value="emit('update:dateFrom', $event)"
      />
    </v-col>
    <v-col cols="6" sm="3">
      <AppDatePicker
        :model-value="dateTo"
        :label="t('reports.dateTo')"
        :clearable="false"
        @update:model-value="emit('update:dateTo', $event)"
      />
    </v-col>
    <v-col v-if="branchStore.branches.length > 1" cols="12" sm="3">
      <v-select
        :model-value="branchId"
        :label="t('fields.branch')"
        clearable
        density="compact"
        item-title="name"
        item-value="id"
        :items="branchStore.branches"
        @update:model-value="emit('update:branchId', $event)"
      />
    </v-col>
  </v-row>
</template>
