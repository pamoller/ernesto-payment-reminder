import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createRouter, createMemoryHistory } from 'vue-router'
import App from './App.vue'
import LoginView from './components/LoginView.vue'
import SubscriptionListView from './components/SubscriptionListView.vue'
import SubscriptionDetailView from './components/SubscriptionDetailView.vue'
import { auth } from './auth'

function mountApp(initialPath = '/subscriptions') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', component: LoginView, meta: { public: true } },
      { path: '/subscriptions', component: SubscriptionListView },
      { path: '/subscriptions/new', component: SubscriptionDetailView },
      { path: '/subscriptions/:id', component: SubscriptionDetailView },
    ],
  })
  router.beforeEach(async (to) => {
    if (!auth.initialized) await auth.loadSession()
    if (to.meta.public !== true && !auth.authenticated) return '/login'
    if (to.meta.public === true && auth.authenticated) return '/subscriptions'
    return true
  })
  const wrapper = mount(App, { global: { plugins: [router] } })
  void router.push(initialPath)
  return { wrapper, router }
}

describe('App', () => {
  beforeEach(() => {
    auth.initialized = false
    auth.authenticated = false
    auth.csrfToken = ''
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('lädt und zeigt bestehende Abonnements', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
      const url = String(input)
      if (url === '/api/auth/session') {
        return new Response(JSON.stringify({ authenticated: true, csrf_token: 'test-csrf', username: 'admin' }), { status: 200 })
      }
      if (url === '/api/subscription-types') {
        return new Response(JSON.stringify({ subscription_types: [
          { id: 1, name: 'Skool Test', duration: '3 MONTHS', duration_months: 3, reminder: 'Text' },
        ] }), { status: 200 })
      }
      if (url.startsWith('/api/customers?')) {
        return new Response(JSON.stringify({
          customers: [{ id: 1, name: 'Ada Lovelace', email: 'ada@example.com', subscription_type_id: 1, duration_months: 3, start_date: '2026-01-01', expires_at: '2026-04-01', reminder_sent: null }],
          pagination: { total_pages: 1 },
        }), { status: 200 })
      }
      return new Response(JSON.stringify({ customers: [] }), { status: 200 })
    })

    const { wrapper } = mountApp()
    await flushPromises()
    expect(wrapper.text()).toContain('Ada Lovelace')
    expect(wrapper.text()).toContain('ada@example.com')
  })

  it('zeigt Login-Fehler direkt auf der Loginseite', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
      if (String(input) === '/api/auth/session') {
        return new Response(JSON.stringify({ authenticated: false, csrf_token: 'test-csrf', username: null }), { status: 200 })
      }
      return new Response(JSON.stringify({ error: 'Invalid credentials' }), { status: 401 })
    })

    const { wrapper } = mountApp('/login')
    await flushPromises()
    await wrapper.find('input[name="username"]').setValue('admin')
    await wrapper.find('input[name="password"]').setValue('wrong-password')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').text()).toBe('Invalid credentials')
  })

  it('sendet einen manuellen Reminder', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation(async (input, init) => {
      const url = String(input)
      if (url === '/api/auth/session') {
        return new Response(JSON.stringify({ authenticated: true, csrf_token: 'test-csrf', username: 'admin' }), { status: 200 })
      }
      if (url === '/api/subscription-types') {
        return new Response(JSON.stringify({ subscription_types: [
          { id: 1, name: 'Skool Test', duration: '3 MONTHS', duration_months: 3, reminder: 'Text' },
        ] }), { status: 200 })
      }
      if (url === '/api/customers/1/remind') {
        expect(init?.method).toBe('POST')
        return new Response(JSON.stringify({ sent: true, reminder_sent: '2026-03-20T12:00:00.123456+00:00' }), { status: 200 })
      }
      if (url.startsWith('/api/customers?')) {
        return new Response(JSON.stringify({
          customers: [{ id: 1, name: 'Ada Lovelace', email: 'ada@example.com', subscription_type_id: 1, duration_months: 3, start_date: '2026-03-01', expires_at: '2026-06-01', reminder_sent: null }],
          pagination: { total_pages: 1 },
        }), { status: 200 })
      }
      return new Response(JSON.stringify({}), { status: 200 })
    })

    const { wrapper } = mountApp()
    await flushPromises()
    const button = wrapper.findAll('button').find((item) => item.text() === 'Erinnern')
    expect(button).toBeDefined()
    await button?.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Erinnerungs-E-Mail an ada@example.com wurde versendet.')
  })
})
