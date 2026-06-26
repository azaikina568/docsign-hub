import type { DocumentStatus } from './api/documents'
import { currentIntlLocale, t } from './i18n'

// Форматтеры кешируем по Intl-локали: их создание небесплатно, а локалей всего две.
const dateTimeFormatters = new Map<string, Intl.DateTimeFormat>()
const dateFormatters = new Map<string, Intl.DateTimeFormat>()

function formatter(cache: Map<string, Intl.DateTimeFormat>, options: Intl.DateTimeFormatOptions): Intl.DateTimeFormat {
  // Чтение активной локали в шаблоне делает форматирование реактивным к переключению языка.
  const locale = currentIntlLocale()
  let instance = cache.get(locale)

  if (!instance) {
    instance = new Intl.DateTimeFormat(locale, options)
    cache.set(locale, instance)
  }

  return instance
}

export function formatDateTime(iso: string | null): string {
  return iso ? formatter(dateTimeFormatters, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso)) : '—'
}

export function formatDate(iso: string | null): string {
  return iso ? formatter(dateFormatters, { dateStyle: 'medium' }).format(new Date(iso)) : '—'
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
  return t(`document.status.${status}`)
}
