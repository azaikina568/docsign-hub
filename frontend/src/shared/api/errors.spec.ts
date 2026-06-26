import { AxiosError } from 'axios'
import { describe, expect, it } from 'vitest'
import { normalizeError } from './errors'
import type { ApiErrorBody } from './types'

function axiosError(status: number, data: Partial<ApiErrorBody> = {}): AxiosError {
  return new AxiosError('request failed', 'ERR_BAD_RESPONSE', undefined, undefined, {
    status,
    data,
    statusText: '',
    headers: {},
    config: {} as never,
  })
}

describe('normalizeError', () => {
  it('hides server-side 5xx detail behind a neutral message', () => {
    const result = normalizeError(axiosError(500, { message: 'Server Error\n#0 /app/trace' }))

    expect(result.status).toBe(500)
    expect(result.message).toBe('Something went wrong on our side. Please try again in a moment.')
    expect(result.fields).toEqual({})
  })

  it('uses a fixed message for 401 and 429 regardless of body', () => {
    expect(normalizeError(axiosError(401, { message: 'Unauthenticated.' })).message).toBe(
      'Your session has expired. Please sign in again.',
    )
    expect(normalizeError(axiosError(429)).message).toBe('Too many requests. Please wait a moment and try again.')
  })

  it('surfaces the backend message for domain 4xx', () => {
    expect(normalizeError(axiosError(409, { message: 'Document is not in a draft state.' })).message).toBe(
      'Document is not in a draft state.',
    )
  })

  it('falls back to a code message when the body has none', () => {
    expect(normalizeError(axiosError(404)).message).toBe('We could not find what you were looking for.')
  })

  it('extracts the first message per field from a 422', () => {
    const result = normalizeError(
      axiosError(422, { message: 'The given data was invalid.', errors: { email: ['Taken.', 'Also bad.'] } }),
    )

    expect(result.status).toBe(422)
    expect(result.fields).toEqual({ email: 'Taken.' })
  })

  it('treats a response-less error as a network failure', () => {
    const result = normalizeError(new Error('boom'))

    expect(result.status).toBeNull()
    expect(result.message).toBe('Network error. Check your connection and try again.')
  })
})
