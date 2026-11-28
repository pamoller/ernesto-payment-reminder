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
          title="Abonnements"
          aria-label="Abonnements"
          class="rounded bg-white/15 px-3 py-2 text-white"
        >
          Abonnements
        </a>
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
</template>
