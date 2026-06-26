<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { setLocale, SUPPORTED_LOCALES, type AppLocale } from '@/shared/i18n'

const { t, locale } = useI18n()

function onChange(event: Event): void {
  setLocale((event.target as HTMLSelectElement).value as AppLocale)
}
</script>

<template>
  <select
    :value="locale"
    :aria-label="t('language.label')"
    class="rounded-md border border-slate-200 bg-white px-2 py-1 text-sm text-slate-600 outline-none transition hover:bg-slate-50 focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
    @change="onChange"
  >
    <option v-for="code in SUPPORTED_LOCALES" :key="code" :value="code">{{ t(`language.${code}`) }}</option>
  </select>
</template>
