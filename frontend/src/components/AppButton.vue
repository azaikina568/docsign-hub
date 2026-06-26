<script setup lang="ts">
withDefaults(
  defineProps<{
    type?: 'button' | 'submit'
    variant?: 'primary' | 'ghost'
    loading?: boolean
    disabled?: boolean
  }>(),
  { type: 'button', variant: 'primary' },
)
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60"
    :class="variant === 'primary' ? 'bg-slate-900 text-white hover:bg-slate-800' : 'text-slate-700 hover:bg-slate-100'"
  >
    <span
      v-if="loading"
      class="size-4 animate-spin rounded-full border-2 border-current border-t-transparent"
      aria-hidden="true"
    />
    <slot />
  </button>
</template>
