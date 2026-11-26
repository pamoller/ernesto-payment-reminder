<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { onBeforeUnmount } from 'vue'
import SubscriptionForm from './SubscriptionForm.vue'
import { auth } from '../auth'
import { SUBSCRIPTIONS_REFRESH_EVENT, type Customer, type SubscriptionType } from '../types'

const route = useRoute()
const router = useRouter()
const id = computed(() => route.params.id ? Number(route.params.id) : null)
const types = ref<SubscriptionType[]>([])
const customer = ref<Customer | null>(null)
const original = ref<Customer | null>(null)
const form = reactive({ name: '', email: '', subscription_type_id: 0, start_date: new Date().toISOString().slice(0, 10) })
const busy = ref(false)
const error = ref('')
const success = ref('')
const changeReminderDialog = ref(false)
const reminderDialog = ref(false)
const changeReminderAction = ref<'save-and-remind' | 'save-only' | null>(null)

function close(): void {
  void router.push('/subscriptions')
}

function closeOnEscape(event: KeyboardEvent): void {
  if (event.key === 'Escape') close()
}

const dirty = computed(() => {
  if (!original.value) return form.name !== '' || form.email !== '' || form.start_date !== new Date().toISOString().slice(0, 10)
  return form.name !== original.value.name || form.email !== original.value.email
    || form.subscription_type_id !== original.value.subscription_type_id || form.start_date !== original.value.start_date
})

const removeNavigationGuard = router.beforeEach((to, from) => {
  const leavingDetail = from.path === route.path
    && (from.path === '/subscriptions/new' || /^\/subscriptions\/\d+$/.test(from.path))
    && to.path !== from.path
  if (leavingDetail && dirty.value) {
    return window.confirm('Es gibt nicht gespeicherte Änderungen. Seite wirklich verlassen?')
  }
  return true
})

async function loadTypes(): Promise<void> {
  const response = await fetch('/api/subscription-types')
  if (!response.ok) throw new Error('Abonnementtypen konnten nicht geladen werden')
  types.value = (await response.json() as { subscription_types: SubscriptionType[] }).subscription_types
  if (!id.value && types.value.length) form.subscription_type_id = types.value[0].id
}

async function loadCustomer(): Promise<void> {
  if (!id.value) return
  const response = await fetch(`/api/customers/${id.value}`)
  const body = await response.json() as { customer?: Customer; error?: string }
  if (!response.ok || !body.customer) throw new Error(body.error ?? 'Abonnement konnte nicht geladen werden')
  customer.value = body.customer
  original.value = { ...body.customer }
  Object.assign(form, {
    name: body.customer.name,
    email: body.customer.email,
    subscription_type_id: body.customer.subscription_type_id,
    start_date: body.customer.start_date,
  })
}

function formatDate(value: string): string {
  const [year, month, day] = value.slice(0, 10).split('-')
  return `${day}.${month}.${year}`
}

function formatTimestamp(value: string | null): string {
  if (!value) return 'Noch nicht erinnert'
  return new Intl.DateTimeFormat('de-DE', { timeZone: 'Europe/Berlin', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
}

function remainingDays(): number {
  if (!customer.value) return 0
  return Math.ceil((new Date(`${customer.value.expires_at}T00:00:00`).getTime() - Date.now()) / 86400000)
}

async function save(): Promise<void> {
  if (busy.value) return
  error.value = ''
  success.value = ''

  if (original.value?.reminder_sent && original.value
    && (form.start_date !== original.value.start_date || form.subscription_type_id !== original.value.subscription_type_id)) {
    changeReminderDialog.value = true
    return
  }
  await persist(false)
}

async function persist(sendReminder: boolean): Promise<void> {
  busy.value = true
  try {
    const response = await fetch(id.value ? `/api/customers/${id.value}` : '/api/customers', {
      method: id.value ? 'PUT' : 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrfToken },
      body: JSON.stringify(form),
    })
    const body = await response.json() as { customer?: Customer; error?: string }
    if (!response.ok) throw new Error(body.error ?? 'Abonnement konnte nicht gespeichert werden')
    if (body.customer) {
      customer.value = body.customer
      original.value = { ...body.customer }
      Object.assign(form, {
        name: body.customer.name,
        email: body.customer.email,
        subscription_type_id: body.customer.subscription_type_id,
        start_date: body.customer.start_date,
      })
    }
    window.dispatchEvent(new Event(SUBSCRIPTIONS_REFRESH_EVENT))
    if (sendReminder && id.value) {
      const reminderResponse = await fetch(`/api/customers/${id.value}/remind`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrfToken },
        body: JSON.stringify({ authorise_repeated_reminder: true }),
      })
      const reminderBody = await reminderResponse.json() as { reminder_sent?: string; error?: string }
      if (!reminderResponse.ok) {
        error.value = 'Das Abonnement wurde gespeichert. Die Zahlungserinnerung konnte jedoch nicht versendet werden.'
        await loadCustomer()
        return
      }
      success.value = 'Abonnement gespeichert und Zahlungserinnerung versendet.'
      await loadCustomer()
      window.dispatchEvent(new Event(SUBSCRIPTIONS_REFRESH_EVENT))
      return
    }
    await router.replace('/subscriptions')
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  } finally {
    busy.value = false
  }
}

function chooseChangeReminder(action: 'save-and-remind' | 'save-only'): void {
  changeReminderAction.value = action
  changeReminderDialog.value = false
  void persist(action === 'save-and-remind')
}

async function deleteCustomer(): Promise<void> {
  if (!id.value || !window.confirm('Soll dieses Abonnement wirklich gelöscht werden?')) return
  if (busy.value) return
  busy.value = true
  error.value = ''
  try {
    const response = await fetch(`/api/customers/${id.value}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-Token': auth.csrfToken },
    })
    if (!response.ok) throw new Error('Abonnement konnte nicht gelöscht werden')
    window.dispatchEvent(new Event(SUBSCRIPTIONS_REFRESH_EVENT))
    await router.replace('/subscriptions')
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  } finally {
    busy.value = false
  }
}

async function sendReminder(): Promise<void> {
  if (!id.value || busy.value) return
  if (dirty.value) {
    error.value = 'Bitte speichern Sie die Änderungen, bevor Sie eine Zahlungserinnerung versenden.'
    return
  }
  reminderDialog.value = true
}

async function confirmReminder(): Promise<void> {
  if (!id.value) return
  reminderDialog.value = false
  busy.value = true
  error.value = ''
  success.value = ''
  try {
    const response = await fetch(`/api/customers/${id.value}/remind`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrfToken },
      body: JSON.stringify({ authorise_repeated_reminder: true }),
    })
    const body = await response.json() as { reminder_sent?: string; error?: string }
    if (!response.ok) throw new Error(body.error ?? 'Erinnerung konnte nicht versendet werden')
    await loadCustomer()
    window.dispatchEvent(new Event(SUBSCRIPTIONS_REFRESH_EVENT))
    success.value = `Erinnerungs-E-Mail an ${customer.value?.email ?? ''} wurde versendet.`
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  } finally {
    busy.value = false
  }
}

watch(id, async () => {
  error.value = ''
  success.value = ''
  await Promise.all([loadTypes(), loadCustomer()])
})

onMounted(async () => {
  window.addEventListener('keydown', closeOnEscape)
  try {
    await Promise.all([loadTypes(), loadCustomer()])
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', closeOnEscape)
  removeNavigationGuard()
})
</script>

<template>
  <div class="fixed inset-0 z-30 overflow-y-auto bg-slate-950/60 p-3 sm:p-8" @click.self="close">
    <section
      class="mx-auto w-full max-w-3xl overflow-hidden rounded-lg bg-ernesto-blue text-white shadow-2xl"
      role="dialog"
      aria-modal="true"
      aria-labelledby="subscription-detail-title"
    >
      <header class="flex items-center justify-between border-b border-white/20 px-5 py-4 sm:px-7">
        <h1 id="subscription-detail-title" class="text-xl font-bold sm:text-2xl">
          {{ id ? 'Abonnement bearbeiten' : 'Abonnement hinzufügen' }}
        </h1>
        <button type="button" aria-label="Schließen" class="rounded px-3 py-1 text-2xl leading-none text-white hover:bg-white/15" @click="close">×</button>
      </header>
      <div class="p-5 sm:p-7">
        <p v-if="error" role="alert" class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-700">{{ error }}</p>
        <p v-if="success" role="status" class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">{{ success }}</p>

        <div v-if="id && customer" class="mb-5 grid gap-2 rounded border border-white/20 bg-white/10 p-4 text-sm sm:grid-cols-3">
          <div><span class="block font-semibold">Ablaufdatum</span>{{ formatDate(customer.expires_at) }}</div>
          <div><span class="block font-semibold">Verbleibende Tage</span>{{ remainingDays() }}</div>
          <div><span class="block font-semibold">Erinnert am</span>{{ formatTimestamp(customer.reminder_sent) }}</div>
        </div>

        <SubscriptionForm v-model="form" :subscription-types="types" :busy="busy" @submit="save">
          <template #actions>
            <div class="flex flex-wrap items-center gap-3 border-t border-white/20 pt-5">
              <button type="submit" :disabled="busy" class="rounded bg-white px-4 py-2 font-semibold text-ernesto-blue disabled:opacity-60">
                {{ busy ? 'Speichert …' : 'Speichern' }}
              </button>
              <button type="button" class="rounded border border-white/50 px-4 py-2 text-white hover:bg-white/10" @click="close">Abbrechen</button>
              <button v-if="id" type="button" :disabled="dirty || busy" class="rounded border border-white/50 px-4 py-2 text-white disabled:opacity-50" @click="sendReminder">
                {{ customer?.reminder_sent ? 'Erneut erinnern' : 'Erinnern' }}
              </button>
              <button v-if="id" type="button" :disabled="busy" class="ml-auto rounded border border-red-200 px-4 py-2 text-red-100 hover:bg-red-700/40" @click="deleteCustomer">Löschen</button>
            </div>
          </template>
        </SubscriptionForm>
      </div>
    </section>

    <div v-if="changeReminderDialog" class="fixed inset-0 z-40 grid place-items-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="change-title">
      <div class="w-full max-w-md rounded bg-white p-6 text-slate-800">
        <h2 id="change-title" class="text-xl font-bold">Erneut erinnern?</h2>
        <p class="mt-3">Das Abonnement wurde geändert. Soll eine neue Zahlungserinnerung versendet werden?</p>
        <div class="mt-5 flex flex-wrap justify-end gap-2">
          <button type="button" class="rounded bg-ernesto-blue px-3 py-2 text-white" @click="chooseChangeReminder('save-and-remind')">Speichern und erneut erinnern</button>
          <button type="button" class="rounded border px-3 py-2" @click="chooseChangeReminder('save-only')">Nur speichern</button>
          <button type="button" class="rounded px-3 py-2" @click="changeReminderDialog = false">Abbrechen</button>
        </div>
      </div>
    </div>

    <div v-if="reminderDialog" class="fixed inset-0 z-40 grid place-items-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="reminder-title">
      <div class="w-full max-w-md rounded bg-white p-6 text-slate-800">
        <h2 id="reminder-title" class="text-xl font-bold">Erinnerung bestätigen</h2>
        <p class="mt-3">Soll eine weitere Zahlungserinnerung an diese E-Mail-Adresse versendet werden?</p>
        <p class="mt-2 font-semibold">{{ customer?.email }}</p>
        <div class="mt-5 flex justify-end gap-2">
          <button type="button" class="rounded bg-ernesto-blue px-3 py-2 text-white" @click="confirmReminder">Erneut erinnern</button>
          <button type="button" class="rounded border px-3 py-2" @click="reminderDialog = false">Abbrechen</button>
        </div>
      </div>
    </div>
  </div>
</template>
