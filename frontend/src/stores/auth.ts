import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import * as authApi from '@/shared/api/auth'
import type { LoginPayload, RegisterPayload } from '@/shared/api/auth'
import { setAuthExpiredHandler } from '@/shared/api/client'
import { toast } from '@/shared/toast'
import { clearTokens, getAccessToken, setTokens } from '@/shared/api/tokens'
import type { AuthResponse, User } from '@/shared/api/types'
import router from '@/router'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  // Флаг, чтобы восстановление сессии (init) отработало ровно один раз.
  const initialized = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const emailVerified = computed(() => user.value?.email_verified_at != null)

  function applyAuth(response: AuthResponse): void {
    setTokens(response.tokens)
    user.value = response.user
  }

  function reset(): void {
    clearTokens()
    user.value = null
  }

  async function register(payload: RegisterPayload): Promise<void> {
    applyAuth(await authApi.register(payload))
  }

  async function login(payload: LoginPayload): Promise<void> {
    applyAuth(await authApi.login(payload))
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } catch {
      // Сервер мог не ответить (5xx/сеть) — это не должно мешать локально завершить сессию и увести на login.
    } finally {
      reset()
    }
  }

  // Восстановление сессии при загрузке приложения: есть токен → тянем профиль. Невалидный токен
  // отработает interceptor (refresh/redirect). Гоняем один раз на старте.
  async function init(): Promise<void> {
    if (initialized.value) {
      return
    }

    setAuthExpiredHandler(() => {
      reset()
      toast.info('Your session has expired. Please sign in again.')
      void router.push({ name: 'login' })
    })

    if (getAccessToken()) {
      try {
        user.value = await authApi.me()
      } catch {
        reset()
      }
    }

    initialized.value = true
  }

  // Перечитать профиль (напр. после подтверждения email — обновить email_verified_at и убрать баннер).
  async function refresh(): Promise<void> {
    if (getAccessToken()) {
      user.value = await authApi.me()
    }
  }

  return { user, initialized, isAuthenticated, emailVerified, register, login, logout, init, refresh, reset }
})
