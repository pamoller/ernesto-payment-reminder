<script setup lang="ts">
import MainHeader from './components/MainHeader.vue'
import { auth } from './auth'
</script>

<template>
  <MainHeader v-if="auth.authenticated" />
  <RouterView />
</template>
