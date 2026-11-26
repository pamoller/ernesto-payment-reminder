<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import MainHeader from './components/MainHeader.vue'
import SubscriptionDetailView from './components/SubscriptionDetailView.vue'
import SubscriptionListView from './components/SubscriptionListView.vue'
import { auth } from './auth'

const route = useRoute()
const subscriptionsRoute = computed(() => route.path.startsWith('/subscriptions'))
const detailRoute = computed(() => route.path === '/subscriptions/new' || /^\/subscriptions\/\d+$/.test(route.path))
</script>

<template>
  <MainHeader v-if="auth.authenticated" />
  <template v-if="auth.authenticated && subscriptionsRoute">
    <SubscriptionListView />
    <SubscriptionDetailView v-if="detailRoute" />
  </template>
  <RouterView v-else />
</template>
