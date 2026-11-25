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
      return new Response(JSON.stringify({
        customers: [{
          id: 1,
          name: 'Ada Lovelace',
          email: 'ada@example.com',
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
})
