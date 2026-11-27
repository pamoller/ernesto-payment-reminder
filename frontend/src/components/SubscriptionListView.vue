<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth } from '../auth'
import { SUBSCRIPTIONS_REFRESH_EVENT, type Customer, type SubscriptionStatus, type SubscriptionType } from '../types'

type PendingAction = {
  type: 'remind' | 'renew'
  customer: Customer
}

const router = useRouter()
const route = useRoute()
const customers = ref<Customer[]>([])
const types = ref<SubscriptionType[]>([])
const statuses = ref<SubscriptionStatus[]>([])
const loading = ref(true)
const error = ref('')
const success = ref('')
const processingId = ref<number | null>(null)
const pendingAction = ref<PendingAction | null>(null)
const page = ref(Number(route.query.page ?? 1))
const sort = ref(String(route.query.sort ?? 'expires_at'))
const direction = ref(String(route.query.direction ?? 'asc'))
const perPage = 25
const totalPages = ref(1)

async function loadTypes(): Promise<void> {
  const response = await fetch('/api/subscription-types')
  if (!response.ok) throw new Error('Abonnementtypen konnten nicht geladen werden')
  types.value = (await response.json() as { subscription_types: SubscriptionType[] }).subscription_types
}

async function loadStatuses(): Promise<void> {
  const response = await fetch('/api/subscription-statuses')
  if (!response.ok) throw new Error('Abonnementstatus konnten nicht geladen werden')
  const body = await response.json() as { subscription_statuses?: SubscriptionStatus[] }
  statuses.value = body.subscription_statuses ?? []
}

async function loadCustomers(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams({ page: String(page.value), per_page: String(perPage), sort: sort.value, direction: direction.value })
    const response = await fetch(`/api/customers?${params}`)
    const body = await response.json() as { customers?: Customer[]; pagination?: { total_pages: number }; error?: string }
    if (!response.ok) throw new Error(body.error ?? 'Abonnements konnten nicht geladen werden')
    customers.value = body.customers ?? []
    totalPages.value = body.pagination?.total_pages ?? 1
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  } finally {
    loading.value = false
  }
}

function updateQuery(): void {
  void router.replace({ query: { page: page.value, sort: sort.value, direction: direction.value } })
}

function setSort(field: string): void {
  if (sort.value === field) direction.value = direction.value === 'asc' ? 'desc' : 'asc'
  else {
    sort.value = field
    direction.value = 'asc'
  }
  page.value = 1
  updateQuery()
  void loadCustomers()
}

function requestAction(type: PendingAction['type'], customer: Customer): void {
  pendingAction.value = { type, customer }
}

async function confirmAction(): Promise<void> {
  const action = pendingAction.value
  if (!action || processingId.value !== null) return

  pendingAction.value = null
  processingId.value = action.customer.id
  success.value = ''
  error.value = ''
  try {
    if (action.type === 'renew') {
      const response = await fetch(`/api/customers/${action.customer.id}/renew`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': auth.csrfToken },
      })
      const body = await response.json() as { customer?: Customer; error?: string }
      if (!response.ok) throw new Error(body.error ?? 'Abonnement konnte nicht verlängert werden')
      await loadCustomers()
      success.value = `Abonnement von ${action.customer.name} wurde verlängert.`
    } else {
      const authorised = action.customer.reminder_sent !== null
      const response = await fetch(`/api/customers/${action.customer.id}/remind`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrfToken },
        body: JSON.stringify({ authorise_repeated_reminder: authorised }),
      })
      const body = await response.json() as { reminder_sent?: string | null; reset?: boolean; error?: string }
      if (!response.ok) throw new Error(body.error ?? 'Erinnerungsaktion konnte nicht abgeschlossen werden')
      await loadCustomers()
      success.value = body.reset
        ? 'Erinnerungsstatus zurückgesetzt. Es wurde keine E-Mail versendet.'
        : `Erinnerungs-E-Mail an ${action.customer.email} wurde versendet.`
    }
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  } finally {
    processingId.value = null
  }
}

function remainingDays(customer: Customer): number {
  const today = new Date()
  const end = new Date(`${customer.expires_at}T00:00:00`)
  return Math.ceil((end.getTime() - today.getTime()) / 86400000)
}

function state(customer: Customer): 'expired' | 'soon' | 'active' {
  const remaining = remainingDays(customer)
  if (remaining < 0) return 'expired'
  if (remaining <= 7) return 'soon'
  return 'active'
}

function typeName(id: number): string {
  return types.value.find((type) => type.id === id)?.name ?? ''
}

function formatDate(value: string): string {
  const [year, month, day] = value.slice(0, 10).split('-')
  return `${day}.${month}.${year}`
}

function formatTimestamp(value: string | null | undefined, fallback = 'Noch nicht erinnert'): string {
  if (!value) return fallback
  const date = new Date(value)
  return new Intl.DateTimeFormat('de-DE', { timeZone: 'Europe/Berlin', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date)
}

function statusBadge(statusId: number): string {
  return {
    1: 'bg-emerald-100 text-emerald-800',
    2: 'bg-yellow-100 text-yellow-800',
    3: 'bg-orange-100 text-orange-800',
    4: 'bg-slate-200 text-slate-800',
  }[statusId] ?? 'bg-slate-100 text-slate-800'
}

function statusName(customer: Customer): string {
  return statuses.value.find((status) => status.id === customer.subscription_status_id)?.name
    ?? customer.subscription_status_name
    ?? 'Unbekannt'
}

function open(customer: Customer): void {
  void router.push(`/subscriptions/${customer.id}`)
}

function changePage(next: number): void {
  page.value = next
  updateQuery()
  void loadCustomers()
}

function refreshCustomers(): void {
  void loadCustomers()
}

watch(() => route.query, () => {
  page.value = Number(route.query.page ?? 1)
  sort.value = String(route.query.sort ?? 'expires_at')
  direction.value = String(route.query.direction ?? 'asc')
})

onMounted(async () => {
  window.addEventListener(SUBSCRIPTIONS_REFRESH_EVENT, refreshCustomers)
  await Promise.all([loadTypes(), loadStatuses(), loadCustomers()])
})

onBeforeUnmount(() => window.removeEventListener(SUBSCRIPTIONS_REFRESH_EVENT, refreshCustomers))
</script>

<template>
  <main class="mx-auto max-w-6xl px-4 py-6">
    <header class="mb-5 rounded bg-ernesto-blue px-4 py-3 text-white">
      <h1 class="text-2xl font-bold">Abonnements</h1>
    </header>
    <p v-if="error" role="alert" class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-700">{{ error }}</p>
    <p v-if="success" role="status" class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">{{ success }}</p>

    <div v-if="loading" class="py-10 text-center text-slate-500">Abonnements werden geladen …</div>
    <div v-else-if="customers.length === 0" class="rounded border border-slate-200 bg-white px-4 py-10 text-center text-slate-500">
      Keine Abonnements vorhanden.
    </div>
    <div v-else class="overflow-x-auto border border-slate-200 bg-white">
      <table class="w-full border-collapse text-left text-sm">
        <thead class="bg-ernesto-blue text-white">
          <tr>
            <th v-for="column in [
              ['name','Name'],['email','E-Mail'],['subscription_type_id','Abonnement'],
              ['start_date','Beginn'],['expires_at','Ende']
            ]" :key="column[0]" class="border-b border-ernesto-blue-dark px-3 py-3">
              <button type="button" class="font-semibold text-white" @click="setSort(column[0])">
                {{ column[1] }}<span v-if="sort === column[0]" aria-label="Sortierreihenfolge">{{ direction === 'asc' ? ' ↑' : ' ↓' }}</span>
              </button>
            </th>
            <th class="border-b border-ernesto-blue-dark px-3 py-3">Tage</th>
            <th class="border-b border-ernesto-blue-dark px-3 py-3">Status</th>
            <th class="border-b border-ernesto-blue-dark px-3 py-3">Erinnert am</th>
            <th class="border-b border-ernesto-blue-dark px-3 py-3">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="customer in customers"
            :key="customer.id"
            tabindex="0"
            class="cursor-pointer border-b hover:bg-blue-50/70"
            @click="open(customer)"
            @keydown.enter.prevent="open(customer)"
            @keydown.space.prevent="open(customer)"
          >
            <td class="px-3 py-3">{{ customer.name }}</td>
            <td class="px-3 py-3">{{ customer.email }}</td>
            <td class="px-3 py-3">{{ typeName(customer.subscription_type_id) }}</td>
            <td class="px-3 py-3">{{ formatDate(customer.start_date) }}</td>
            <td class="px-3 py-3">{{ formatDate(customer.expires_at) }}</td>
            <td class="px-3 py-3">
              <span :class="{
                'text-red-700': state(customer) === 'expired',
                'text-amber-700': state(customer) === 'soon',
                'text-ernesto-blue': state(customer) === 'active'
              }">{{ remainingDays(customer) }}</span>
            </td>
            <td class="px-3 py-3">
              <span
                class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                :class="statusBadge(customer.subscription_status_id)"
              >{{ statusName(customer) }}</span>
            </td>
            <td class="px-3 py-3">{{ formatTimestamp(customer.reminder_sent) }}</td>
            <td class="px-3 py-3" @click.stop>
              <button
                type="button"
                :disabled="processingId === customer.id"
                class="rounded bg-ernesto-blue px-3 py-1.5 text-white disabled:opacity-60"
                @click="requestAction('remind', customer)"
              >Erinnern</button>
              <button
                type="button"
                :disabled="processingId === customer.id"
                class="ml-2 rounded bg-ernesto-blue px-3 py-1.5 text-white disabled:opacity-60"
                @click="requestAction('renew', customer)"
              >Verlängern</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav v-if="totalPages > 1" class="mt-4 flex items-center justify-center gap-3" aria-label="Seitennavigation">
      <button type="button" :disabled="page <= 1" class="rounded border px-3 py-1.5 disabled:opacity-40" @click="changePage(page - 1)">Zurück</button>
      <span>Seite {{ page }} von {{ totalPages }}</span>
      <button type="button" :disabled="page >= totalPages" class="rounded border px-3 py-1.5 disabled:opacity-40" @click="changePage(page + 1)">Weiter</button>
    </nav>

    <div
      v-if="pendingAction"
      class="fixed inset-0 z-30 grid place-items-center bg-slate-950/60 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="subscription-action-title"
      @click.self="pendingAction = null"
      @keydown.esc="pendingAction = null"
    >
      <section class="w-full max-w-md rounded border-2 border-ernesto-blue bg-ernesto-blue p-6 text-white">
        <h2 id="subscription-action-title" class="text-xl font-bold">
          {{ pendingAction.type === 'renew' ? 'Abonnement verlängern?' : 'Erinnerung bestätigen' }}
        </h2>
        <p v-if="pendingAction.type === 'renew'" class="mt-3">
          {{ pendingAction.customer.name }} wird ab heute um {{ pendingAction.customer.duration_months }} Monate verlängert.
          Der Erinnerungsstatus wird zurückgesetzt.
        </p>
        <p v-else-if="pendingAction.customer.reminder_sent" class="mt-3">
          Soll der Erinnerungsstatus für {{ pendingAction.customer.email }} zurückgesetzt werden? Dabei wird keine E-Mail versendet.
        </p>
        <p v-else class="mt-3">
          Soll eine Zahlungserinnerung an {{ pendingAction.customer.email }} versendet werden?
        </p>
        <div class="mt-5 flex flex-row flex-wrap justify-start gap-2">
          <button type="button" class="rounded border border-white bg-ernesto-blue px-3 py-2 text-white" @click="confirmAction">
            {{ pendingAction.type === 'renew'
              ? 'Verlängern'
              : pendingAction.customer.reminder_sent ? 'Status zurücksetzen' : 'Erinnern' }}
          </button>
          <button type="button" class="rounded border border-white bg-ernesto-blue px-3 py-2 text-white" @click="pendingAction = null">
            Abbrechen
          </button>
        </div>
      </section>
    </div>
  </main>
</template>
