<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const { t } = useI18n()
const route = useRoute()

const isSuccess = computed(() => route.query.status === 'success')
</script>

<template>
  <div class="text-center">
    <v-icon
      :icon="isSuccess ? 'mdi-check-circle-outline' : 'mdi-alert-circle-outline'"
      :color="isSuccess ? 'success' : 'error'"
      size="56"
      class="mb-4"
    />
    <h2 class="text-h6 font-weight-bold mb-2">{{ t('auth.verifyEmailTitle') }}</h2>
    <p class="text-body-2 text-medium-emphasis mb-6">
      {{ isSuccess ? t('auth.verifyEmailSuccess') : t('auth.verifyEmailInvalid') }}
    </p>
    <v-btn color="primary" :to="{ name: 'login' }">{{ t('auth.login') }}</v-btn>
  </div>
</template>
