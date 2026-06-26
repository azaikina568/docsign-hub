<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { normalizeError } from '@/shared/api/errors'
import { loginSchema, validate } from '@/shared/validation'
import FormField from '@/components/FormField.vue'
import AppButton from '@/components/AppButton.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = reactive({ email: '', password: '' })
const errors = ref<Record<string, string>>({})
const generalError = ref('')
const loading = ref(false)

async function onSubmit(): Promise<void> {
  generalError.value = ''

  const clientErrors = validate(loginSchema, form)
  if (clientErrors) {
    errors.value = clientErrors
    return
  }

  loading.value = true
  errors.value = {}

  try {
    await auth.login({ ...form })
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : { name: 'dashboard' }
    await router.push(redirect as never)
  } catch (error) {
    const normalized = normalizeError(error)
    errors.value = normalized.fields
    generalError.value = Object.keys(normalized.fields).length ? '' : normalized.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="relative flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="absolute right-4 top-4">
      <LanguageSwitcher />
    </div>
    <div class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h1 class="text-lg font-semibold text-slate-900">{{ t('login.title') }}</h1>
      <p class="mt-1 text-sm text-slate-500">{{ t('login.subtitle') }}</p>

      <form class="mt-5 flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
        <p v-if="generalError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ generalError }}</p>

        <FormField
          id="email"
          v-model="form.email"
          :label="t('login.email')"
          type="email"
          autocomplete="email"
          :error="errors.email"
        />
        <FormField
          id="password"
          v-model="form.password"
          :label="t('login.password')"
          type="password"
          autocomplete="current-password"
          :error="errors.password"
        />

        <AppButton type="submit" :loading="loading">{{ t('login.title') }}</AppButton>
      </form>

      <p class="mt-4 text-center text-sm text-slate-500">
        {{ t('login.noAccount') }}
        <RouterLink :to="{ name: 'register' }" class="font-medium text-slate-900 hover:underline">{{
          t('login.createOne')
        }}</RouterLink>
      </p>
    </div>
  </div>
</template>
