<script setup>
import { computed } from 'vue'
import { useAppStore } from '@/stores/app'
import { useI18n } from 'vue-i18n'
import { watch } from 'vue'
import LoadingOverlay from '@/components/common/LoadingOverlay.vue'

const appStore = useAppStore()
const { locale } = useI18n()

const theme = computed(() => appStore.theme)

watch(() => appStore.locale, (value) => { locale.value = value }, { immediate: true })
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
