<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppButton from '@/components/AppButton.vue'

const auth = useAuthStore()
const router = useRouter()

async function onLogout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
        <RouterLink :to="{ name: 'dashboard' }" class="text-base font-semibold">DocSign Hub</RouterLink>
        <div class="flex items-center gap-3">
          <span class="hidden text-sm text-slate-500 sm:inline">{{ auth.user?.email }}</span>
          <AppButton variant="ghost" @click="onLogout">Sign out</AppButton>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-6">
      <div
        v-if="!auth.emailVerified"
        class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
      >
        Please verify your email to start creating documents. Check your inbox for the confirmation link.
      </div>

      <slot />
    </main>
  </div>
</template>
