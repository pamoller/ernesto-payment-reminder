<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'

type Customer = {
  id: number
  name: string
  email: string
  subscription_type_id: number
  duration_months: number
  start_date: string
  expires_at: string
  reminder_sent: string | null
}

type SubscriptionType = {
  id: number
  name: string
  duration: string
  duration_months: number
  reminder: string
}

const customers = ref<Customer[]>([])
const subscriptionTypes = ref<SubscriptionType[]>([])
const error = ref('')
const success = ref('')
const username = ref('')
const authenticated = ref(false)
const csrfToken = ref('')
const remindingId = ref<number | null>(null)
const loginUsername = ref('')
const loginPassword = ref('')
const editingId = ref<number | null>(null)
const form = reactive({
  name: '',
  email: '',
  subscription_type_id: 0,
  start_date: new Date().toISOString().slice(0, 10),
})

async function loadSubscriptionTypes(): Promise<void> {
  const response = await fetch('/api/subscription-types')
  if (!response.ok) throw new Error('Abo-Typen konnten nicht geladen werden')
  const body = await response.json() as { subscription_types: SubscriptionType[] }
  subscriptionTypes.value = body.subscription_types
  if (form.subscription_type_id === 0 && subscriptionTypes.value.length > 0) {
    form.subscription_type_id = subscriptionTypes.value[0].id
  }
}

async function loadCustomers(): Promise<void> {
  const response = await fetch('/api/customers')
  if (!response.ok) throw new Error('Kunden konnten nicht geladen werden')
  const body = await response.json() as { customers: Customer[] }
  customers.value = body.customers
}

async function loadSession(): Promise<void> {
  const response = await fetch('/api/auth/session', { credentials: 'same-origin' })
  if (!response.ok) throw new Error('Session konnte nicht geladen werden')
  const body = await response.json() as {
    authenticated: boolean
    csrf_token: string
    username: string | null
  }
  authenticated.value = body.authenticated
  csrfToken.value = body.csrf_token
  username.value = body.username ?? ''
  if (body.authenticated) {
    await loadSubscriptionTypes()
    await loadCustomers()
  }
}

async function login(): Promise<void> {
  error.value = ''
  try {
    const response = await fetch('/api/auth/login', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken.value,
      },
      body: JSON.stringify({
        username: loginUsername.value,
        password: loginPassword.value,
      }),
    })
    const body = await response.json() as { authenticated?: boolean; csrf_token?: string; error?: string }
    if (!response.ok) throw new Error(body.error ?? 'Login fehlgeschlagen')
    authenticated.value = body.authenticated === true
    csrfToken.value = body.csrf_token ?? csrfToken.value
    loginPassword.value = ''
    await loadSubscriptionTypes()
    await loadCustomers()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  }
}

async function logout(): Promise<void> {
  error.value = ''
  try {
    const response = await fetch('/api/auth/logout', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrfToken.value },
    })
    if (!response.ok) throw new Error('Logout fehlgeschlagen')
    authenticated.value = false
    username.value = ''
    customers.value = []
    await loadSession()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  }
}

function resetForm(): void {
  editingId.value = null
  form.name = ''
  form.email = ''
  form.subscription_type_id = subscriptionTypes.value[0]?.id ?? 0
  form.start_date = new Date().toISOString().slice(0, 10)
  error.value = ''
}

async function saveCustomer(): Promise<void> {
  error.value = ''
  try {
    const response = await fetch(
      editingId.value === null ? '/api/customers' : `/api/customers/${editingId.value}`,
      {
        method: editingId.value === null ? 'POST' : 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken.value,
        },
        body: JSON.stringify(form),
      },
    )
    const body = await response.json() as { error?: string }
    if (!response.ok) throw new Error(body.error ?? 'Kunde konnte nicht gespeichert werden')
    await loadCustomers()
    resetForm()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  }
}

function editCustomer(customer: Customer): void {
  editingId.value = customer.id
  form.name = customer.name
  form.email = customer.email
  form.subscription_type_id = customer.subscription_type_id
  form.start_date = customer.start_date
  error.value = ''
}

function subscriptionTypeName(id: number): string {
  return subscriptionTypes.value.find((type) => type.id === id)?.name ?? 'Unbekannter Abo-Typ'
}

async function deleteCustomer(customer: Customer): Promise<void> {
  error.value = ''
  try {
    const response = await fetch(`/api/customers/${customer.id}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-Token': csrfToken.value },
    })
    if (!response.ok) throw new Error('Kunde konnte nicht gelöscht werden')
    await loadCustomers()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  }
}

async function remindCustomer(customer: Customer): Promise<void> {
  error.value = ''
  success.value = ''
  remindingId.value = customer.id
  try {
    const response = await fetch(`/api/customers/${customer.id}/remind`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrfToken.value },
    })
    const body = await response.json() as { error?: string; reminder_sent?: string }
    if (!response.ok) throw new Error(body.error ?? 'Reminder konnte nicht versendet werden')
    if (!body.reminder_sent) throw new Error('Backend lieferte keinen Versandzeitpunkt zurück')

    customers.value = customers.value.map((current) => current.id === customer.id
      ? { ...current, reminder_sent: body.reminder_sent }
      : current)
    success.value = `Reminder-E-Mail an ${customer.email} wurde versendet.`
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  } finally {
    remindingId.value = null
  }
}

onMounted(async () => {
  try {
    await loadSession()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  }
})
</script>

<template>
  <main>
    <h1>Ernesto Payment Reminder</h1>

    <form v-if="!authenticated" @submit.prevent="login">
      <label>Benutzername
        <input v-model="loginUsername" name="username" autocomplete="username" required />
      </label>
      <label>Passwort
        <input v-model="loginPassword" name="password" type="password" autocomplete="current-password" required />
      </label>
      <button type="submit">Anmelden</button>
    </form>

    <p v-if="error" role="alert">{{ error }}</p>
    <p v-if="success" role="status">{{ success }}</p>

    <template v-if="authenticated">
      <p>Angemeldet als {{ username }}</p>
      <button type="button" @click="logout">Abmelden</button>

      <form @submit.prevent="saveCustomer">
      <label>Name
        <input v-model="form.name" name="name" required />
      </label>
      <label>E-Mail
        <input v-model="form.email" name="email" type="email" required />
      </label>
      <label>Abo-Typ
        <select v-model.number="form.subscription_type_id" name="subscription_type_id" required>
          <option v-for="type in subscriptionTypes" :key="type.id" :value="type.id">
            {{ type.name }} ({{ type.duration_months }} Monate)
          </option>
        </select>
      </label>
      <label>Startdatum
        <input v-model="form.start_date" name="start_date" type="date" required />
      </label>
      <button type="submit">{{ editingId === null ? 'Kunde hinzufügen' : 'Kunde speichern' }}</button>
      <button v-if="editingId !== null" type="button" @click="resetForm">Abbrechen</button>
    </form>

      <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>E-Mail</th>
          <th>Laufzeit</th>
          <th>Startdatum</th>
          <th>Ablaufdatum</th>
          <th>Reminder gesendet</th>
          <th>Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="customer in customers" :key="customer.id">
          <td>{{ customer.name }}</td>
          <td>{{ customer.email }}</td>
          <td>{{ subscriptionTypeName(customer.subscription_type_id) }} ({{ customer.duration_months }} Monate)</td>
          <td>{{ customer.start_date }}</td>
          <td>{{ customer.expires_at }}</td>
          <td>{{ customer.reminder_sent ?? 'Noch nicht gesendet' }}</td>
          <td>
            <button
              type="button"
              :disabled="remindingId === customer.id"
              @click="remindCustomer(customer)"
            >
              {{ remindingId === customer.id ? 'Sende …' : 'Remind' }}
            </button>
            <button type="button" @click="editCustomer(customer)">Bearbeiten</button>
            <button type="button" @click="deleteCustomer(customer)">Löschen</button>
          </td>
        </tr>
      </tbody>
      </table>
    </template>
  </main>
</template>
