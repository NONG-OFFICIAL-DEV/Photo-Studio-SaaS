<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import AlbumFormDialog from '@/components/albums/AlbumFormDialog.vue'
import {
  getAlbumsApi,
  deleteAlbumApi,
  startAlbumApi,
  markAlbumReadyApi,
  deliverAlbumApi,
  archiveAlbumApi,
} from '@/apis/album.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()
const tableRef = ref(null)

const STATUS_MAP = computed(() => ({
  draft: { color: 'default', label: t('albums.status.draft') },
  in_progress: { color: 'primary', label: t('albums.status.inProgress') },
  ready: { color: 'secondary', label: t('albums.status.ready') },
  delivered: { color: 'success', label: t('albums.status.delivered') },
  archived: { color: 'default', label: t('albums.status.archived') },
}))

const headers = computed(() => [
  { title: t('fields.name'), key: 'name' },
  { title: t('fields.customer'), key: 'customer' },
  { title: t('albums.expectedPhotoCount'), key: 'expected_photo_count', sortable: false },
  { title: t('fields.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({ status: null })

async function fetchAlbums(params) {
  const { data } = await getAlbumsApi(params)
  return { items: data.data, total: data.meta.total }
}

const formDialog = ref(false)
const editingAlbum = ref(null)
const confirmDelete = ref(false)
const deleteTarget = ref(null)

function openCreate() {
  editingAlbum.value = null
  formDialog.value = true
}

function openEdit(album) {
  editingAlbum.value = album
  formDialog.value = true
}

function askDelete(album) {
  deleteTarget.value = album
  confirmDelete.value = true
}

async function confirmDeleteAlbum() {
  await deleteAlbumApi(deleteTarget.value.id)
  confirmDelete.value = false
  appStore.notify(t('albums.messages.deletedSuccess'))
  tableRef.value?.refresh()
}

async function runAction(action, album) {
  const actions = {
    start: startAlbumApi,
    ready: markAlbumReadyApi,
    deliver: deliverAlbumApi,
    archive: archiveAlbumApi,
  }

  try {
    await actions[action](album.id)
    tableRef.value?.refresh()
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'common.actionFailed'), 'error')
  }
}

const canCreate = computed(() => auth.hasPermission('albums.create'))
const canUpdate = computed(() => auth.hasPermission('albums.update'))
const canDelete = computed(() => auth.hasPermission('albums.delete'))
</script>

<template>
  <div>
    <AppToolbar :title="t('albums.title')" :subtitle="t('albums.subtitle')">
      <template #actions>
        <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('albums.newAlbum') }}</v-btn>
      </template>
    </AppToolbar>

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
    </v-row>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchAlbums"
        :filters="filters"
        item-label="albums"
      >
        <template #[`item.name`]="{ item }">
          <span class="cursor-pointer" @click="canUpdate && openEdit(item)">{{ item.name }}</span>
        </template>

        <template #[`item.customer`]="{ item }">
          {{ item.customer?.name || '—' }}
        </template>

        <template #[`item.expected_photo_count`]="{ item }">
          {{ item.expected_photo_count ?? '—' }}
        </template>

        <template #[`item.status`]="{ item }">
          <AppStatusChip :status="item.status" :map="STATUS_MAP" size="small" />
        </template>

        <template #[`item.actions`]="{ item }">
          <template v-if="canUpdate">
            <v-btn v-if="item.status === 'draft'" size="small" variant="tonal" color="primary" class="mr-1" @click="runAction('start', item)">
              {{ t('albums.actions.start') }}
            </v-btn>
            <v-btn v-if="item.status === 'in_progress'" size="small" variant="tonal" color="secondary" class="mr-1" @click="runAction('ready', item)">
              {{ t('albums.actions.markReady') }}
            </v-btn>
            <v-btn v-if="item.status === 'ready'" size="small" variant="tonal" color="success" class="mr-1" @click="runAction('deliver', item)">
              {{ t('albums.actions.deliver') }}
            </v-btn>
            <v-btn v-if="!['delivered', 'archived'].includes(item.status)" size="small" variant="text" class="mr-1" @click="runAction('archive', item)">
              {{ t('albums.actions.archive') }}
            </v-btn>
            <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="openEdit(item)" />
          </template>
          <v-btn v-if="canDelete" icon="mdi-trash-can-outline" size="small" variant="text" @click="askDelete(item)" />
        </template>
      </AppTable>
    </v-card>

    <AlbumFormDialog v-model="formDialog" :album="editingAlbum" @saved="tableRef?.refresh()" />

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('albums.dialogs.deleteConfirmTitle')"
      :message="t('albums.dialogs.deleteConfirmMessage', { name: deleteTarget?.name })"
      @confirm="confirmDeleteAlbum"
    />
  </div>
</template>
