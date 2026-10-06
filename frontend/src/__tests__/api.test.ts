import { describe, expect, it } from 'vitest'
import { fetchHealth } from '@/api'

function fakeFetch(body: unknown, status = 200): typeof fetch {
  return () => Promise.resolve(new Response(JSON.stringify(body), { status }))
}

describe('fetchHealth', () => {
  it('returns a valid report', async () => {
    const report = { status: 'ok', components: { database: 'ok', redis: 'ok' } }
    await expect(fetchHealth(fakeFetch(report))).resolves.toEqual(report)
  })

  it('accepts the degraded report that comes with a 503', async () => {
    const report = { status: 'degraded', components: { database: 'fail', redis: 'ok' } }
    await expect(fetchHealth(fakeFetch(report, 503))).resolves.toEqual(report)
  })

  it('rejects an unexpected payload', async () => {
    await expect(fetchHealth(fakeFetch({ hello: 'world' }))).rejects.toThrow('Unexpected health response')
  })
})
