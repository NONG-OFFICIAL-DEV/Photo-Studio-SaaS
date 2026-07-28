<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <v-container class="fill-height">
    <v-responsive class="mx-auto text-center" width="100%">
      <div class="text-h2 font-weight-bold text-primary mb-2">404</div>
      <div class="text-h6 mb-6">Page not found</div>
      <v-btn color="primary" @click="router.push('/')">Go Home</v-btn>
    </v-responsive>
  </v-container>
</template>
