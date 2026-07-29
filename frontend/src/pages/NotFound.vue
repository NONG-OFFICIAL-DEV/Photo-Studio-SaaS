<script setup>
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const router = useRouter()
const { t } = useI18n()
</script>

<template>
  <v-container class="fill-height">
    <v-responsive class="mx-auto text-center" width="100%">
      <div class="text-h2 font-weight-bold text-primary mb-2">404</div>
      <div class="text-h6 mb-6">{{ t('common.pageNotFound') }}</div>
      <v-btn color="primary" @click="router.push('/')">{{ t('common.goHome') }}</v-btn>
    </v-responsive>
  </v-container>
</template>
