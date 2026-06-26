import { afterEach, describe, expect, it } from 'vitest'
import { currentIntlLocale, i18n, setLocale, SUPPORTED_LOCALES } from './index'
import en from './locales/en'
import ru from './locales/ru'
import { formatDate, statusLabel } from '@/shared/format'

const t = i18n.global.t

// Тесты переключают локаль — возвращаем английскую, чтобы не влиять на остальные spec'и (setup ставит en).
afterEach(() => setLocale('en'))

// Собирает плоский набор ключей словаря (a.b.c) — для сверки покрытия между языками.
function flatKeys(obj: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(obj).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key

    return value && typeof value === 'object' ? flatKeys(value as Record<string, unknown>, path) : [path]
  })
}

describe('locale dictionaries', () => {
  it('expose exactly en and ru', () => {
    expect(SUPPORTED_LOCALES).toEqual(['en', 'ru'])
  })

  it('have identical key sets across languages', () => {
    expect(flatKeys(ru).sort()).toEqual(flatKeys(en).sort())
  })
})

describe('setLocale', () => {
  it('switches the active locale, persists it and tags <html lang>', () => {
    setLocale('ru')

    expect(i18n.global.locale.value).toBe('ru')
    expect(localStorage.getItem('docsign.locale')).toBe('ru')
    expect(document.documentElement.lang).toBe('ru')
    expect(currentIntlLocale()).toBe('ru-RU')
  })
})

describe('pluralization', () => {
  it('picks English singular/plural by count', () => {
    expect(t('documentsList.partyCount', 0)).toBe('0 parties')
    expect(t('documentsList.partyCount', 1)).toBe('1 party')
    expect(t('documentsList.partyCount', 3)).toBe('3 parties')
  })

  it('applies the three Russian plural forms', () => {
    setLocale('ru')

    expect(t('documentsList.partyCount', 1)).toBe('1 участник')
    expect(t('documentsList.partyCount', 2)).toBe('2 участника')
    expect(t('documentsList.partyCount', 5)).toBe('5 участников')
    expect(t('documentsList.partyCount', 21)).toBe('21 участник')
  })
})

describe('locale-aware output', () => {
  it('translates status labels for the active locale', () => {
    expect(statusLabel('partially_signed')).toBe('Partially signed')

    setLocale('ru')

    expect(statusLabel('partially_signed')).toBe('Частично подписан')
  })

  it('formats dates in the active locale', () => {
    expect(formatDate('2026-06-15T12:00:00Z')).toMatch(/Jun/)

    setLocale('ru')

    // ru-RU medium-формат добавляет суффикс года «г.» — признак, что локаль действительно сменилась.
    expect(formatDate('2026-06-15T12:00:00Z')).toMatch(/г\./)
  })
})
