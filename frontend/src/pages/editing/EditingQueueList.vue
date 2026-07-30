<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import {
  getEditingTasksApi,
  startEditingTaskApi,
  markEditingTaskInReviewApi,
  requestEditingTaskRevisionApi,
  completeEditingTaskApi,
} from '@/apis/editing-task.api'
import { getUsersApi } from '@/apis/user.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const { t } = useI18n()
const appStore = useAppStore()
const tableRef = ref(null)
const users = ref([])
getUsersApi().then(({ data }) => { users.value = data.data })

const STATUS_MAP = computed(() => ({
  pending: { color: 'default', label: t('editing.status.pending') },
  in_progress: { color: 'primary', label: t('editing.status.inProgress') },
  in_review: { color: 'info', label: t('editing.status.inReview') },
  revision_requested: { color: 'warning', label: t('editing.status.revisionRequested') },
  completed: { color: 'success', label: t('editing.status.completed') },
}))

const headers = computed(() => [
  { title: t('fields.order'), key: 'order' },
  { title: t('fields.assignedTo'), key: 'assigned' },
  { title: t('fields.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({ status: null, assigned_user_id: null })

async function fetchTasks(params) {
  const { data } = await getEditingTasksApi(params)
  return { items: data.data, total: data.meta.total }
}

async function runAction(action, task) {
  const actions = {
    start: startEditingTaskApi,
    inReview: markEditingTaskInReviewApi,
    requestRevision: requestEditingTaskRevisionApi,
    complete: completeEditingTaskApi,
  }

  try {
    await actions[action](task.id)
    tableRef.value?.refresh()
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'common.actionFailed'), 'error')
  }
}
</script>

<template>
  <div>
    <AppToolbar :title="t('editing.title')" :subtitle="t('editing.subtitle')" />

    <v-row class="mb-2" dense>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.status"
          :label="t('fields.status')"
          clearable
          density="compact"
          :items="Object.entries(STATUS_MAP).map(([value, s]) => ({ title: s.label, value }))"
        />
      </v-col>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.assigned_user_id"
          :label="t('fields.assignedTo')"
          clearable
          density="compact"
          item-title="name"
          item-value="id"
          :items="users"
        />
      </v-col>
    </v-row>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchTasks"
        :filters="filters"
        item-label="tasks"
      >
        <template #[`item.order`]="{ item }">
          {{ item.order?.customer || '—' }}
        </template>

        <template #[`item.assigned`]="{ item }">
          {{ item.assigned_user?.name || t('editing.unassigned') }}
        </template>

        <template #[`item.status`]="{ item }">
          <AppStatusChip :status="item.status" :map="STATUS_MAP" size="small" />
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn v-if="item.status === 'pending' || item.status === 'revision_requested'" size="small" variant="tonal" color="primary" class="mr-1" @click="runAction('start', item)">
            {{ t('editing.actions.start') }}
          </v-btn>
          <v-btn v-if="item.status === 'in_progress'" size="small" variant="tonal" color="info" class="mr-1" @click="runAction('inReview', item)">
            {{ t('editing.actions.submitForReview') }}
          </v-btn>
          <template v-if="item.status === 'in_review'">
            <v-btn size="small" variant="tonal" color="success" class="mr-1" @click="runAction('complete', item)">
              {{ t('editing.actions.complete') }}
            </v-btn>
            <v-btn size="small" variant="text" color="warning" @click="runAction('requestRevision', item)">
              {{ t('editing.actions.requestRevision') }}
            </v-btn>
          </template>
        </template>
      </AppTable>
    </v-card>
  </div>
</template>
