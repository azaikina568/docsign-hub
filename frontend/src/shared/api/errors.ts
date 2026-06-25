import { AxiosError } from 'axios'
import type { ApiErrorBody } from './types'

export interface NormalizedError {
  message: string
  // Ошибки валидации по полям (из 422), первое сообщение на поле.
  fields: Record<string, string>
}

// Приводит любую ошибку axios к удобному виду для форм: общий текст + ошибки по полям.
export function normalizeError(error: unknown): NormalizedError {
  if (error instanceof AxiosError && error.response) {
    const body = error.response.data as ApiErrorBody
    const fields: Record<string, string> = {}

    for (const [field, messages] of Object.entries(body.errors ?? {})) {
      if (messages.length > 0) {
        fields[field] = messages[0]
      }
    }

    return { message: body.message ?? 'Request failed.', fields }
  }

  return { message: 'Network error. Check your connection and try again.', fields: {} }
}
