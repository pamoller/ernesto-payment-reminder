import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from './App.vue'

describe('App', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('lädt und zeigt bestehende Kunden', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
      const url = String(input)
      if (url === '/api/auth/session') {
        return new Response(JSON.stringify({
          authenticated: true,
          csrf_token: 'test-csrf',
          username: 'admin',
        }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }
      if (url === '/api/subscription-types') {
        return new Response(JSON.stringify({
          subscription_types: [
            { id: 1, name: 'Skool Test', duration: '3 MONTHS', duration_months: 3, reminder: 'Reminder Text 123' },
            { id: 2, name: 'Skool Halbjahres Abo', duration: '6 MONTHS', duration_months: 6, reminder: 'Reminder Text 123' },
            { id: 3, name: 'Skool Jahres Abo', duration: '12 MONTHS', duration_months: 12, reminder: 'Reminder Text 123' },
          ],
        }), { status: 200 })
      }
      return new Response(JSON.stringify({
        customers: [{
          id: 1,
          name: 'Ada Lovelace',
          email: 'ada@example.com',
          subscription_type_id: 2,
          duration_months: 6,
          start_date: '2026-01-01',
          expires_at: '2026-07-01',
          reminder_sent: null,
        }],
      }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    })

    const wrapper = mount(App)
    await flushPromises()

    expect(wrapper.text()).toContain('Ada Lovelace')
    expect(wrapper.text()).toContain('ada@example.com')
    expect(wrapper.text()).toContain('6 Monate')
  })

  it('zeigt Login-Fehler direkt auf der Loginseite', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
      if (String(input) === '/api/auth/session') {
        return new Response(JSON.stringify({
          authenticated: false,
          csrf_token: 'test-csrf',
          username: null,
        }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }

      return new Response(JSON.stringify({ error: 'Invalid credentials' }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' },
      })
    })

    const wrapper = mount(App)
    await flushPromises()
    await wrapper.find('input[name="username"]').setValue('admin')
    await wrapper.find('input[name="password"]').setValue('wrong-password')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toBe('Invalid credentials')
    expect(wrapper.find('input[name="password"]').exists()).toBe(true)
  })

  it('sendet einen manuellen Reminder und zeigt den neuen Versandzeitpunkt', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation(async (input, init) => {
      const url = String(input)
      if (url === '/api/auth/session') {
        return new Response(JSON.stringify({
          authenticated: true,
          csrf_token: 'test-csrf',
          username: 'admin',
        }), { status: 200 })
      }
      if (url === '/api/subscription-types') {
        return new Response(JSON.stringify({
          subscription_types: [
            { id: 3, name: 'Skool Jahres Abo', duration: '12 MONTHS', duration_months: 12, reminder: 'Reminder Text 123' },
          ],
        }), { status: 200 })
      }
      if (url === '/api/customers/1/remind') {
        expect(init?.method).toBe('POST')
        expect(new Headers(init?.headers).get('X-CSRF-Token')).toBe('test-csrf')
        return new Response(JSON.stringify({
          sent: true,
          reminder_sent: '2026-03-20T12:00:00.123456+00:00',
        }), { status: 200 })
      }
      return new Response(JSON.stringify({
        customers: [{
          id: 1,
          name: 'Ada Lovelace',
          email: 'ada@example.com',
          subscription_type_id: 3,
          duration_months: 12,
          start_date: '2026-03-01',
          expires_at: '2027-03-01',
          reminder_sent: null,
        }],
      }), { status: 200 })
    })

    const wrapper = mount(App)
    await flushPromises()
    const remindButton = wrapper.findAll('button').find((button) => button.text() === 'Remind')
    expect(remindButton).toBeDefined()
    if (!remindButton) throw new Error('Remind button is missing')
    await remindButton.trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/customers/1/remind', expect.any(Object))
    expect(wrapper.text()).toContain('Reminder-E-Mail an ada@example.com wurde versendet.')
    expect(wrapper.text()).toContain('2026-03-20T12:00:00.123456+00:00')
  })
})
