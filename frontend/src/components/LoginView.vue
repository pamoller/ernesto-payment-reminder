<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth } from '../auth'

const router = useRouter()
const username = ref('')
const password = ref('')
const error = ref('')
const submitting = ref(false)

async function login(): Promise<void> {
  if (submitting.value) return
  submitting.value = true
  error.value = ''
  try {
    const body = await submitLogin(await auth.refreshCsrfToken())
    auth.authenticated = body.authenticated === true
    auth.csrfToken = body.csrf_token ?? auth.csrfToken
    auth.initialized = true
    password.value = ''
    await router.replace('/subscriptions')
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Unbekannter Fehler'
  } finally {
    submitting.value = false
  }
}

async function submitLogin(csrfToken: string): Promise<{
  authenticated?: boolean
  csrf_token?: string
  error?: string
}> {
  const response = await fetch('/api/auth/login', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    body: JSON.stringify({ username: username.value, password: password.value }),
  })
  const body = await response.json() as { authenticated?: boolean; csrf_token?: string; error?: string }
  if (response.status === 419) {
    const retry = await fetch('/api/auth/login', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': await auth.refreshCsrfToken(),
      },
      body: JSON.stringify({ username: username.value, password: password.value }),
    })
    const retryBody = await retry.json() as { authenticated?: boolean; csrf_token?: string; error?: string }
    if (!retry.ok) throw new Error(retryBody.error ?? 'Login fehlgeschlagen')
    return retryBody
  }
  if (!response.ok) throw new Error(body.error ?? 'Login fehlgeschlagen')
  return body
}
</script>

<template>
  <main class="grid min-h-screen place-items-center px-4">
    <form class="w-full max-w-sm rounded border border-ernesto-blue/10 bg-white p-6" @submit.prevent="login">
      <h1 class="-mx-6 -mt-6 mb-6 bg-ernesto-blue px-6 py-5 text-2xl font-bold text-white">
        Ernesto's Payment Reminder
      </h1>
      <p v-if="error" role="alert" class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
      <label class="mb-4 block text-sm font-semibold">
        Benutzername
        <input v-model="username" name="username" autocomplete="username" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2" />
      </label>
      <label class="mb-6 block text-sm font-semibold">
        Passwort
        <input v-model="password" name="password" type="password" autocomplete="current-password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2" />
      </label>
      <button type="submit" :disabled="submitting" class="w-full rounded bg-ernesto-blue px-4 py-2 font-semibold text-white disabled:opacity-60">
        {{ submitting ? 'Anmeldung läuft …' : 'Anmelden' }}
      </button>
    </form>
  </main>
</template>
