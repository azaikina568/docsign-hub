import { createI18n } from 'vue-i18n'
import en from './locales/en'
import ru from './locales/ru'

export type AppLocale = 'en' | 'ru'

export const SUPPORTED_LOCALES: AppLocale[] = ['en', 'ru']

const STORAGE_KEY = 'docsign.locale'

// BCP-47-тег для Intl (даты/числа): русский формат отличается от английского.
const intlTags: Record<AppLocale, string> = { en: 'en-GB', ru: 'ru-RU' }

function isSupported(value: string | null): value is AppLocale {
  return value === 'en' || value === 'ru'
}

// Выбор языка при старте: сохранённый ранее → язык браузера → английский по умолчанию.
// Браузерные глобалы читаем с guard'ами: модуль импортируется и в тестовой среде, где они
// могут быть ещё не готовы на момент загрузки (полифилл localStorage ставится позже).
function detectLocale(): AppLocale {
  const saved = typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null
  if (isSupported(saved)) {
    return saved
  }

  const language = typeof navigator !== 'undefined' ? navigator.language : ''

  return language.toLowerCase().startsWith('ru') ? 'ru' : 'en'
}

// Русская плюрализация: 0 / «один» (1, 21…) / «несколько» (2–4) / «много» (5–20, 0). Под 4 формы в словаре.
function russianPlural(choice: number, choicesLength: number): number {
  if (choice === 0) {
    return 0
  }

  const teen = choice > 10 && choice < 20
  const endsWithOne = choice % 10 === 1

  if (!teen && endsWithOne) {
    return 1
  }
  if (!teen && choice % 10 >= 2 && choice % 10 <= 4) {
    return 2
  }

  return choicesLength < 4 ? 2 : 3
}

export const i18n = createI18n<false>({
  legacy: false,
  locale: detectLocale(),
  fallbackLocale: 'en',
  messages: { en, ru },
  pluralRules: { ru: russianPlural },
})

// Глобальный t для не-компонентных модулей (errors/validation/format): читает активную локаль из инстанса.
export const t = i18n.global.t

function applyHtmlLang(locale: AppLocale): void {
  if (typeof document !== 'undefined') {
    document.documentElement.lang = locale
  }
}

export function currentIntlLocale(): string {
  return intlTags[i18n.global.locale.value as AppLocale]
}

export function setLocale(locale: AppLocale): void {
  i18n.global.locale.value = locale
  localStorage.setItem(STORAGE_KEY, locale)
  applyHtmlLang(locale)
}

applyHtmlLang(i18n.global.locale.value as AppLocale)
