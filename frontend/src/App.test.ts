import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createRouter, createMemoryHistory } from 'vue-router'
import App from './App.vue'
import LoginView from './components/LoginView.vue'
import SubscriptionListView from './components/SubscriptionListView.vue'
import SubscriptionDetailView from './components/SubscriptionDetailView.vue'
import { auth } from './auth'

const mountedWrappers: VueWrapper[] = []

async function mountApp(initialPath = '/subscriptions') {
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
  await router.push(initialPath)
  const wrapper = mount(App, { global: { plugins: [router] } })
  mountedWrappers.push(wrapper)
  return { wrapper, router }
}

describe('App', () => {
  beforeEach(() => {
    auth.initialized = false
    auth.authenticated = false
    auth.csrfToken = ''
  })

  afterEach(() => {
    mountedWrappers.forEach((wrapper) => wrapper.unmount())
    mountedWrappers.length = 0
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
          customers: [{
            id: 1,
            name: 'Ada Lovelace',
            email: 'ada@example.com',
            subscription_type_id: 1,
            duration_months: 3,
            start_date: '2026-01-01',
            expires_at: '2026-04-01',
            reminder_sent: null,
            subscription_status_id: 2,
            subscription_status_name: 'Fällig',
            subscription_status_changed: '2026-04-01T00:00:00.000000+00:00',
            next_subscription_status_id: 3,
            next_subscription_status_name: 'Säumig',
            next_subscription_status_at: '2026-04-08T00:00:00.000000+00:00',
          }],
          pagination: { total_pages: 1 },
        }), { status: 200 })
      }
      return new Response(JSON.stringify({ customers: [] }), { status: 200 })
    })

    const { wrapper } = await mountApp()
    await flushPromises()
    expect(wrapper.text()).toContain('Ada Lovelace')
    expect(wrapper.text()).toContain('ada@example.com')
    expect(wrapper.text()).toContain('Fällig')
    expect(wrapper.text()).toContain('Tage')
  })

  it('zeigt Login-Fehler direkt auf der Loginseite', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
      if (String(input) === '/api/auth/session') {
        return new Response(JSON.stringify({ authenticated: false, csrf_token: 'test-csrf', username: null }), { status: 200 })
      }
      return new Response(JSON.stringify({ error: 'Invalid credentials' }), { status: 401 })
    })

    const { wrapper } = await mountApp('/login')
    await flushPromises()
    await wrapper.find('input[name="username"]').setValue('admin')
    await wrapper.find('input[name="password"]').setValue('wrong-password')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').text()).toBe('Invalid credentials')
  })

  it('öffnet die Einstellungen und zeigt Cron- sowie Testdaten-Aktion', async () => {
    let listLoads = 0
    vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
      const url = String(input)
      if (url === '/api/auth/session') {
        return new Response(JSON.stringify({ authenticated: true, csrf_token: 'test-csrf', username: 'admin' }), { status: 200 })
      }
      if (url === '/api/subscription-types') {
        return new Response(JSON.stringify({ subscription_types: [] }), { status: 200 })
      }
      if (url === '/api/subscription-statuses') {
        return new Response(JSON.stringify({ subscription_statuses: [] }), { status: 200 })
      }
      if (url.startsWith('/api/customers?')) {
        listLoads++
        return new Response(JSON.stringify({ customers: [], pagination: { total_pages: 1 } }), { status: 200 })
      }
      if (url === '/api/settings/test-data') {
        return new Response(JSON.stringify({ test_data: { id: 1, email: 'admin@example.com', status_id: 1 } }), { status: 200 })
      }
      return new Response(JSON.stringify({}), { status: 200 })
    })

    const { wrapper } = await mountApp()
    await flushPromises()
    await wrapper.find('button[aria-label="Einstellungen"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Cron starten:')
    expect(wrapper.text()).toContain('Testdaten einspielen:')
    expect(wrapper.findAll('button').filter((button) => button.text() === 'ok')).toHaveLength(2)
    const beforeRefresh = listLoads
    await wrapper.findAll('button').filter((button) => button.text() === 'ok')[1].trigger('click')
    await flushPromises()
    expect(listLoads).toBe(beforeRefresh + 1)
  })

  it('aktualisiert ein abgelaufenes CSRF-Token vor dem Login', async () => {
    let loginAttempts = 0
    vi.spyOn(globalThis, 'fetch').mockImplementation(async (input, init) => {
      const url = String(input)
      if (url === '/api/auth/session') {
        return new Response(JSON.stringify({
          authenticated: false,
          csrf_token: loginAttempts === 0 ? 'stale-csrf' : 'fresh-csrf',
          username: null,
        }), { status: 200 })
      }
      if (url === '/api/auth/login') {
        loginAttempts++
        const headers = init?.headers as Record<string, string>
        if (headers['X-CSRF-Token'] === 'stale-csrf') {
          return new Response(JSON.stringify({ error: 'Invalid CSRF token' }), { status: 419 })
        }
        return new Response(JSON.stringify({
          authenticated: true,
          csrf_token: 'fresh-csrf',
          username: 'admin',
        }), { status: 200 })
      }
      if (url === '/api/subscription-types') {
        return new Response(JSON.stringify({ subscription_types: [] }), { status: 200 })
      }
      if (url === '/api/subscription-statuses') {
        return new Response(JSON.stringify({ subscription_statuses: [] }), { status: 200 })
      }
      if (url.startsWith('/api/customers?')) {
        return new Response(JSON.stringify({
          customers: [],
          pagination: { total_pages: 1 },
        }), { status: 200 })
      }
      return new Response(JSON.stringify({ error: 'Unexpected request' }), { status: 500 })
    })

    const { wrapper } = await mountApp('/login')
    await flushPromises()
    await wrapper.find('input[name="username"]').setValue('admin')
    await wrapper.find('input[name="password"]').setValue('secret')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(loginAttempts).toBe(2)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('übermittelt die eingegebenen Werte beim ersten Speichern einer Subscription', async () => {
    let submitted: Record<string, unknown> | null = null
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
      if (url === '/api/customers' && init?.method === 'POST') {
        submitted = JSON.parse(String(init.body)) as Record<string, unknown>
        const customer = {
          ...submitted,
          id: 7,
          duration_months: 3,
          expires_at: '2027-02-26',
          reminder_sent: null,
        }
        return new Response(JSON.stringify({ customer }), { status: 201 })
      }
      if (url.startsWith('/api/customers?')) {
        return new Response(JSON.stringify({ customers: [], pagination: { total_pages: 1 } }), { status: 200 })
      }
      return new Response(JSON.stringify({}), { status: 200 })
    })

    const { wrapper } = await mountApp('/subscriptions/new')
    await flushPromises()
    await wrapper.find('#name').setValue('Test')
    await wrapper.find('#email').setValue('test@example.com')
    await wrapper.find('#start_date').setValue('2026-11-26')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(submitted).toMatchObject({
      name: 'Test',
      email: 'test@example.com',
      subscription_type_id: 1,
      start_date: '2026-11-26',
    })
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('zeigt in der Liste keinen Knopf Erinnern', async () => {
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
          customers: [{ id: 1, name: 'Ada Lovelace', email: 'ada@example.com', subscription_type_id: 1, duration_months: 3, start_date: '2026-03-01', expires_at: '2026-06-01', reminder_sent: null }],
          pagination: { total_pages: 1 },
        }), { status: 200 })
      }
      return new Response(JSON.stringify({}), { status: 200 })
    })

    const { wrapper } = await mountApp()
    await flushPromises()
    const button = wrapper.findAll('button').find((item) => item.text() === 'Erinnern')
    expect(button).toBeUndefined()
  })

  it('zeigt in der Liste eine Verlängern-Aktion', async () => {
    const customer = {
      id: 1,
      name: 'Ada Lovelace',
      email: 'ada@example.com',
      subscription_type_id: 1,
      duration_months: 3,
      start_date: '2026-03-01',
      expires_at: '2026-06-01',
      reminder_sent: '2026-03-10T09:00:00+00:00' as string | null,
    }
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
        return new Response(JSON.stringify({ customers: [customer], pagination: { total_pages: 1 } }), { status: 200 })
      }
      return new Response(JSON.stringify({}), { status: 200 })
    })

    const { wrapper } = await mountApp()
    await flushPromises()
    const renewButton = wrapper.findAll('button').find((item) => item.text() === 'Verlängern')
    expect(renewButton).toBeDefined()
  })

  it('speichert den gewählten Status und bietet Erinnern neben der Statusauswahl', async () => {
    let submitted: Record<string, unknown> | null = null
    let detailLoads = 0
    const makeCustomer = () => ({
      id: 1,
      name: 'Ada Lovelace',
      email: 'ada@example.com',
      subscription_type_id: 1,
      duration_months: 3,
      start_date: '2026-03-01',
      expires_at: '2026-06-01',
      reminder_sent: '2026-03-10T09:00:00+00:00',
      subscription_status_id: 4,
      subscription_status_name: 'Beendet',
      subscription_status_changed: '2026-06-01T00:00:00+00:00',
      next_subscription_status_id: 3,
      next_subscription_status_name: 'Säumig',
      next_subscription_status_at: '2026-06-08T00:00:00+00:00',
    })
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
      if (url === '/api/subscription-statuses') {
        return new Response(JSON.stringify({ subscription_statuses: [
          { id: 1, name: 'Bezahlt', duration: null, subject: null, message: null },
          { id: 2, name: 'Fällig', duration: '0 DAYS', subject: 'Betreff', message: 'Text' },
        ] }), { status: 200 })
      }
      if (url === '/api/customers/1' && init?.method === 'PUT') {
        submitted = JSON.parse(String(init.body)) as Record<string, unknown>
        return new Response(JSON.stringify({ customer: makeCustomer() }), { status: 200 })
      }
      if (url === '/api/customers/1') {
        detailLoads++
        return new Response(JSON.stringify({ customer: makeCustomer() }), { status: 200 })
      }
      if (url.startsWith('/api/customers?')) {
        return new Response(JSON.stringify({ customers: [makeCustomer()], pagination: { total_pages: 1 } }), { status: 200 })
      }
      return new Response(JSON.stringify({}), { status: 200 })
    })

    const { wrapper } = await mountApp('/subscriptions/1')
    await flushPromises()
    const detail = wrapper.find('[aria-labelledby="subscription-detail-title"]')

    expect(detail.find('#subscription_status_id').exists()).toBe(true)
    expect(detail.findAll('button').find((item) => item.text() === 'Erinnern')).toBeDefined()
    expect(detail.findAll('button').find((item) => item.text() === 'Verlängern')).toBeUndefined()
    expect(detail.findAll('button').find((item) => item.text() === 'Reaktivieren')).toBeUndefined()
    expect(detail.text()).not.toContain('E-Mail erneut senden')
    await detail.find('#subscription_status_id').setValue('1')
    await detail.find('form').trigger('submit')
    await flushPromises()
    expect(submitted).toMatchObject({ subscription_status_id: 1 })
    expect(detailLoads).toBe(1)
  })

  it('lädt die Abonnementliste nach dem Speichern in der Detailansicht erneut', async () => {
    let listLoads = 0
    const customer = {
      id: 1,
      name: 'Ada Lovelace',
      email: 'ada@example.com',
      subscription_type_id: 1,
      duration_months: 3,
      start_date: '2026-03-01',
      expires_at: '2026-06-01',
      reminder_sent: null,
    }
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
      if (url.startsWith('/api/customers?')) {
        listLoads++
        return new Response(JSON.stringify({ customers: [customer], pagination: { total_pages: 1 } }), { status: 200 })
      }
      if (url === '/api/customers/1' && init?.method === 'PUT') {
        return new Response(JSON.stringify({ customer }), { status: 200 })
      }
      if (url === '/api/customers/1') {
        return new Response(JSON.stringify({ customer }), { status: 200 })
      }
      return new Response(JSON.stringify({}), { status: 200 })
    })

    const { wrapper } = await mountApp('/subscriptions/1')
    await flushPromises()
    expect(listLoads).toBe(1)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(listLoads).toBe(2)
    expect(wrapper.text()).toContain('Ada Lovelace')
  })
})
