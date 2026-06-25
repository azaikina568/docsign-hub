<script setup lang="ts">
// Лёгкий каркас для публичных страниц (подписание/верификация): только бренд, без auth-навигации —
// сюда попадают и незалогиненные внешние участники.
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto max-w-2xl px-4 py-3">
        <span class="text-base font-semibold">DocSign Hub</span>
      </div>
    </header>

    <main class="mx-auto max-w-2xl px-4 py-8">
      <slot />
    </main>
  </div>
</template>
