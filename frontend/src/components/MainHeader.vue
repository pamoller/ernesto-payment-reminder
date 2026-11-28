<script setup lang="ts">
import { ref } from 'vue'
import { auth } from '../auth'
import SettingsView from './SettingsView.vue'

const settingsOpen = ref(false)

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
          title="Abonnements"
          aria-label="Abonnements"
          class="rounded bg-white/15 px-3 py-2 text-white"
        >
          Abonnements
        </a>
        <button
          type="button"
          title="Einstellungen"
          aria-label="Einstellungen"
          class="grid size-9 place-items-center rounded-full text-white hover:bg-white/15"
          @click="settingsOpen = true"
        >
          <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 fill-none stroke-current" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.09A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.6 8.6a1.7 1.7 0 0 0-.34-1.88L4.2 6.66a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15.4 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.22.37.57.65 1 .8.35.12.72.18 1.1.18H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.51 1.02Z" />
          </svg>
        </button>
        <button
          type="button"
          title="Logout"
          aria-label="Logout"
          class="grid size-9 place-items-center rounded-full text-white hover:bg-white/15"
          @click="logout"
        >
          <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 fill-none stroke-current" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2v10" />
            <path d="M18.36 6.64a9 9 0 1 1-12.72 0" />
          </svg>
        </button>
      </nav>
    </div>
  </header>
  <SettingsView v-if="settingsOpen" @close="settingsOpen = false" />
</template>
