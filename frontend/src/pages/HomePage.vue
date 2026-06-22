<template>
  <main class="min-h-screen bg-zinc-950 text-zinc-100">
    <section class="mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-6 py-10">
      <div class="mb-8">
        <p class="text-sm font-medium uppercase tracking-wide text-emerald-300">DocSign Hub</p>
        <h1 class="mt-3 text-4xl font-semibold tracking-normal text-white sm:text-5xl">
          Bootstrap workspace
        </h1>
      </div>

      <div class="grid gap-4 md:grid-cols-[1.2fr_0.8fr]">
        <section class="rounded-lg border border-zinc-800 bg-zinc-900/80 p-6">
          <h2 class="text-lg font-semibold text-white">API status</h2>

          <div v-if="isPending" class="mt-6 text-sm text-zinc-300">Checking API...</div>

          <div v-else-if="isError" class="mt-6 rounded-md border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-100">
            API is not available.
          </div>

          <div v-else class="mt-6 rounded-md border border-emerald-500/30 bg-emerald-500/10 p-4">
            <div class="flex items-center gap-3">
              <span class="h-2.5 w-2.5 rounded-full bg-emerald-300"></span>
              <span class="text-sm font-medium text-emerald-100">{{ health?.service }} is online</span>
            </div>
            <p class="mt-3 text-sm text-zinc-300">{{ health?.timestamp }}</p>
          </div>
        </section>

        <aside class="rounded-lg border border-zinc-800 bg-zinc-900/80 p-6">
          <h2 class="text-lg font-semibold text-white">Runtime</h2>
          <dl class="mt-5 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
              <dt class="text-zinc-400">Frontend</dt>
              <dd class="font-medium text-zinc-100">Vue 3</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-zinc-400">Backend</dt>
              <dd class="font-medium text-zinc-100">Laravel 12</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-zinc-400">API</dt>
              <dd class="font-medium text-zinc-100">/api/v1</dd>
            </div>
          </dl>
        </aside>
      </div>
    </section>
  </main>
</template>

<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { getHealth } from '@/shared/api/health'

const { data: health, isError, isPending } = useQuery({
  queryKey: ['health'],
  queryFn: getHealth,
})
</script>
