<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { normalizeError } from '@/shared/api/errors'
import FormField from '@/components/FormField.vue'
import AppButton from '@/components/AppButton.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = reactive({ email: '', password: '' })
const errors = ref<Record<string, string>>({})
const generalError = ref('')
const loading = ref(false)

async function onSubmit(): Promise<void> {
  loading.value = true
  errors.value = {}
  generalError.value = ''

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
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h1 class="text-lg font-semibold text-slate-900">Sign in</h1>
      <p class="mt-1 text-sm text-slate-500">Welcome back to DocSign Hub.</p>

      <form class="mt-5 flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
        <p v-if="generalError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ generalError }}</p>

        <FormField
          id="email"
          v-model="form.email"
          label="Email"
          type="email"
          autocomplete="email"
          :error="errors.email"
        />
        <FormField
          id="password"
          v-model="form.password"
          label="Password"
          type="password"
          autocomplete="current-password"
          :error="errors.password"
        />

        <AppButton type="submit" :loading="loading">Sign in</AppButton>
      </form>

      <p class="mt-4 text-center text-sm text-slate-500">
        No account?
        <RouterLink :to="{ name: 'register' }" class="font-medium text-slate-900 hover:underline">Create one</RouterLink>
      </p>
    </div>
  </div>
</template>
