import { reactive } from 'vue'

export type ToastVariant = 'success' | 'error' | 'info'

export interface Toast {
  id: number
  variant: ToastVariant
  message: string
}

// Один список на всё приложение (модуль-синглтон): тосты должны переживать смену роутов, поэтому
// не привязаны к компоненту. ToastHost рендерит его поверх страниц.
const toasts = reactive<Toast[]>([])
let seq = 0

function push(variant: ToastVariant, message: string, ttl: number): void {
  const id = ++seq
  toasts.push({ id, variant, message })

  if (ttl > 0) {
    window.setTimeout(() => dismiss(id), ttl)
  }
}

export function dismiss(id: number): void {
  const index = toasts.findIndex((t) => t.id === id)

  if (index !== -1) {
    toasts.splice(index, 1)
  }
}

export const toast = {
  success: (message: string) => push('success', message, 5000),
  // Ошибки держим дольше — их нужно успеть прочитать.
  error: (message: string) => push('error', message, 8000),
  info: (message: string) => push('info', message, 5000),
}

export function useToasts() {
  return { toasts, dismiss }
}
