import { AxiosError } from 'axios'
import type { ApiErrorBody } from './types'
import { t } from '@/shared/i18n'

export interface NormalizedError {
  message: string
  // HTTP-статус ответа (null — сетевая ошибка/нет ответа). Позволяет ветвить UX по коду.
  status: number | null
  // Ошибки валидации по полям (из 422), первое сообщение на поле.
  fields: Record<string, string>
}

// Фолбэк-ключи по кодам, когда сообщение бэка либо техническое, либо неинформативно для пользователя.
// Текст резолвится из словаря в момент вызова — под активную локаль.
const codeKeys: Record<number, string> = {
  401: 'errors.session',
  403: 'errors.forbidden',
  404: 'errors.notFound',
  409: 'errors.conflict',
  410: 'errors.gone',
  429: 'errors.tooMany',
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

  return { message: t('errors.network'), status: null, fields: {} }
}

function messageFor(status: number, body: ApiErrorBody): string {
  // 5xx нельзя показывать как есть (может быть «Server Error»/трейс) — даём нейтральный текст.
  if (status >= 500) {
    return t('errors.server')
  }

  // На 401/429 текст сервера не несёт пользе пользователю — всегда свой; на доменных 4xx — сообщение бэка.
  if (status === 401 || status === 429) {
    return t(codeKeys[status])
  }

  return body.message?.trim() || (codeKeys[status] ? t(codeKeys[status]) : t('errors.generic'))
}
