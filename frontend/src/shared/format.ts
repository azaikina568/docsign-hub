import type { DocumentStatus } from './api/documents'

const dateTime = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'short' })
const dateOnly = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' })

export function formatDateTime(iso: string | null): string {
  return iso ? dateTime.format(new Date(iso)) : '—'
}

export function formatDate(iso: string | null): string {
  return iso ? dateOnly.format(new Date(iso)) : '—'
}

// Документ «истекает скоро» — в работе и до дедлайна осталось меньше трёх дней.
export function isExpiringSoon(status: DocumentStatus, expiresAt: string | null): boolean {
  if (!expiresAt || (status !== 'pending' && status !== 'partially_signed')) {
    return false
  }

  const msLeft = new Date(expiresAt).getTime() - Date.now()

  return msLeft > 0 && msLeft < 3 * 24 * 60 * 60 * 1000
}

export function statusLabel(status: DocumentStatus): string {
  return status.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
}
