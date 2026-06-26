<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { resendVerification, verifyEmail } from '@/shared/api/auth'
import { useAuthStore } from '@/stores/auth'
import { normalizeError } from '@/shared/api/errors'
import PublicLayout from '@/layouts/PublicLayout.vue'
import AppButton from '@/components/AppButton.vue'

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()

const state = ref<'loading' | 'success' | 'error'>('loading')
const message = ref('')
const resent = ref(false)
const resending = ref(false)

onMounted(async () => {
  const id = route.params.id as string
  const hash = route.params.hash as string
  // Берём сырой query (signature/expires) как есть из адреса — пересборка могла бы испортить подпись.
  const signedQuery = window.location.search.replace(/^\?/, '')

  try {
    message.value = (await verifyEmail(id, hash, signedQuery)).message
    state.value = 'success'
    // Если это наша же сессия — обновим профиль, чтобы пропал баннер «подтвердите email».
    if (auth.isAuthenticated) {
      try {
        await auth.refresh()
      } catch {
        // профиль обновится при следующем заходе — не критично
      }
    }
  } catch (error) {
    state.value = 'error'
    message.value = normalizeError(error).message
  }
})

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
  <PublicLayout>
    <div class="rounded-xl border border-slate-200 bg-white p-6 text-center">
      <p v-if="state === 'loading'" class="text-sm text-slate-400">{{ t('verifyEmail.verifying') }}</p>

      <template v-else-if="state === 'success'">
        <p class="text-base font-medium text-green-600">{{ message }}</p>
        <RouterLink
          :to="auth.isAuthenticated ? { name: 'dashboard' } : { name: 'login' }"
          class="mt-4 inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
        >
          {{ auth.isAuthenticated ? t('verifyEmail.goToDashboard') : t('verifyEmail.signIn') }}
        </RouterLink>
      </template>

      <template v-else>
        <p class="text-base font-medium text-slate-900">{{ t('verifyEmail.invalid') }}</p>
        <p class="mt-1 text-sm text-slate-500">{{ message }}</p>

        <div v-if="auth.isAuthenticated && !auth.emailVerified" class="mt-4">
          <p v-if="resent" class="text-sm text-green-600">{{ t('verifyEmail.resent') }}</p>
          <AppButton v-else :loading="resending" @click="onResend">{{ t('verifyEmail.resend') }}</AppButton>
        </div>
        <RouterLink v-else :to="{ name: 'login' }" class="mt-4 inline-flex text-sm text-slate-600 hover:underline">
          {{ t('verifyEmail.backToSignIn') }}
        </RouterLink>
      </template>
    </div>
  </PublicLayout>
</template>
