<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { normalizeError } from '@/shared/api/errors'
import { passwordRules, registerSchema, validate } from '@/shared/validation'
import FormField from '@/components/FormField.vue'
import AppButton from '@/components/AppButton.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const errors = ref<Record<string, string>>({})
const generalError = ref('')
const loading = ref(false)

async function onSubmit(): Promise<void> {
  generalError.value = ''

  // Клиентская проверка до запроса; серверная 422 остаётся источником истины.
  const clientErrors = validate(registerSchema, form)
  if (clientErrors) {
    errors.value = clientErrors
    return
  }

  loading.value = true
  errors.value = {}

  try {
    await auth.register({ ...form })
    await router.push({ name: 'dashboard' })
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
  <div class="relative flex min-h-screen items-center justify-center bg-slate-50 px-4 py-8">
    <div class="absolute right-4 top-4">
      <LanguageSwitcher />
    </div>
    <div class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h1 class="text-lg font-semibold text-slate-900">{{ t('register.title') }}</h1>
      <p class="mt-1 text-sm text-slate-500">{{ t('register.subtitle') }}</p>

      <form class="mt-5 flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
        <p v-if="generalError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ generalError }}</p>

        <FormField id="name" v-model="form.name" :label="t('register.name')" autocomplete="name" :error="errors.name" />
        <FormField
          id="email"
          v-model="form.email"
          :label="t('register.email')"
          type="email"
          autocomplete="email"
          :error="errors.email"
        />
        <div>
          <FormField
            id="password"
            v-model="form.password"
            :label="t('register.password')"
            type="password"
            autocomplete="new-password"
            :error="errors.password"
          />
          <ul class="mt-1.5 list-disc pl-5 text-xs text-slate-400">
            <li v-for="rule in passwordRules" :key="rule">{{ t(rule) }}</li>
          </ul>
        </div>
        <FormField
          id="password_confirmation"
          v-model="form.password_confirmation"
          :label="t('register.confirmPassword')"
          type="password"
          autocomplete="new-password"
          :error="errors.password_confirmation"
        />

        <AppButton type="submit" :loading="loading">{{ t('register.submit') }}</AppButton>
      </form>

      <p class="mt-4 text-center text-sm text-slate-500">
        {{ t('register.alreadyRegistered') }}
        <RouterLink :to="{ name: 'login' }" class="font-medium text-slate-900 hover:underline">{{
          t('common.signIn')
        }}</RouterLink>
      </p>
    </div>
  </div>
</template>
