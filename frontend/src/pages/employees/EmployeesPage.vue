<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import EmployeesTab from '@/components/employees/EmployeesTab.vue'
import AttendanceTab from '@/components/attendance/AttendanceTab.vue'
import CommissionsTab from '@/components/commissions/CommissionsTab.vue'
import PayrollTab from '@/components/payroll/PayrollTab.vue'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const TABS = computed(() => [
  { key: 'employees', label: t('employees.title'), permission: 'users.view' },
  { key: 'attendance', label: t('attendance.title'), permission: 'attendance.view' },
  { key: 'commissions', label: t('commissions.title'), permission: 'commissions.view' },
  { key: 'payroll', label: t('payroll.title'), permission: 'payroll.view' },
].filter(tab => auth.hasPermission(tab.permission)))

const tab = ref(TABS.value[0]?.key)
</script>

<template>
  <div>
    <AppToolbar :title="t('employees.title')" :subtitle="t('employees.subtitle')" />

    <v-tabs v-model="tab" class="mb-4">
      <v-tab v-for="item in TABS" :key="item.key" :value="item.key">{{ item.label }}</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="employees"><EmployeesTab /></v-window-item>
      <v-window-item value="attendance"><AttendanceTab /></v-window-item>
      <v-window-item value="commissions"><CommissionsTab /></v-window-item>
      <v-window-item value="payroll"><PayrollTab /></v-window-item>
    </v-window>
  </div>
</template>
