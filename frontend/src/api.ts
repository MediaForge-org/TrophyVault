export type ComponentState = 'ok' | 'fail'

export interface HealthResponse {
  status: 'ok' | 'degraded'
  components: Record<string, ComponentState>
}

function isHealthResponse(value: unknown): value is HealthResponse {
  if (typeof value !== 'object' || value === null) return false
  const candidate = value as Record<string, unknown>
  return (
    (candidate.status === 'ok' || candidate.status === 'degraded') &&
    typeof candidate.components === 'object' &&
    candidate.components !== null
  )
}

/** Fetches the backend health report. 503 responses carry a valid report too. */
export async function fetchHealth(fetchImpl: typeof fetch = fetch): Promise<HealthResponse> {
  const response = await fetchImpl('/api/health', { headers: { Accept: 'application/json' } })
  const body: unknown = await response.json()
  if (!isHealthResponse(body)) {
    throw new Error('Unexpected health response')
  }
  return body
}
