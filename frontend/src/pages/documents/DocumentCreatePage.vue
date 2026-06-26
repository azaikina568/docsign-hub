<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCreateDocument } from '@/features/documents/queries'
import { normalizeError } from '@/shared/api/errors'
import { toast } from '@/shared/toast'
import { createDocumentSchema, validate } from '@/shared/validation'
import AppLayout from '@/layouts/AppLayout.vue'
import FormField from '@/components/FormField.vue'
import AppButton from '@/components/AppButton.vue'

const router = useRouter()
const createDocument = useCreateDocument()

const form = reactive({ title: '', expiresAt: '' })
const errors = ref<Record<string, string>>({})
const generalError = ref('')

async function onSubmit(): Promise<void> {
  generalError.value = ''

  const clientErrors = validate(createDocumentSchema, { title: form.title })
  if (clientErrors) {
    errors.value = clientErrors
    return
  }

  errors.value = {}

  try {
    // datetime-local — локальное время без зоны; приводим к ISO для бэкенда (правило after:now).
    const expires_at = form.expiresAt ? new Date(form.expiresAt).toISOString() : null
    const doc = await createDocument.mutateAsync({ title: form.title, expires_at })
    toast.success('Draft created.')
    await router.push({ name: 'document-detail', params: { id: doc.id } })
  } catch (error) {
    const normalized = normalizeError(error)
    errors.value = normalized.fields
    generalError.value = Object.keys(normalized.fields).length ? '' : normalized.message
  }
}
</script>

<template>
  <AppLayout>
    <RouterLink :to="{ name: 'dashboard' }" class="text-sm text-slate-500 hover:underline">← Back to documents</RouterLink>

    <h1 class="mt-3 text-xl font-semibold text-slate-900">New document</h1>

    <form class="mt-5 flex max-w-md flex-col gap-4" novalidate @submit.prevent="onSubmit">
      <p v-if="generalError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ generalError }}</p>

      <FormField id="title" v-model="form.title" label="Title" :error="errors.title" />

      <div class="flex flex-col gap-1.5">
        <label for="expires_at" class="text-sm font-medium text-slate-700">Signing deadline (optional)</label>
        <input
          id="expires_at"
          v-model="form.expiresAt"
          type="datetime-local"
          class="rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
        />
        <p v-if="errors.expires_at" class="text-sm text-red-600">{{ errors.expires_at }}</p>
        <p class="text-xs text-slate-400">
          If left empty, a default 14-day deadline is applied when the document is sent. Signing links stay valid until then.
        </p>
      </div>

      <div class="flex gap-2">
        <AppButton type="submit" :loading="createDocument.isPending.value">Create draft</AppButton>
        <RouterLink :to="{ name: 'dashboard' }" class="inline-flex items-center px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-md">
          Cancel
        </RouterLink>
      </div>
    </form>
  </AppLayout>
</template>
