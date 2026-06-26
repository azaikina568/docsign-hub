import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { dismiss, toast, useToasts } from './toast'

const { toasts } = useToasts()

beforeEach(() => {
  vi.useFakeTimers()
  toasts.splice(0)
})

afterEach(() => {
  vi.useRealTimers()
})

describe('toast', () => {
  it('adds a toast with the chosen variant', () => {
    toast.success('Saved.')

    expect(toasts).toHaveLength(1)
    expect(toasts[0]).toMatchObject({ variant: 'success', message: 'Saved.' })
  })

  it('auto-dismisses success after 5s and error after 8s', () => {
    toast.success('ok')
    toast.error('boom')

    vi.advanceTimersByTime(5000)
    expect(toasts.map((t) => t.message)).toEqual(['boom'])

    vi.advanceTimersByTime(3000)
    expect(toasts).toHaveLength(0)
  })

  it('keeps unique ids so dismiss removes the right toast', () => {
    toast.info('first')
    toast.info('second')
    const firstId = toasts[0].id

    dismiss(firstId)

    expect(toasts.map((t) => t.message)).toEqual(['second'])
  })
})
