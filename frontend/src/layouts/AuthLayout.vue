<script setup>
import { useAppStore } from '@/stores/app'
import { useI18n } from 'vue-i18n'

const appStore = useAppStore()
const { t } = useI18n()

function toggleLocale() {
  appStore.setLocale(appStore.locale === 'en' ? 'km' : 'en')
}
</script>

<template>
  <v-main class="auth-layout">
    <div class="d-flex justify-end pa-4 ga-2">
      <v-btn icon variant="text" @click="toggleLocale">
        <v-icon icon="mdi-translate" />
      </v-btn>
      <v-btn icon variant="text" @click="appStore.toggleTheme">
        <v-icon :icon="appStore.theme === 'light' ? 'mdi-weather-night' : 'mdi-white-balance-sunny'" />
      </v-btn>
    </div>

    <v-container class="fill-height" max-width="480">
      <v-responsive class="mx-auto" width="100%">
        <div class="text-center mb-6">
          <v-icon icon="mdi-camera-iris" size="40" color="primary" class="mb-2" />
          <div class="text-h5 font-weight-bold">{{ t('app.name') }}</div>
        </div>

        <v-card elevation="2" rounded="lg" class="pa-6">
          <router-view />
        </v-card>
      </v-responsive>
    </v-container>
  </v-main>
</template>

<style scoped>
.auth-layout {
  min-height: 100vh;
  background: rgb(var(--v-theme-surface-variant), 0.25);
}
</style>
