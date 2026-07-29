<script setup>
import { useAppStore } from '@/stores/app'
import { useI18n } from 'vue-i18n'

const appStore = useAppStore()
const { t } = useI18n()

function toggleLocale() {
  appStore.setLocale(appStore.locale === 'en' ? 'km' : 'en')
}

const FEATURES = [
  { icon: 'mdi-calendar-check-outline', key: 'bookings' },
  { icon: 'mdi-receipt-text-outline', key: 'orders' },
  { icon: 'mdi-image-multiple-outline', key: 'editing' },
  { icon: 'mdi-account-group-outline', key: 'customers' },
]
</script>

<template>
  <div class="auth-shell">
    <div class="auth-brand d-none d-md-flex">
      <div class="auth-brand__glow auth-brand__glow--one" />
      <div class="auth-brand__glow auth-brand__glow--two" />

      <div class="auth-brand__content">
        <div class="d-flex align-center ga-3 mb-10">
          <v-avatar color="white" size="48" class="auth-brand__logo">
            <v-icon icon="mdi-camera-iris" size="26" color="primary" />
          </v-avatar>
          <span class="text-h5 font-weight-bold">{{ t('app.name') }}</span>
        </div>

        <h1 class="text-h3 font-weight-bold auth-brand__headline mb-4">{{ t('auth.brandHeadline') }}</h1>
        <p class="text-body-1 auth-brand__subtitle mb-10">{{ t('auth.brandSubtitle') }}</p>

        <div class="d-flex flex-column ga-5">
          <div v-for="feature in FEATURES" :key="feature.key" class="d-flex align-center ga-4">
            <v-avatar color="rgba(255, 255, 255, 0.16)" size="44">
              <v-icon :icon="feature.icon" size="22" />
            </v-avatar>
            <span class="text-body-1">{{ t(`auth.features.${feature.key}`) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="auth-form-panel">
      <div class="d-flex justify-end ga-2 pa-4">
        <v-btn icon variant="text" @click="toggleLocale">
          <v-icon icon="mdi-translate" />
        </v-btn>
        <v-btn icon variant="text" @click="appStore.toggleTheme">
          <v-icon :icon="appStore.theme === 'light' ? 'mdi-weather-night' : 'mdi-white-balance-sunny'" />
        </v-btn>
      </div>

      <div class="auth-form-panel__content">
        <div class="d-flex d-md-none align-center justify-center ga-3 mb-8">
          <v-icon icon="mdi-camera-iris" size="32" color="primary" />
          <span class="text-h5 font-weight-bold">{{ t('app.name') }}</span>
        </div>

        <router-view />
      </div>
    </div>
  </div>
</template>

<style scoped>
.auth-shell {
  min-height: 100vh;
  display: flex;
  background: rgb(var(--v-theme-background));
}

.auth-brand {
  position: relative;
  flex: 0 0 42%;
  max-width: 560px;
  padding: 56px 48px;
  color: #fff;
  overflow: hidden;
  background: linear-gradient(160deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-tertiary)) 100%);
}

.auth-brand__glow {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}

.auth-brand__glow--one {
  width: 320px;
  height: 320px;
  top: -110px;
  right: -100px;
  background: rgba(255, 255, 255, 0.12);
}

.auth-brand__glow--two {
  width: 240px;
  height: 240px;
  bottom: -90px;
  left: -70px;
  background: rgba(255, 255, 255, 0.08);
}

.auth-brand__content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  height: 100%;
}

.auth-brand__logo {
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
}

.auth-brand__headline {
  max-width: 380px;
  line-height: 1.2;
}

.auth-brand__subtitle {
  color: rgba(255, 255, 255, 0.85);
  max-width: 360px;
}

.auth-form-panel {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.auth-form-panel__content {
  flex: 1 1 auto;
  width: 100%;
  max-width: 540px;
  margin: 0 auto;
  padding: 8px 24px 64px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
</style>
