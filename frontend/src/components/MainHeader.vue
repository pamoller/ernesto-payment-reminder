<script setup lang="ts">
import { auth } from '../auth'

async function logout(): Promise<void> {
  await fetch('/api/auth/logout', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-CSRF-Token': auth.csrfToken },
  })
  auth.authenticated = false
  auth.initialized = false
  window.location.href = '/login'
}
</script>

<template>
  <header class="border-b border-ernesto-blue-dark bg-ernesto-blue text-white">
    <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
      <a href="/subscriptions" class="font-serif text-xl font-bold text-white">
        Ernesto's Payment Reminder
      </a>
      <nav class="flex flex-wrap items-center gap-2 text-sm" aria-label="Hauptnavigation">
        <a
          href="/subscriptions"
          class="rounded bg-white/15 px-3 py-2 text-white"
        >Abonnements</a>
        <a href="/subscriptions/new" class="rounded px-3 py-2 text-white hover:bg-white/15">Abonnement hinzufügen</a>
        <button type="button" class="rounded px-3 py-2 text-white hover:bg-white/15" @click="logout">
          Abmelden
        </button>
      </nav>
    </div>
  </header>
</template>
