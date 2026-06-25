import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import type { AuthResponse } from './types'
import { clearTokens, getAccessToken, getRefreshToken, setTokens } from './tokens'

const baseURL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1'

// Запас по таймауту: в dev на Windows запросы через bind-mount бывают медленными; в проде ответы быстрые.
export const api = axios.create({ baseURL, timeout: 15000 })

// Помечаем запрос, чтобы повторить его максимум один раз после refresh (без бесконечной петли на 401).
interface RetriableConfig extends InternalAxiosRequestConfig {
  _retried?: boolean
}

// Колбэк «сессия окончательно протухла» — регистрирует auth-стор, чтобы клиент не зависел от роутера/стора.
let onAuthExpired: (() => void) | null = null

export function setAuthExpiredHandler(handler: () => void): void {
  onAuthExpired = handler
}

// Эндпоинты, на 401 которых refresh не делаем (сам логин/refresh — это не «протухший access»).
function isAuthFlow(url?: string): boolean {
  return !!url && ['/auth/login', '/auth/register', '/auth/refresh'].some((path) => url.includes(path))
}

api.interceptors.request.use((config) => {
  const token = getAccessToken()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

// Single-flight refresh: пачка параллельных 401 должна вызвать ОДИН refresh, остальные ждут его результата.
let refreshing: Promise<string | null> | null = null

async function refreshAccess(): Promise<string | null> {
  const refreshToken = getRefreshToken()

  if (!refreshToken) {
    return null
  }

  refreshing ??= axios
    .create({ baseURL })
    .post<AuthResponse>('/auth/refresh', null, { headers: { Authorization: `Bearer ${refreshToken}` } })
    .then((response) => {
      setTokens(response.data.tokens)

      return response.data.tokens.access_token
    })
    .catch(() => {
      // Refresh не прошёл (украден/протух/семья отозвана) — сессия мертва.
      clearTokens()

      return null
    })
    .finally(() => {
      refreshing = null
    })

  return refreshing
}

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const original = error.config as RetriableConfig | undefined

    if (error.response?.status === 401 && original && !original._retried && !isAuthFlow(original.url)) {
      original._retried = true

      const access = await refreshAccess()

      if (access) {
        original.headers.Authorization = `Bearer ${access}`

        return api(original)
      }

      onAuthExpired?.()
    }

    return Promise.reject(error)
  },
)
