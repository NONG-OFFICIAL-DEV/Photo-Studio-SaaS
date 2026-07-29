<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import ActivityLogTable from '@/components/audit/ActivityLogTable.vue'
import ApiLogsTable from '@/components/audit/ApiLogsTable.vue'
import {
  getAuditLogApi,
  getActivityLogApi,
  getLoginHistoryApi,
  getSecurityEventsApi,
  getApiLogsApi,
} from '@/apis/audit.api'

const { t } = useI18n()
const tab = ref('audit')
</script>

<template>
  <div>
    <AppToolbar :title="t('auditPage.title')" :subtitle="t('auditPage.subtitle')" />

    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="audit">{{ t('auditPage.tabs.audit') }}</v-tab>
      <v-tab value="activity">{{ t('auditPage.tabs.activity') }}</v-tab>
      <v-tab value="login">{{ t('auditPage.tabs.login') }}</v-tab>
      <v-tab value="apiLogs">{{ t('auditPage.tabs.apiLogs') }}</v-tab>
      <v-tab value="security">{{ t('auditPage.tabs.security') }}</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="audit" class="mt-2"><ActivityLogTable :fetch-fn="getAuditLogApi" /></v-window-item>
      <v-window-item value="activity" class="mt-2"><ActivityLogTable :fetch-fn="getActivityLogApi" show-log-name /></v-window-item>
      <v-window-item value="login" class="mt-2"><ActivityLogTable :fetch-fn="getLoginHistoryApi" show-outcome /></v-window-item>
      <v-window-item value="apiLogs" class="mt-2"><ApiLogsTable :fetch-fn="getApiLogsApi" /></v-window-item>
      <v-window-item value="security" class="mt-2"><ActivityLogTable :fetch-fn="getSecurityEventsApi" /></v-window-item>
    </v-window>
  </div>
</template>
