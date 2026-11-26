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
  <header class="border-b border-emerald-900/10 bg-white">
    <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
      <a href="/subscriptions" class="font-serif text-xl font-bold text-ernesto-green">
        Ernesto's Payment Reminder
      </a>
      <nav class="flex flex-wrap items-center gap-2 text-sm" aria-label="Hauptnavigation">
        <a
          href="/subscriptions"
          class="rounded bg-ernesto-green px-3 py-2 text-white"
        >Abonnements</a>
        <a href="/subscriptions/new" class="rounded px-3 py-2 text-ernesto-green hover:bg-emerald-50">Abonnement hinzufügen</a>
        <button type="button" class="rounded px-3 py-2 text-slate-600 hover:bg-slate-100" @click="logout">
          Abmelden
        </button>
      </nav>
    </div>
  </header>
</template>
