import { AxiosError } from 'axios'
import type { ApiErrorBody } from './types'

export interface NormalizedError {
  message: string
  // HTTP-статус ответа (null — сетевая ошибка/нет ответа). Позволяет ветвить UX по коду.
  status: number | null
  // Ошибки валидации по полям (из 422), первое сообщение на поле.
  fields: Record<string, string>
}

// Фолбэк-тексты по кодам, когда сообщение бэка либо техническое, либо неинформативно для пользователя.
const codeMessages: Record<number, string> = {
  401: 'Your session has expired. Please sign in again.',
  403: 'You do not have permission to do that.',
  404: 'We could not find what you were looking for.',
  409: 'This action conflicts with the document’s current state. Refresh and try again.',
  410: 'This link is no longer valid — it may have expired.',
  429: 'Too many requests. Please wait a moment and try again.',
}

// Приводит любую ошибку axios к удобному виду: текст по коду + ошибки по полям (для форм).
export function normalizeError(error: unknown): NormalizedError {
  if (error instanceof AxiosError && error.response) {
    const status = error.response.status
    const body = (error.response.data ?? {}) as ApiErrorBody
    const fields: Record<string, string> = {}

    for (const [field, messages] of Object.entries(body.errors ?? {})) {
      if (messages.length > 0) {
        fields[field] = messages[0]
      }
    }

    return { message: messageFor(status, body), status, fields }
  }

  return { message: 'Network error. Check your connection and try again.', status: null, fields: {} }
}

function messageFor(status: number, body: ApiErrorBody): string {
  // 5xx нельзя показывать как есть (может быть «Server Error»/трейс) — даём нейтральный текст.
  if (status >= 500) {
    return 'Something went wrong on our side. Please try again in a moment.'
  }

  // На 401/429 текст сервера не несёт пользе пользователю — всегда свой; на доменных 4xx — сообщение бэка.
  if (status === 401 || status === 429) {
    return codeMessages[status]
  }

  return body.message?.trim() || codeMessages[status] || 'Request failed. Please try again.'
}
