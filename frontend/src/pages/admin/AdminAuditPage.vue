<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import ActivityLogTable from '@/components/audit/ActivityLogTable.vue'
import ApiLogsTable from '@/components/audit/ApiLogsTable.vue'
import {
  getAdminAuditLogApi,
  getAdminActivityLogApi,
  getAdminLoginHistoryApi,
  getAdminSecurityEventsApi,
  getAdminApiLogsApi,
} from '@/apis/audit.api'
import { getAdminTenantsApi } from '@/apis/admin.api'

const { t } = useI18n()
const tab = ref('audit')
const tenantId = ref(null)
const tenantOptions = ref([])

onMounted(async () => {
  const { data } = await getAdminTenantsApi({ perPage: 200 })
  tenantOptions.value = data.data.map(t => ({ title: t.name, value: t.id }))
})
</script>

<template>
  <div>
    <AppToolbar :title="t('auditPage.title')" :subtitle="t('adminAuditPage.subtitle')">
      <template #actions>
        <v-select
          v-model="tenantId"
          :items="tenantOptions"
          :label="t('adminAuditPage.filterByTenant')"
          clearable
          density="comfortable"
          style="min-width: 260px"
          hide-details
        />
      </template>
    </AppToolbar>

    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="audit">{{ t('auditPage.tabs.audit') }}</v-tab>
      <v-tab value="activity">{{ t('auditPage.tabs.activity') }}</v-tab>
      <v-tab value="login">{{ t('auditPage.tabs.login') }}</v-tab>
      <v-tab value="apiLogs">{{ t('auditPage.tabs.apiLogs') }}</v-tab>
      <v-tab value="security">{{ t('auditPage.tabs.security') }}</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="audit" class="mt-2">
        <ActivityLogTable :fetch-fn="getAdminAuditLogApi" :extra-filters="{ tenant_id: tenantId }" />
      </v-window-item>
      <v-window-item value="activity" class="mt-2">
        <ActivityLogTable :fetch-fn="getAdminActivityLogApi" show-log-name :extra-filters="{ tenant_id: tenantId }" />
      </v-window-item>
      <v-window-item value="login" class="mt-2">
        <ActivityLogTable :fetch-fn="getAdminLoginHistoryApi" show-outcome :extra-filters="{ tenant_id: tenantId }" />
      </v-window-item>
      <v-window-item value="apiLogs" class="mt-2">
        <ApiLogsTable :fetch-fn="getAdminApiLogsApi" :extra-filters="{ tenant_id: tenantId }" />
      </v-window-item>
      <v-window-item value="security" class="mt-2">
        <ActivityLogTable :fetch-fn="getAdminSecurityEventsApi" :extra-filters="{ tenant_id: tenantId }" />
      </v-window-item>
    </v-window>
  </div>
</template>
