<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { normalizeError } from '@/shared/api/errors'
import FormField from '@/components/FormField.vue'
import AppButton from '@/components/AppButton.vue'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const errors = ref<Record<string, string>>({})
const generalError = ref('')
const loading = ref(false)

async function onSubmit(): Promise<void> {
  loading.value = true
  errors.value = {}
  generalError.value = ''

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
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-8">
    <div class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h1 class="text-lg font-semibold text-slate-900">Create account</h1>
      <p class="mt-1 text-sm text-slate-500">Start sending documents for signing.</p>

      <form class="mt-5 flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
        <p v-if="generalError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ generalError }}</p>

        <FormField id="name" v-model="form.name" label="Name" autocomplete="name" :error="errors.name" />
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
          autocomplete="new-password"
          :error="errors.password"
        />
        <FormField
          id="password_confirmation"
          v-model="form.password_confirmation"
          label="Confirm password"
          type="password"
          autocomplete="new-password"
          :error="errors.password_confirmation"
        />

        <AppButton type="submit" :loading="loading">Create account</AppButton>
      </form>

      <p class="mt-4 text-center text-sm text-slate-500">
        Already registered?
        <RouterLink :to="{ name: 'login' }" class="font-medium text-slate-900 hover:underline">Sign in</RouterLink>
      </p>
    </div>
  </div>
</template>
