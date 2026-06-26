import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { server } from '../../../tests/msw/server'
import { setAuthExpiredHandler } from './client'
import { login, me } from './auth'
import { getAccessToken, getRefreshToken, setTokens } from './tokens'
import type { TokenPair } from './types'

const BASE = 'http://localhost:8080/api/v1'
const user = { id: 1, name: 'Demo', email: 'demo@docsign.test', email_verified_at: null, created_at: null }

function tokenPair(access: string, refresh: string): TokenPair {
  return {
    token_type: 'Bearer',
    access_token: access,
    access_expires_at: '2999-01-01T00:00:00Z',
    refresh_token: refresh,
    refresh_expires_at: '2999-01-01T00:00:00Z',
  }
}

beforeEach(() => {
  setTokens(tokenPair('stale-access', 'good-refresh'))
  setAuthExpiredHandler(() => {})
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('api client transparent refresh', () => {
  it('refreshes the access token on 401 and retries the original request', async () => {
    server.use(
      http.get(`${BASE}/me`, ({ request }) =>
        request.headers.get('Authorization') === 'Bearer fresh-access'
          ? HttpResponse.json({ data: user })
          : new HttpResponse(null, { status: 401 }),
      ),
      http.post(`${BASE}/auth/refresh`, () =>
        HttpResponse.json({ user, tokens: tokenPair('fresh-access', 'next-refresh') }),
      ),
    )

    const result = await me()

    expect(result.email).toBe('demo@docsign.test')
    expect(getAccessToken()).toBe('fresh-access')
    expect(getRefreshToken()).toBe('next-refresh')
  })

  it('refreshes only once for a burst of parallel 401s (single-flight)', async () => {
    let refreshCalls = 0
    server.use(
      http.get(`${BASE}/me`, ({ request }) =>
        request.headers.get('Authorization') === 'Bearer fresh-access'
          ? HttpResponse.json({ data: user })
          : new HttpResponse(null, { status: 401 }),
      ),
      http.post(`${BASE}/auth/refresh`, () => {
        refreshCalls += 1
        return HttpResponse.json({ user, tokens: tokenPair('fresh-access', 'next-refresh') })
      }),
    )

    await Promise.all([me(), me(), me()])

    expect(refreshCalls).toBe(1)
  })

  it('clears the session and notifies when refresh fails', async () => {
    const onExpired = vi.fn()
    setAuthExpiredHandler(onExpired)
    server.use(
      http.get(`${BASE}/me`, () => new HttpResponse(null, { status: 401 })),
      http.post(`${BASE}/auth/refresh`, () => new HttpResponse(null, { status: 401 })),
    )

    await expect(me()).rejects.toThrow()
    expect(onExpired).toHaveBeenCalledOnce()
    expect(getAccessToken()).toBeNull()
  })

  it('does not attempt a refresh on a failed login (auth flow)', async () => {
    let refreshCalls = 0
    server.use(
      http.post(`${BASE}/auth/login`, () => HttpResponse.json({ message: 'Invalid credentials.' }, { status: 422 })),
      http.post(`${BASE}/auth/refresh`, () => {
        refreshCalls += 1
        return new HttpResponse(null, { status: 200 })
      }),
    )

    await expect(login({ email: 'a@b.com', password: 'nope' })).rejects.toThrow()
    expect(refreshCalls).toBe(0)
  })
})
