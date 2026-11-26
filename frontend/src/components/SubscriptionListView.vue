<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth } from '../auth'
import type { Customer, SubscriptionType } from '../types'

const router = useRouter()
const route = useRoute()
const customers = ref<Customer[]>([])
const types = ref<SubscriptionType[]>([])
const loading = ref(true)
const error = ref('')
const success = ref('')
const remindingId = ref<number | null>(null)
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

async function remind(customer: Customer): Promise<void> {
  if (remindingId.value !== null) return
  remindingId.value = customer.id
  success.value = ''
  error.value = ''
  try {
    const authorised = customer.reminder_sent !== null
    if (authorised) {
      if (!window.confirm(`Soll eine weitere Zahlungserinnerung an diese E-Mail-Adresse versendet werden?\n${customer.email}`)) return
    }
    const response = await fetch(`/api/customers/${customer.id}/remind`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrfToken },
      body: JSON.stringify({ authorise_repeated_reminder: authorised }),
    })
    const body = await response.json() as { reminder_sent?: string; error?: string }
    if (!response.ok) throw new Error(body.error ?? 'Erinnerung konnte nicht versendet werden')
    await loadCustomers()
    success.value = `Erinnerungs-E-Mail an ${customer.email} wurde versendet.`
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  } finally {
    remindingId.value = null
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

function formatTimestamp(value: string | null): string {
  if (!value) return 'Noch nicht erinnert'
  const date = new Date(value)
  return new Intl.DateTimeFormat('de-DE', { timeZone: 'Europe/Berlin', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date)
}

function open(customer: Customer): void {
  void router.push(`/subscriptions/${customer.id}`)
}

function changePage(next: number): void {
  page.value = next
  updateQuery()
  void loadCustomers()
}

watch(() => route.query, () => {
  page.value = Number(route.query.page ?? 1)
  sort.value = String(route.query.sort ?? 'expires_at')
  direction.value = String(route.query.direction ?? 'asc')
})

onMounted(async () => {
  await Promise.all([loadTypes(), loadCustomers()])
})
</script>

<template>
  <main class="mx-auto max-w-6xl px-4 py-6">
    <h1 class="mb-5 text-2xl font-bold text-ernesto-green">Abonnements</h1>
    <p v-if="error" role="alert" class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-700">{{ error }}</p>
    <p v-if="success" role="status" class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">{{ success }}</p>

    <div v-if="loading" class="py-10 text-center text-slate-500">Abonnements werden geladen …</div>
    <div v-else-if="customers.length === 0" class="rounded border border-slate-200 bg-white px-4 py-10 text-center text-slate-500">
      Keine Abonnements vorhanden.
    </div>
    <div v-else class="overflow-x-auto border border-slate-200 bg-white">
      <table class="w-full border-collapse text-left text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th v-for="column in [
              ['name','Name'],['email','E-Mail'],['subscription_type_id','Abonnement'],
              ['start_date','Beginn'],['expires_at','Ende'],['reminder_sent','Erinnert am']
            ]" :key="column[0]" class="border-b px-3 py-3">
              <button type="button" class="font-semibold" @click="setSort(column[0])">
                {{ column[1] }}<span v-if="sort === column[0]" aria-label="Sortierreihenfolge">{{ direction === 'asc' ? ' ↑' : ' ↓' }}</span>
              </button>
            </th>
            <th class="border-b px-3 py-3">Verbleibende Tage</th>
            <th class="border-b px-3 py-3">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="customer in customers"
            :key="customer.id"
            tabindex="0"
            class="cursor-pointer border-b hover:bg-emerald-50/50"
            @click="open(customer)"
            @keydown.enter.prevent="open(customer)"
            @keydown.space.prevent="open(customer)"
          >
            <td class="px-3 py-3">{{ customer.name }}</td>
            <td class="px-3 py-3">{{ customer.email }}</td>
            <td class="px-3 py-3">{{ typeName(customer.subscription_type_id) }}</td>
            <td class="px-3 py-3">{{ formatDate(customer.start_date) }}</td>
            <td class="px-3 py-3">{{ formatDate(customer.expires_at) }}</td>
            <td class="px-3 py-3">{{ formatTimestamp(customer.reminder_sent) }}</td>
            <td class="px-3 py-3">
              <span :class="{
                'text-red-700': state(customer) === 'expired',
                'text-amber-700': state(customer) === 'soon',
                'text-ernesto-green': state(customer) === 'active'
              }">{{ remainingDays(customer) }}</span>
            </td>
            <td class="px-3 py-3" @click.stop>
              <button
                v-if="customer.reminder_sent === null"
                type="button"
                :disabled="remindingId === customer.id"
                class="rounded bg-ernesto-green px-3 py-1.5 text-white disabled:opacity-60"
                @click="remind(customer)"
              >{{ remindingId === customer.id ? 'Sendet …' : 'Erinnern' }}</button>
              <span v-else class="text-slate-500">{{ formatTimestamp(customer.reminder_sent) }}</span>
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
  </main>
</template>
