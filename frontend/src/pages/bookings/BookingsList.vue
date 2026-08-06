<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import BookingFormDialog from '@/components/bookings/BookingFormDialog.vue'
import BookingCancelDialog from '@/components/bookings/BookingCancelDialog.vue'
import OrderFormDialog from '@/components/orders/OrderFormDialog.vue'
import {
  getBookingsApi,
  deleteBookingApi,
  confirmBookingApi,
  startBookingApi,
  completeBookingApi,
  noShowBookingApi,
} from '@/apis/booking.api'
import { getUsersApi } from '@/apis/user.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { formatDate, formatTime } from '@/utils/dateFormat'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()

const tableRef = ref(null)
const users = ref([])
getUsersApi().then(({ data }) => { users.value = data.data })

const STATUS_MAP = computed(() => ({
  pending: { color: 'warning', label: t('bookings.status.pending') },
  confirmed: { color: 'info', label: t('bookings.status.confirmed') },
  in_progress: { color: 'primary', label: t('bookings.status.inProgress') },
  completed: { color: 'success', label: t('bookings.status.completed') },
  cancelled: { color: 'error', label: t('bookings.status.cancelled') },
  no_show: { color: 'error', label: t('bookings.status.noShow') },
}))

const TYPE_LABELS = computed(() => ({
  wedding: t('bookings.types.wedding'),
  portrait: t('bookings.types.portrait'),
  family: t('bookings.types.family'),
  product: t('bookings.types.product'),
  passport: t('bookings.types.passport'),
  event: t('bookings.types.event'),
  other: t('bookings.types.other'),
}))

const headers = computed(() => [
  { title: t('fields.customer'), key: 'customer' },
  { title: t('fields.type'), key: 'type' },
  { title: t('bookings.when'), key: 'when', sortable: false },
  { title: t('fields.assignedTo'), key: 'assigned', sortable: false },
  { title: t('fields.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({ status: null, type: null, assigned_user_id: null })

async function fetchBookings(params) {
  const { data } = await getBookingsApi(params)
  return { items: data.data, total: data.meta.total }
}

const formDialog = ref(false)
const editingBooking = ref(null)
const cancelDialog = ref(false)
const cancelTargetId = ref(null)
const confirmDelete = ref(false)
const deleteTarget = ref(null)
const orderDialog = ref(false)
const orderPresetBooking = ref(null)

function openCreate() {
  editingBooking.value = null
  formDialog.value = true
}

function openEdit(booking) {
  editingBooking.value = booking
  formDialog.value = true
}

function openCancel(booking) {
  cancelTargetId.value = booking.id
  cancelDialog.value = true
}

function askDelete(booking) {
  deleteTarget.value = booking
  confirmDelete.value = true
}

function openCreateOrder(booking) {
  orderPresetBooking.value = booking
  orderDialog.value = true
}

async function confirmDeleteBooking() {
  await deleteBookingApi(deleteTarget.value.id)
  confirmDelete.value = false
  appStore.notify(t('bookings.messages.deletedSuccess'))
  tableRef.value?.refresh()
}

async function runAction(action, booking) {
  const actions = { confirm: confirmBookingApi, start: startBookingApi, complete: completeBookingApi, noShow: noShowBookingApi }
  await actions[action](booking.id)
  tableRef.value?.refresh()
}

function formatWhen(booking) {
  return `${formatDate(booking.starts_at)}, ${formatTime(booking.starts_at)} - ${formatTime(booking.ends_at)}`
}

const canCreate = computed(() => auth.hasPermission('bookings.create'))
const canUpdate = computed(() => auth.hasPermission('bookings.update'))
const canDelete = computed(() => auth.hasPermission('bookings.delete'))
const canCancel = computed(() => auth.hasPermission('bookings.cancel'))
const canCreateOrder = computed(() => auth.hasPermission('orders.create'))
</script>

<template>
  <div>
    <AppToolbar :title="t('menu.bookings')" :subtitle="t('bookings.subtitle')">
      <template #actions>
        <v-btn variant="outlined" prepend-icon="mdi-calendar-month-outline" :to="{ name: 'bookings-calendar' }">
          {{ t('bookings.calendar') }}
        </v-btn>
        <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('bookings.newBooking') }}</v-btn>
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
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.type"
          :label="t('fields.type')"
          clearable
          density="compact"
          :items="Object.entries(TYPE_LABELS).map(([value, title]) => ({ title, value }))"
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
        :fetch-fn="fetchBookings"
        :filters="filters"
        item-label="bookings"
      >
        <template #[`item.customer`]="{ item }">
          <div>{{ item.customer?.name }}</div>
          <div class="text-caption text-medium-emphasis">{{ item.title }}</div>
        </template>

        <template #[`item.type`]="{ item }">
          {{ TYPE_LABELS[item.type] }}
        </template>

        <template #[`item.when`]="{ item }">
          {{ formatWhen(item) }}
        </template>

        <template #[`item.assigned`]="{ item }">
          {{ item.assigned_user?.name || '—' }}
        </template>

        <template #[`item.status`]="{ item }">
          <AppStatusChip :status="item.status" :map="STATUS_MAP" size="small" />
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn v-if="canUpdate && item.status === 'pending'" size="small" variant="tonal" color="info" class="mr-1" @click="runAction('confirm', item)">
            {{ t('bookings.actions.confirm') }}
          </v-btn>
          <v-btn v-if="canUpdate && item.status === 'confirmed'" size="small" variant="tonal" color="primary" class="mr-1" @click="runAction('start', item)">
            {{ t('bookings.actions.start') }}
          </v-btn>
          <v-btn v-if="canUpdate && item.status === 'in_progress'" size="small" variant="tonal" color="success" class="mr-1" @click="runAction('complete', item)">
            {{ t('bookings.actions.complete') }}
          </v-btn>

          <v-menu>
            <template #activator="{ props: menuProps }">
              <v-btn icon="mdi-dots-vertical" size="small" variant="text" v-bind="menuProps" />
            </template>
            <v-list density="compact">
              <v-list-item v-if="canUpdate" class="text-primary" :title="t('common.edit')" prepend-icon="mdi-pencil-outline" @click="openEdit(item)" />
              <v-list-item
                v-if="canCreateOrder && !['cancelled', 'no_show'].includes(item.status)"
                :title="t('bookings.actions.createOrder')"
                prepend-icon="mdi-cart-plus"
                @click="openCreateOrder(item)"
              />
              <v-list-item
                v-if="canUpdate && ['pending', 'confirmed'].includes(item.status)"
                class="text-warning"
                :title="t('bookings.actions.markNoShow')"
                prepend-icon="mdi-account-off-outline"
                @click="runAction('noShow', item)"
              />
              <v-list-item
                v-if="canCancel && !['cancelled', 'completed', 'no_show'].includes(item.status)"
                class="text-warning"
                :title="t('common.cancel')"
                prepend-icon="mdi-calendar-remove-outline"
                @click="openCancel(item)"
              />
              <v-list-item v-if="canDelete" class="text-error" :title="t('common.delete')" prepend-icon="mdi-delete-outline" @click="askDelete(item)" />
            </v-list>
          </v-menu>
        </template>
      </AppTable>
    </v-card>

    <BookingFormDialog v-model="formDialog" :booking="editingBooking" @saved="tableRef?.refresh()" />

    <BookingCancelDialog v-model="cancelDialog" :booking-id="cancelTargetId" @saved="tableRef?.refresh()" />

    <OrderFormDialog v-model="orderDialog" :preset-booking="orderPresetBooking" />

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('bookings.deleteConfirmTitle')"
      :message="t('bookings.deleteConfirmMessage', { name: deleteTarget?.customer?.name })"
      @confirm="confirmDeleteBooking"
    />
  </div>
</template>
