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
    expect(wrapper.text()).toContain('Säumig am')
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

    const { wrapper } = await mountApp()
    await flushPromises()
    const button = wrapper.findAll('button').find((item) => item.text() === 'Erinnern')
    expect(button).toBeDefined()
    await button?.trigger('click')
    await flushPromises()
    const confirmButton = wrapper.findAll('[role="dialog"] button').find((item) => item.text() === 'Erinnern')
    expect(confirmButton).toBeDefined()
    await confirmButton?.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Erinnerungs-E-Mail an ada@example.com wurde versendet.')
  })

  it('zeigt auch bei bereits erinnerten Abonnements den Knopf Erinnern', async () => {
    let reminderSent: string | null = '2026-03-10T09:00:00+00:00'
    const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation(async (input, init) => {
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
        expect(JSON.parse(String(init?.body))).toEqual({ authorise_repeated_reminder: true })
        reminderSent = null
        return new Response(JSON.stringify({ sent: false, reset: true, reminder_sent: null }), { status: 200 })
      }
      if (url.startsWith('/api/customers?')) {
        return new Response(JSON.stringify({
          customers: [{ id: 1, name: 'Ada Lovelace', email: 'ada@example.com', subscription_type_id: 1, duration_months: 3, start_date: '2026-03-01', expires_at: '2026-06-01', reminder_sent: reminderSent }],
          pagination: { total_pages: 1 },
        }), { status: 200 })
      }
      return new Response(JSON.stringify({}), { status: 200 })
    })

    const { wrapper } = await mountApp()
    await flushPromises()
    const button = wrapper.findAll('button').find((item) => item.text() === 'Erinnern')
    expect(button).toBeDefined()
    await button?.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Dabei wird keine E-Mail versendet.')
    const resetButton = wrapper.findAll('[role="dialog"] button').find((item) => item.text() === 'Status zurücksetzen')
    expect(resetButton).toBeDefined()
    await resetButton?.trigger('click')
    await flushPromises()
    expect(fetch).toHaveBeenCalledWith('/api/customers/1/remind', expect.any(Object))
    expect(wrapper.text()).toContain('Erinnerungsstatus zurückgesetzt. Es wurde keine E-Mail versendet.')
  })

  it('bestätigt die Verlängerung in der Liste und lädt die Liste erneut', async () => {
    let listLoads = 0
    let startDate = '2026-03-01'
    const customer = {
      id: 1,
      name: 'Ada Lovelace',
      email: 'ada@example.com',
      subscription_type_id: 1,
      duration_months: 3,
      start_date: startDate,
      expires_at: '2026-06-01',
      reminder_sent: '2026-03-10T09:00:00+00:00' as string | null,
    }
    const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation(async (input, init) => {
      const url = String(input)
      if (url === '/api/auth/session') {
        return new Response(JSON.stringify({ authenticated: true, csrf_token: 'test-csrf', username: 'admin' }), { status: 200 })
      }
      if (url === '/api/subscription-types') {
        return new Response(JSON.stringify({ subscription_types: [
          { id: 1, name: 'Skool Test', duration: '3 MONTHS', duration_months: 3, reminder: 'Text' },
        ] }), { status: 200 })
      }
      if (url === '/api/customers/1/renew') {
        expect(init?.method).toBe('POST')
        startDate = '2026-11-26'
        customer.start_date = startDate
        customer.expires_at = '2027-02-26'
        customer.reminder_sent = null
        return new Response(JSON.stringify({ customer }), { status: 200 })
      }
      if (url.startsWith('/api/customers?')) {
        listLoads++
        return new Response(JSON.stringify({ customers: [customer], pagination: { total_pages: 1 } }), { status: 200 })
      }
      return new Response(JSON.stringify({}), { status: 200 })
    })

    const { wrapper } = await mountApp()
    await flushPromises()
    const renewButton = wrapper.findAll('button').find((item) => item.text() === 'Verlängern')
    expect(renewButton).toBeDefined()
    await renewButton?.trigger('click')
    await flushPromises()
    const confirmButton = wrapper.findAll('[role="dialog"] button').find((item) => item.text() === 'Verlängern')
    expect(confirmButton).toBeDefined()
    await confirmButton?.trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/customers/1/renew', expect.any(Object))
    expect(listLoads).toBe(2)
    expect(wrapper.text()).toContain('Abonnement von Ada Lovelace wurde verlängert.')
  })

  it('lädt die Detailansicht nach Verlängern und Erinnern erneut', async () => {
    let startDate = '2026-03-01'
    let reminderSent: string | null = null
    let detailLoads = 0
    const makeCustomer = () => ({
      id: 1,
      name: 'Ada Lovelace',
      email: 'ada@example.com',
      subscription_type_id: 1,
      duration_months: 3,
      start_date: startDate,
      expires_at: startDate === '2026-11-26' ? '2027-02-26' : '2026-06-01',
      reminder_sent: reminderSent,
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
      if (url === '/api/customers/1/renew') {
        startDate = '2026-11-26'
        reminderSent = null
        return new Response(JSON.stringify({ customer: makeCustomer() }), { status: 200 })
      }
      if (url === '/api/customers/1/remind') {
        reminderSent = '2026-11-26T12:00:00.000000+00:00'
        return new Response(JSON.stringify({ sent: true, reminder_sent: reminderSent }), { status: 200 })
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

    await detail.findAll('button').find((item) => item.text() === 'Verlängern')?.trigger('click')
    await wrapper.find('[aria-labelledby="renew-title"] button').trigger('click')
    await flushPromises()
    expect(detailLoads).toBe(2)
    expect(detail.text()).toContain('26.02.2027')

    await detail.findAll('button').find((item) => item.text() === 'Erinnern')?.trigger('click')
    await wrapper.find('[aria-labelledby="reminder-title"] button').trigger('click')
    await flushPromises()
    expect(detailLoads).toBe(3)
    expect(detail.text()).toContain('Erinnerungs-E-Mail an ada@example.com wurde versendet.')
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
