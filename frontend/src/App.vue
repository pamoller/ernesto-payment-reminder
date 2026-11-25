<script setup lang="ts">
import { onMounted, ref } from 'vue'

const health = ref<'unbekannt' | 'ok' | 'nicht erreichbar'>('unbekannt')

onMounted(async () => {
  try {
    const response = await fetch('/api/health')
    if (!response.ok) throw new Error(`HTTP ${response.status}`)
    const body = await response.json() as { status?: string }
    health.value = body.status === 'ok' ? 'ok' : 'nicht erreichbar'
  } catch {
    health.value = 'nicht erreichbar'
  }
})
</script>

<template>
  <main>
    <h1>Ernesto Payment Reminder</h1>
    <p>Backend-Status: {{ health }}</p>
  </main>
</template>
