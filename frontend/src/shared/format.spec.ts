import { describe, expect, it } from 'vitest'
import { formatDateTime, isExpiringSoon, statusLabel } from './format'

const days = (n: number) => new Date(Date.now() + n * 24 * 60 * 60 * 1000).toISOString()

describe('formatDateTime', () => {
  it('renders an em dash for null', () => {
    expect(formatDateTime(null)).toBe('—')
  })

  it('formats an ISO date in a human form', () => {
    const out = formatDateTime('2026-06-15T12:00:00Z')

    expect(out).toMatch(/Jun/)
    expect(out).toMatch(/2026/)
  })
})

describe('isExpiringSoon', () => {
  it('is true for an open document due within three days', () => {
    expect(isExpiringSoon('pending', days(2))).toBe(true)
  })

  it('is false when the deadline is further out', () => {
    expect(isExpiringSoon('pending', days(5))).toBe(false)
  })

  it('is false once the deadline has passed', () => {
    expect(isExpiringSoon('partially_signed', days(-1))).toBe(false)
  })

  it('only applies to open statuses', () => {
    expect(isExpiringSoon('draft', days(1))).toBe(false)
    expect(isExpiringSoon('signed', days(1))).toBe(false)
  })

  it('is false without a deadline', () => {
    expect(isExpiringSoon('pending', null)).toBe(false)
  })
})

describe('statusLabel', () => {
  it('humanises the enum value', () => {
    expect(statusLabel('partially_signed')).toBe('Partially signed')
    expect(statusLabel('draft')).toBe('Draft')
  })
})
