import { reactive } from 'vue'

export const auth = reactive({
  initialized: false,
  authenticated: false,
  csrfToken: '',
  username: '',
  async loadSession(): Promise<void> {
    const response = await fetch('/api/auth/session', { credentials: 'same-origin' })
    if (!response.ok) throw new Error('Session konnte nicht geladen werden')
    const body = await response.json() as { authenticated: boolean; csrf_token: string; username: string | null }
    auth.authenticated = body.authenticated
    auth.csrfToken = body.csrf_token
    auth.username = body.username ?? ''
    auth.initialized = true
  },
})
