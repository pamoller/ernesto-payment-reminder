<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { onBeforeUnmount } from 'vue'
import SubscriptionForm from './SubscriptionForm.vue'
import { auth } from '../auth'
import { SUBSCRIPTIONS_REFRESH_EVENT, type Customer, type SubscriptionStatus, type SubscriptionType } from '../types'

const route = useRoute()
const router = useRouter()
const id = computed(() => route.params.id ? Number(route.params.id) : null)
const types = ref<SubscriptionType[]>([])
const statuses = ref<SubscriptionStatus[]>([])
const customer = ref<Customer | null>(null)
const original = ref<Customer | null>(null)
const form = reactive({
  name: '',
  email: '',
  subscription_type_id: 0,
  subscription_status_id: 1,
  start_date: new Date().toISOString().slice(0, 10),
})
const busy = ref(false)
const error = ref('')
const success = ref('')
const resendDialog = ref(false)

function close(): void {
  void router.push('/subscriptions')
}

function closeOnEscape(event: KeyboardEvent): void {
  if (event.key === 'Escape') close()
}

const dirty = computed(() => {
  if (!original.value) return form.name !== '' || form.email !== '' || form.start_date !== new Date().toISOString().slice(0, 10)
  return form.name !== original.value.name || form.email !== original.value.email
    || form.subscription_type_id !== original.value.subscription_type_id
    || form.subscription_status_id !== original.value.subscription_status_id
    || form.start_date !== original.value.start_date
})

function updateForm(value: typeof form): void {
  Object.assign(form, value)
}

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

async function loadStatuses(): Promise<void> {
  const response = await fetch('/api/subscription-statuses')
  if (!response.ok) throw new Error('Abonnementstatus konnten nicht geladen werden')
  const body = await response.json() as { subscription_statuses?: SubscriptionStatus[] }
  statuses.value = body.subscription_statuses ?? []
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
    subscription_status_id: body.customer.subscription_status_id,
    start_date: body.customer.start_date,
  })
}

async function save(): Promise<void> {
  if (busy.value) return
  error.value = ''
  success.value = ''

  await persist()
}

async function persist(): Promise<void> {
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
        subscription_status_id: body.customer.subscription_status_id,
        start_date: body.customer.start_date,
      })
    }
    window.dispatchEvent(new Event(SUBSCRIPTIONS_REFRESH_EVENT))
    await router.replace('/subscriptions')
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  } finally {
    busy.value = false
  }
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

async function runLifecycleAction(
  path: string,
  method = 'POST',
  body?: Record<string, unknown>,
): Promise<void> {
  if (!id.value || busy.value) return
  busy.value = true
  error.value = ''
  success.value = ''
  try {
    const response = await fetch(`/api/customers/${id.value}/${path}`, {
      method,
      headers: body
        ? { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrfToken }
        : { 'X-CSRF-Token': auth.csrfToken },
      body: body ? JSON.stringify(body) : undefined,
    })
    const payload = await response.json() as {
      changed?: boolean
      email_sent?: boolean
      email_error?: string | null
      error?: string
    }
    if (!response.ok) throw new Error(payload.error ?? 'Aktion konnte nicht abgeschlossen werden')
    await loadCustomer()
    window.dispatchEvent(new Event(SUBSCRIPTIONS_REFRESH_EVENT))
    success.value = payload.email_error
      ?? (payload.email_sent ? 'Status wurde geändert und die E-Mail versendet.'
        : payload.changed ? 'Status wurde geändert.' : 'Aktion wurde abgeschlossen.')
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  } finally {
    busy.value = false
  }
}

function confirmResend(): void {
  resendDialog.value = false
  void runLifecycleAction('status-email')
}

watch(id, async () => {
  error.value = ''
  success.value = ''
  await Promise.all([loadTypes(), loadStatuses(), loadCustomer()])
})

onMounted(async () => {
  window.addEventListener('keydown', closeOnEscape)
  try {
    await Promise.all([loadTypes(), loadStatuses(), loadCustomer()])
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
      class="mx-auto w-full max-w-3xl overflow-hidden rounded-lg border-2 border-ernesto-blue bg-white text-slate-800 shadow-2xl"
      role="dialog"
      aria-modal="true"
      aria-labelledby="subscription-detail-title"
    >
      <header class="flex items-center justify-between border-b border-ernesto-blue-dark bg-ernesto-blue px-5 py-4 text-white sm:px-7">
        <h1 id="subscription-detail-title" class="text-xl font-bold text-white sm:text-2xl">
          {{ id ? 'Abonnement bearbeiten' : 'Abonnement hinzufügen' }}
        </h1>
        <button type="button" aria-label="Schließen" class="rounded px-3 py-1 text-2xl leading-none text-white" @click="close">×</button>
      </header>
      <div class="p-5 sm:p-7">
        <p v-if="error" role="alert" class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-700">{{ error }}</p>
        <p v-if="success" role="status" class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">{{ success }}</p>

        <SubscriptionForm
          :model-value="form"
          :subscription-types="types"
          :subscription-statuses="statuses"
          :can-remind="id !== null && !dirty"
          :busy="busy"
          @update:model-value="updateForm"
          @submit="save"
          @remind="resendDialog = true"
        >
          <template #actions>
            <div class="flex flex-wrap items-center gap-3 border-t border-ernesto-blue/20 pt-5">
              <button type="submit" :disabled="busy" class="rounded bg-ernesto-blue px-4 py-2 font-semibold text-white disabled:opacity-60">
                {{ busy ? 'Speichert …' : 'Speichern' }}
              </button>
              <button type="button" class="rounded bg-ernesto-blue px-4 py-2 text-white" @click="close">Abbrechen</button>
              <button v-if="id" type="button" :disabled="busy" class="rounded border border-red-200 px-4 py-2 text-red-700" @click="deleteCustomer">Löschen</button>
              <div v-if="id" class="ml-auto flex gap-2">
                <button
                  v-if="customer && [2, 3].includes(customer.subscription_status_id)"
                  type="button"
                  :disabled="dirty || busy"
                  class="rounded bg-emerald-700 px-4 py-2 text-white disabled:opacity-50"
                  @click="runLifecycleAction('payment')"
                >Zahlung registrieren</button>
              </div>
            </div>
          </template>
        </SubscriptionForm>
      </div>
    </section>

    <div v-if="resendDialog" class="fixed inset-0 z-40 grid place-items-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="resend-title">
      <div class="w-full max-w-md rounded border-2 border-ernesto-blue bg-white p-6 text-slate-800">
        <h2 id="resend-title" class="text-xl font-bold">E-Mail erneut senden?</h2>
        <p class="mt-3">Die E-Mail des aktuellen Status wird erneut an {{ customer?.email }} versendet. Der Abo-Status, das Fälligkeitsdatum und der Statuszeitpunkt bleiben unverändert.</p>
        <div class="mt-5 flex gap-2">
          <button type="button" class="rounded bg-ernesto-blue px-3 py-2 text-white" @click="confirmResend">E-Mail senden</button>
          <button type="button" class="rounded border px-3 py-2" @click="resendDialog = false">Abbrechen</button>
        </div>
      </div>
    </div>

  </div>
</template>
