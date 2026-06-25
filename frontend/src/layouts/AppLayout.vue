<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { resendVerification } from '@/shared/api/auth'
import { useAuthStore } from '@/stores/auth'
import AppButton from '@/components/AppButton.vue'

const auth = useAuthStore()
const router = useRouter()

const resent = ref(false)
const resending = ref(false)

async function onLogout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}

async function onResend(): Promise<void> {
  resending.value = true
  try {
    await resendVerification()
    resent.value = true
  } finally {
    resending.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
        <RouterLink :to="{ name: 'dashboard' }" class="text-base font-semibold">DocSign Hub</RouterLink>
        <nav class="flex items-center gap-1 sm:gap-3">
          <RouterLink
            :to="{ name: 'dashboard' }"
            class="rounded-md px-2 py-1 text-sm text-slate-600 hover:bg-slate-100"
            active-class="text-slate-900"
          >
            Documents
          </RouterLink>
          <RouterLink
            :to="{ name: 'signing-requests' }"
            class="rounded-md px-2 py-1 text-sm text-slate-600 hover:bg-slate-100"
            active-class="text-slate-900"
          >
            To sign
          </RouterLink>
          <span class="mx-1 hidden text-sm text-slate-400 sm:inline">{{ auth.user?.email }}</span>
          <AppButton variant="ghost" @click="onLogout">Sign out</AppButton>
        </nav>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-6">
      <div
        v-if="!auth.emailVerified"
        class="mb-5 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
      >
        <span>Verify your email to start creating documents — check your inbox for the link.</span>
        <span v-if="resent" class="font-medium text-amber-900">Sent — check your inbox.</span>
        <button
          v-else
          class="font-medium underline disabled:opacity-50"
          :disabled="resending"
          @click="onResend"
        >
          Resend link
        </button>
      </div>

      <slot />
    </main>
  </div>
</template>
