<template>
  <div class="flex min-h-screen flex-col items-center justify-center gap-3 bg-slate-50 px-4 text-center">
    <p class="text-5xl font-semibold text-slate-900">404</p>
    <p class="text-sm text-slate-500">This page does not exist.</p>
    <RouterLink :to="{ name: 'dashboard' }" class="text-sm font-medium text-slate-900 hover:underline">
      Back to dashboard
    </RouterLink>
  </div>
</template>
