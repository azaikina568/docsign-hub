import { createPinia, setActivePinia } from 'pinia'
import { http, HttpResponse } from 'msw'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { server } from '../../tests/msw/server'
import { useAuthStore } from './auth'
import { useToasts } from '@/shared/toast'
import { getAccessToken, setTokens } from '@/shared/api/tokens'
import router from '@/router'
import type { TokenPair, User } from '@/shared/api/types'

const BASE = 'http://localhost:8080/api/v1'
const user: User = {
  id: 1,
  name: 'Demo',
  email: 'demo@docsign.test',
  email_verified_at: '2026-01-01T00:00:00Z',
  created_at: null,
}

function pair(access = 'access-1', refresh = 'refresh-1'): TokenPair {
  return {
    token_type: 'Bearer',
    access_token: access,
    access_expires_at: '2999-01-01T00:00:00Z',
    refresh_token: refresh,
    refresh_expires_at: '2999-01-01T00:00:00Z',
  }
}

const { toasts } = useToasts()

beforeEach(() => {
  setActivePinia(createPinia())
  toasts.splice(0)
})

afterEach(() => vi.restoreAllMocks())

describe('auth store', () => {
  it('stores tokens and profile after login', async () => {
    server.use(http.post(`${BASE}/auth/login`, () => HttpResponse.json({ user, tokens: pair() })))
    const auth = useAuthStore()

    await auth.login({ email: 'demo@docsign.test', password: 'secret' })

    expect(getAccessToken()).toBe('access-1')
    expect(auth.user).toEqual(user)
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.emailVerified).toBe(true)
  })

  it('registers the same way as login', async () => {
    server.use(http.post(`${BASE}/auth/register`, () => HttpResponse.json({ user, tokens: pair() })))
    const auth = useAuthStore()

    await auth.register({
      name: 'Demo',
      email: 'demo@docsign.test',
      password: 'secret12',
      password_confirmation: 'secret12',
    })

    expect(auth.isAuthenticated).toBe(true)
  })

  it('clears the session on logout even if the API call fails', async () => {
    setTokens(pair())
    server.use(http.post(`${BASE}/auth/logout`, () => new HttpResponse(null, { status: 500 })))
    const auth = useAuthStore()
    auth.user = user

    await auth.logout()

    expect(auth.user).toBeNull()
    expect(getAccessToken()).toBeNull()
  })

  it('restores the profile from a stored token on init', async () => {
    setTokens(pair())
    server.use(http.get(`${BASE}/me`, () => HttpResponse.json({ data: user })))
    const auth = useAuthStore()

    await auth.init()

    expect(auth.user).toEqual(user)
    expect(auth.initialized).toBe(true)
  })

  it('resets, toasts and redirects to login when the session finally expires', async () => {
    setTokens(pair('stale', 'bad'))
    const push = vi.spyOn(router, 'push').mockResolvedValue()
    server.use(
      http.get(`${BASE}/me`, () => new HttpResponse(null, { status: 401 })),
      http.post(`${BASE}/auth/refresh`, () => new HttpResponse(null, { status: 401 })),
    )
    const auth = useAuthStore()
    // init() регистрирует обработчик «сессия протухла» в axios-клиенте; первый /me словит 401.
    await auth.init()

    expect(auth.user).toBeNull()
    expect(getAccessToken()).toBeNull()
    expect(toasts.some((t) => t.message.includes('session has expired'))).toBe(true)
    expect(push).toHaveBeenCalledWith({ name: 'login' })
  })
})
