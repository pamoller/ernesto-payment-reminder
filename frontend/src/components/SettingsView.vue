<script setup lang="ts">
import { ref } from 'vue'
import { auth } from '../auth'
import { SUBSCRIPTIONS_REFRESH_EVENT } from '../types'

const emit = defineEmits<{ close: [] }>()
const busy = ref<'cron' | 'test-data' | null>(null)
const message = ref('')
const error = ref('')

async function run(action: 'cron' | 'test-data'): Promise<void> {
  if (busy.value) return
  busy.value = action
  message.value = ''
  error.value = ''
  try {
    const response = await fetch(`/api/settings/${action}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': auth.csrfToken },
    })
    const body = await response.json() as {
      error?: string
      transitions?: number
      sent?: number
      test_data?: { status_id: number; email: string }
    }
    if (!response.ok) throw new Error(body.error ?? 'Aktion fehlgeschlagen')
    window.dispatchEvent(new Event(SUBSCRIPTIONS_REFRESH_EVENT))
    message.value = action === 'cron'
      ? `Cron ausgeführt: ${body.transitions ?? 0} Statuswechsel, ${body.sent ?? 0} E-Mails.`
      : `Testdaten für ${body.test_data?.email ?? ''} importiert. Status: Bezahlt.`
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  } finally {
    busy.value = null
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4" @click.self="emit('close')">
    <section
      class="w-full max-w-xl rounded-lg border-2 border-ernesto-blue bg-white text-slate-800 shadow-2xl"
      role="dialog"
      aria-modal="true"
      aria-labelledby="settings-title"
    >
      <header class="flex items-center justify-between bg-ernesto-blue px-5 py-4 text-white">
        <h1 id="settings-title" class="text-xl font-bold text-white">Einstellungen</h1>
        <button type="button" aria-label="Schließen" class="grid size-9 place-items-center rounded-full text-white hover:bg-white/15" @click="emit('close')">×</button>
      </header>
      <div class="space-y-4 p-5">
        <p v-if="error" role="alert" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-red-700">{{ error }}</p>
        <p v-if="message" role="status" class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800">{{ message }}</p>
        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 border-b border-slate-200 pb-4">
          <span class="font-semibold">Cron starten:</span>
          <button type="button" :disabled="busy !== null" class="rounded bg-ernesto-blue px-4 py-2 text-white disabled:opacity-50" @click="run('cron')">ok</button>
        </div>
        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4">
          <span class="font-semibold">Testdaten einspielen:</span>
          <button type="button" :disabled="busy !== null" class="rounded bg-ernesto-blue px-4 py-2 text-white disabled:opacity-50" @click="run('test-data')">ok</button>
        </div>
      </div>
    </section>
  </div>
</template>
