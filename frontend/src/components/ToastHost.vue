<script setup lang="ts">
import { CheckCircle2, Info, X, XCircle } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { useToasts, type ToastVariant } from '@/shared/toast'

const { t } = useI18n()
const { toasts, dismiss } = useToasts()

const styles: Record<ToastVariant, string> = {
  success: 'border-green-200 bg-green-50 text-green-800',
  error: 'border-red-200 bg-red-50 text-red-800',
  info: 'border-slate-200 bg-white text-slate-800',
}

const icons = { success: CheckCircle2, error: XCircle, info: Info }
</script>

<template>
  <div class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4 sm:items-end">
    <TransitionGroup
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-2 opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-for="toastItem in toasts"
        :key="toastItem.id"
        class="pointer-events-auto flex w-full max-w-sm items-start gap-2 rounded-lg border px-4 py-3 text-sm shadow-sm"
        :class="styles[toastItem.variant]"
        :role="toastItem.variant === 'error' ? 'alert' : 'status'"
        aria-live="polite"
      >
        <component :is="icons[toastItem.variant]" class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span class="min-w-0 flex-1">{{ toastItem.message }}</span>
        <button
          class="shrink-0 rounded p-0.5 opacity-60 transition hover:opacity-100"
          :aria-label="t('toast.dismiss')"
          @click="dismiss(toastItem.id)"
        >
          <X class="size-4" aria-hidden="true" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
