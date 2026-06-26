<script setup lang="ts">
// Лёгкий каркас для публичных страниц (подписание/верификация): только бренд, без auth-навигации —
// сюда попадают и незалогиненные внешние участники.
import { useI18n } from 'vue-i18n'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'

const { t } = useI18n()
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-2xl items-center justify-between px-4 py-3">
        <span class="text-base font-semibold">{{ t('common.appName') }}</span>
        <LanguageSwitcher />
      </div>
    </header>

    <main class="mx-auto max-w-2xl px-4 py-8">
      <slot />
    </main>
  </div>
</template>
