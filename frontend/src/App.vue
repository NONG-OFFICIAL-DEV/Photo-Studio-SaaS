<script setup>
import { computed, watch } from 'vue'
import { useTheme } from 'vuetify'
import { useAppStore } from '@/stores/app'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import LoadingOverlay from '@/components/common/LoadingOverlay.vue'

const appStore = useAppStore()
const auth = useAuthStore()
const vuetifyTheme = useTheme()
const { locale } = useI18n()

const theme = computed(() => appStore.theme)

watch(() => appStore.locale, (value) => { locale.value = value }, { immediate: true })

/*
 * Tenant brand colors (Settings > Theme) override the default Material
 * palette on both light/dark themes at runtime — applied here rather than
 * baked into vuetify.js since they're per-tenant data, not build-time config.
 */
watch(
  () => auth.tenant?.settings,
  (settings) => {
    if (!settings) return

    if (settings.primary_color) {
      vuetifyTheme.themes.value.light.colors.primary = settings.primary_color
      vuetifyTheme.themes.value.dark.colors.primary = settings.primary_color
    }
    if (settings.secondary_color) {
      vuetifyTheme.themes.value.light.colors.secondary = settings.secondary_color
      vuetifyTheme.themes.value.dark.colors.secondary = settings.secondary_color
    }
  },
  { immediate: true, deep: true },
)
</script>

<template>
  <v-app :theme="theme">
    <router-view />

    <LoadingOverlay :model-value="appStore.globalLoading" />

    <v-snackbar
      v-model="appStore.snackbar.show"
      :color="appStore.snackbar.color"
      location="top right"
      timeout="4000"
    >
      {{ appStore.snackbar.text }}
    </v-snackbar>
  </v-app>
</template>
