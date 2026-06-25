import type { TokenPair } from './types'

// Токены храним в localStorage, чтобы сессия переживала перезагрузку вкладки. Один модуль-владелец
// (и axios-клиент, и Pinia-стор читают отсюда), без дублирования источника истины.

const ACCESS_KEY = 'docsign.access_token'
const REFRESH_KEY = 'docsign.refresh_token'

export function getAccessToken(): string | null {
  return localStorage.getItem(ACCESS_KEY)
}

export function getRefreshToken(): string | null {
  return localStorage.getItem(REFRESH_KEY)
}

export function setTokens(tokens: TokenPair): void {
  localStorage.setItem(ACCESS_KEY, tokens.access_token)
  localStorage.setItem(REFRESH_KEY, tokens.refresh_token)
}

export function clearTokens(): void {
  localStorage.removeItem(ACCESS_KEY)
  localStorage.removeItem(REFRESH_KEY)
}
