<script setup>
import { computed, ref } from 'vue'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import BookingFormDialog from '@/components/bookings/BookingFormDialog.vue'
import BookingCancelDialog from '@/components/bookings/BookingCancelDialog.vue'
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

const auth = useAuthStore()
const appStore = useAppStore()

const tableRef = ref(null)
const users = ref([])
getUsersApi().then(({ data }) => { users.value = data.data })

const STATUS_MAP = {
  pending: { color: 'warning', label: 'Pending' },
  confirmed: { color: 'info', label: 'Confirmed' },
  in_progress: { color: 'primary', label: 'In Progress' },
  completed: { color: 'success', label: 'Completed' },
  cancelled: { color: 'error', label: 'Cancelled' },
  no_show: { color: 'error', label: 'No Show' },
}

const TYPE_LABELS = {
  wedding: 'Wedding', portrait: 'Portrait', family: 'Family', product: 'Product',
  passport: 'Passport', event: 'Event', other: 'Other',
}

const headers = [
  { title: 'Customer', key: 'customer' },
  { title: 'Type', key: 'type' },
  { title: 'When', key: 'when', sortable: false },
  { title: 'Assigned', key: 'assigned', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
]

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

async function confirmDeleteBooking() {
  await deleteBookingApi(deleteTarget.value.id)
  confirmDelete.value = false
  appStore.notify('Booking deleted successfully.')
  tableRef.value?.refresh()
}

async function runAction(action, booking) {
  const actions = { confirm: confirmBookingApi, start: startBookingApi, complete: completeBookingApi, noShow: noShowBookingApi }
  await actions[action](booking.id)
  tableRef.value?.refresh()
}

function formatWhen(booking) {
  const start = new Date(booking.starts_at)
  const end = new Date(booking.ends_at)
  const dateStr = start.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
  const timeStr = (d) => d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
  return `${dateStr}, ${timeStr(start)} - ${timeStr(end)}`
}

const canCreate = computed(() => auth.hasPermission('bookings.create'))
const canUpdate = computed(() => auth.hasPermission('bookings.update'))
const canDelete = computed(() => auth.hasPermission('bookings.delete'))
const canCancel = computed(() => auth.hasPermission('bookings.cancel'))
</script>

<template>
  <div>
    <AppToolbar title="Bookings" subtitle="Schedule and track photography sessions.">
      <template #actions>
        <v-btn variant="outlined" prepend-icon="mdi-calendar-month-outline" :to="{ name: 'bookings-calendar' }">
          Calendar
        </v-btn>
        <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="openCreate">New Booking</v-btn>
      </template>
    </AppToolbar>

    <v-row class="mb-2" dense>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.status"
          label="Status"
          clearable
          density="compact"
          :items="Object.entries(STATUS_MAP).map(([value, s]) => ({ title: s.label, value }))"
        />
      </v-col>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.type"
          label="Type"
          clearable
          density="compact"
          :items="Object.entries(TYPE_LABELS).map(([value, title]) => ({ title, value }))"
        />
      </v-col>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.assigned_user_id"
          label="Assigned To"
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
            Confirm
          </v-btn>
          <v-btn v-if="canUpdate && item.status === 'confirmed'" size="small" variant="tonal" color="primary" class="mr-1" @click="runAction('start', item)">
            Start
          </v-btn>
          <v-btn v-if="canUpdate && item.status === 'in_progress'" size="small" variant="tonal" color="success" class="mr-1" @click="runAction('complete', item)">
            Complete
          </v-btn>

          <v-menu>
            <template #activator="{ props: menuProps }">
              <v-btn icon="mdi-dots-vertical" size="small" variant="text" v-bind="menuProps" />
            </template>
            <v-list density="compact">
              <v-list-item v-if="canUpdate" title="Edit" prepend-icon="mdi-pencil-outline" @click="openEdit(item)" />
              <v-list-item
                v-if="canUpdate && ['pending', 'confirmed'].includes(item.status)"
                title="Mark No-Show"
                prepend-icon="mdi-account-off-outline"
                @click="runAction('noShow', item)"
              />
              <v-list-item
                v-if="canCancel && !['cancelled', 'completed', 'no_show'].includes(item.status)"
                title="Cancel"
                prepend-icon="mdi-calendar-remove-outline"
                @click="openCancel(item)"
              />
              <v-list-item v-if="canDelete" title="Delete" prepend-icon="mdi-delete-outline" @click="askDelete(item)" />
            </v-list>
          </v-menu>
        </template>
      </AppTable>
    </v-card>

    <BookingFormDialog v-model="formDialog" :booking="editingBooking" @saved="tableRef?.refresh()" />

    <BookingCancelDialog v-model="cancelDialog" :booking-id="cancelTargetId" @saved="tableRef?.refresh()" />

    <AppConfirmDialog
      v-model="confirmDelete"
      title="Delete booking?"
      :message="`This will remove the booking for '${deleteTarget?.customer?.name}'.`"
      @confirm="confirmDeleteBooking"
    />
  </div>
</template>
