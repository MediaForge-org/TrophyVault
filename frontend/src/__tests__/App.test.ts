import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from '@/App.vue'

function stubFetch(impl: () => Promise<Response>) {
  vi.stubGlobal('fetch', vi.fn(impl))
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('App', () => {
  it('mounts and renders the foundation page', () => {
    stubFetch(() => new Promise(() => undefined))
    const wrapper = mount(App)

    expect(wrapper.find('h1').text()).toBe('TrophyVault')
    expect(wrapper.get('[data-testid="status"]').text()).toContain('Checking backend')
  })

  it('shows the backend health once loaded', async () => {
    stubFetch(() =>
      Promise.resolve(
        new Response(JSON.stringify({ status: 'ok', components: { database: 'ok', redis: 'ok' } })),
      ),
    )
    const wrapper = mount(App)
    await flushPromises()

    expect(wrapper.get('[data-testid="status"]').text()).toBe('Backend: ok')
    expect(wrapper.text()).toContain('database: ok')
    expect(wrapper.text()).toContain('redis: ok')
  })

  it('shows an error state when the backend is unreachable', async () => {
    stubFetch(() => Promise.reject(new Error('network down')))
    const wrapper = mount(App)
    await flushPromises()

    expect(wrapper.get('[data-testid="status"]').text()).toBe('Backend unreachable')
  })
})
