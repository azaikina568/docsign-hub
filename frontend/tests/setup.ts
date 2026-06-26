import { afterAll, afterEach, beforeAll, beforeEach } from 'vitest'
import { server } from './msw/server'

// Node 24 заводит экспериментальный глобальный localStorage (за флагом --localstorage-file) и перебивает
// localStorage тестовой DOM-среды. Ставим детерминированный in-memory Storage — на нём работает tokens.ts.
function createStorage(): Storage {
  const store = new Map<string, string>()

  return {
    get length() {
      return store.size
    },
    clear: () => store.clear(),
    getItem: (key: string) => (store.has(key) ? store.get(key)! : null),
    key: (index: number) => [...store.keys()][index] ?? null,
    removeItem: (key: string) => void store.delete(key),
    setItem: (key: string, value: string) => void store.set(key, String(value)),
  } as Storage
}

Object.defineProperty(globalThis, 'localStorage', { value: createStorage(), configurable: true, writable: true })

// onUnhandledRequest: 'error' — любой незамоканный запрос валит тест, чтобы не было «тихих» сетевых вызовов.
beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

// Каждый тест стартует с чистым хранилищем токенов: клиент и стор читают сессию из localStorage.
beforeEach(() => localStorage.clear())
