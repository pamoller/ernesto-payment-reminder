<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'

type Customer = {
  id: number
  name: string
  email: string
  duration_months: number
  start_date: string
  expires_at: string
  reminder_sent: string | null
}

const customers = ref<Customer[]>([])
const error = ref('')
const editingId = ref<number | null>(null)
const form = reactive({
  name: '',
  email: '',
  duration_months: 3,
  start_date: new Date().toISOString().slice(0, 10),
})

async function loadCustomers(): Promise<void> {
  const response = await fetch('/api/customers')
  if (!response.ok) throw new Error('Kunden konnten nicht geladen werden')
  const body = await response.json() as { customers: Customer[] }
  customers.value = body.customers
}

function resetForm(): void {
  editingId.value = null
  form.name = ''
  form.email = ''
  form.duration_months = 3
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
        headers: { 'Content-Type': 'application/json' },
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
  form.duration_months = customer.duration_months
  form.start_date = customer.start_date
  error.value = ''
}

async function deleteCustomer(customer: Customer): Promise<void> {
  error.value = ''
  try {
    const response = await fetch(`/api/customers/${customer.id}`, { method: 'DELETE' })
    if (!response.ok) throw new Error('Kunde konnte nicht gelöscht werden')
    await loadCustomers()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  }
}

onMounted(async () => {
  try {
    await loadCustomers()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  }
})
</script>

<template>
  <main>
    <h1>Ernesto Payment Reminder</h1>

    <form @submit.prevent="saveCustomer">
      <label>Name
        <input v-model="form.name" name="name" required />
      </label>
      <label>E-Mail
        <input v-model="form.email" name="email" type="email" required />
      </label>
      <label>Laufzeit
        <select v-model.number="form.duration_months" name="duration_months">
          <option :value="3">3 Monate</option>
          <option :value="6">6 Monate</option>
          <option :value="12">12 Monate</option>
        </select>
      </label>
      <label>Startdatum
        <input v-model="form.start_date" name="start_date" type="date" required />
      </label>
      <button type="submit">{{ editingId === null ? 'Kunde hinzufügen' : 'Kunde speichern' }}</button>
      <button v-if="editingId !== null" type="button" @click="resetForm">Abbrechen</button>
    </form>

    <p v-if="error" role="alert">{{ error }}</p>

    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>E-Mail</th>
          <th>Laufzeit</th>
          <th>Startdatum</th>
          <th>Ablaufdatum</th>
          <th>Aktionen</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="customer in customers" :key="customer.id">
          <td>{{ customer.name }}</td>
          <td>{{ customer.email }}</td>
          <td>{{ customer.duration_months }} Monate</td>
          <td>{{ customer.start_date }}</td>
          <td>{{ customer.expires_at }}</td>
          <td>
            <button type="button" @click="editCustomer(customer)">Bearbeiten</button>
            <button type="button" @click="deleteCustomer(customer)">Löschen</button>
          </td>
        </tr>
      </tbody>
    </table>
  </main>
</template>
